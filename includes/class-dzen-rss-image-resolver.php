<?php
/**
 * Resolves feed-safe images and converts unsupported local uploads when possible.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Image_Resolver
{
    /**
     * Resolve a feed-ready image. Unsupported local attachments are transcoded to JPEG.
     *
     * @return array{
     *     url:?string,
     *     mime_type:?string,
     *     source_mime_type:?string,
     *     width:?int,
     *     height:?int,
     *     converted:bool,
     *     warnings:string[]
     * }
     */
    public function resolve(WP_Post $post, ?string $image_url, ?string $mime_type, ?int $width, ?int $height): array
    {
        $normalized_url = $this->normalize_url($image_url);
        $normalized_mime = $this->normalize_mime_type($mime_type);
        $detected_mime = $normalized_mime;

        if ($detected_mime === '' && $normalized_url !== '') {
            $file_reference = (string) (wp_parse_url($normalized_url, PHP_URL_PATH) ?: $normalized_url);
            $filetype = wp_check_filetype($file_reference);
            if (! empty($filetype['type'])) {
                $detected_mime = $this->normalize_mime_type((string) $filetype['type']);
            }
        }

        $result = [
            'url' => $normalized_url !== '' ? $normalized_url : null,
            'mime_type' => $detected_mime !== '' ? $detected_mime : null,
            'source_mime_type' => $detected_mime !== '' ? $detected_mime : null,
            'width' => $width,
            'height' => $height,
            'converted' => false,
            'warnings' => [],
        ];

        if ($normalized_url === '' || $this->is_supported_mime_type($detected_mime)) {
            return $result;
        }

        $conversion = $this->convert_to_jpeg($post, $normalized_url);
        if (! is_array($conversion)) {
            return $result;
        }

        $result['url'] = $conversion['url'];
        $result['mime_type'] = 'image/jpeg';
        $result['width'] = isset($conversion['width']) ? absint($conversion['width']) : $result['width'];
        $result['height'] = isset($conversion['height']) ? absint($conversion['height']) : $result['height'];
        $result['converted'] = true;
        $result['warnings'][] = __('Image was converted to JPEG for Dzen feed compatibility.', 'dzen-rss-feed');

        return $result;
    }

    private function normalize_url(?string $url): string
    {
        if (! is_string($url)) {
            return '';
        }

        return esc_url_raw(trim($url));
    }

    private function normalize_mime_type(?string $mime_type): string
    {
        if (! is_string($mime_type) || $mime_type === '') {
            return '';
        }

        return Dzen_RSS_Constants::normalize_image_mime_type($mime_type);
    }

    private function is_supported_mime_type(string $mime_type): bool
    {
        if ($mime_type === '') {
            return false;
        }

        return in_array($mime_type, Dzen_RSS_Constants::allowed_image_mime_types(), true);
    }

    /**
     * @return array{url:string,width?:int,height?:int}|null
     */
    private function convert_to_jpeg(WP_Post $post, string $image_url): ?array
    {
        $attachment_id = $this->get_attachment_id_for_image($post, $image_url);
        if ($attachment_id <= 0) {
            return null;
        }

        $source_path = (string) get_attached_file($attachment_id);
        if ($source_path === '' || ! is_readable($source_path)) {
            return null;
        }

        if (! function_exists('wp_get_image_editor')) {
            return null;
        }

        $editor = wp_get_image_editor($source_path);
        if (is_wp_error($editor)) {
            return null;
        }

        if (method_exists($editor, 'set_quality')) {
            $editor->set_quality(Dzen_RSS_Constants::DEFAULT_IMAGE_CONVERSION_QUALITY);
        }

        $upload_dir = wp_upload_dir();
        if (! is_array($upload_dir) || empty($upload_dir['basedir']) || empty($upload_dir['baseurl'])) {
            return null;
        }

        $target_dir = trailingslashit((string) $upload_dir['basedir']) . Dzen_RSS_Constants::IMAGE_DERIVATIVE_SUBDIR;
        if (! wp_mkdir_p($target_dir)) {
            return null;
        }

        $hash_source = $source_path . '|' . @filemtime($source_path) . '|' . @filesize($source_path) . '|' . $attachment_id;
        $target_name = sprintf('attachment-%d-%s.jpg', $attachment_id, substr(sha1($hash_source), 0, 16));
        $target_path = trailingslashit($target_dir) . $target_name;

        if (! file_exists($target_path) || @filesize($target_path) === 0) {
            $saved = $editor->save($target_path, 'image/jpeg');
            if (is_wp_error($saved) || ! is_array($saved)) {
                return null;
            }
        }

        if (! file_exists($target_path)) {
            return null;
        }

        $url = trailingslashit((string) $upload_dir['baseurl']) . Dzen_RSS_Constants::IMAGE_DERIVATIVE_SUBDIR . '/' . $target_name;
        $size = @getimagesize($target_path);

        return [
            'url' => esc_url_raw($url),
            'width' => is_array($size) && isset($size[0]) ? absint($size[0]) : null,
            'height' => is_array($size) && isset($size[1]) ? absint($size[1]) : null,
        ];
    }

    private function get_attachment_id_for_image(WP_Post $post, ?string $image_url): int
    {
        if ($image_url === null || $image_url === '') {
            return 0;
        }

        $featured = $this->resolve_featured_image($post);
        if (($featured['url'] ?? null) === $image_url) {
            return (int) ($featured['attachment_id'] ?? 0);
        }

        if (function_exists('attachment_url_to_postid')) {
            $attachment_id = absint(attachment_url_to_postid($image_url));
            if ($attachment_id > 0) {
                return $attachment_id;
            }

            $parsed_path = (string) (wp_parse_url($image_url, PHP_URL_PATH) ?: '');
            if ($parsed_path !== '') {
                $attachment_id = absint(attachment_url_to_postid(home_url($parsed_path)));
                if ($attachment_id > 0) {
                    return $attachment_id;
                }
            }
        }

        return 0;
    }

    /**
     * @return array{url?:string,width?:int,height?:int,attachment_id?:int}
     */
    private function resolve_featured_image(WP_Post $post): array
    {
        $thumbnail_id = get_post_thumbnail_id($post);
        if ($thumbnail_id <= 0) {
            return [];
        }

        $image = wp_get_attachment_image_src($thumbnail_id, 'full');
        if (! is_array($image) || empty($image[0])) {
            return [];
        }

        return [
            'url' => (string) $image[0],
            'width' => isset($image[1]) ? absint($image[1]) : null,
            'height' => isset($image[2]) ? absint($image[2]) : null,
            'attachment_id' => $thumbnail_id,
        ];
    }
}
