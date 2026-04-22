<?php
/**
 * Maps a WP_Post into a normalized Dzen feed DTO.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Mapper
{
    public function __construct(private readonly Dzen_RSS_Options $options)
    {
    }

    public function map_post_to_feed_item(WP_Post $post): Dzen_RSS_Feed_Item
    {
        $item = new Dzen_RSS_Feed_Item((int) $post->ID, (string) $post->post_type);

        $item->source_title = $this->resolve_title($post);
        $item->title = $item->source_title;

        $item->source_link = $this->resolve_link($post);
        $item->link = $item->source_link;

        $item->guid = $this->resolve_guid($post);
        $item->pub_date = $this->resolve_pub_date($post);
        $item->media_rating = 'nonadult';
        $item->publication_directives = $this->resolve_publication_directives($post);

        $item->source_content_html = $this->resolve_content_html($post);
        $item->content_html = $item->source_content_html;

        $item->source_description = $this->resolve_description($post, $item->source_content_html);
        $item->description = $item->source_description;

        $item->source_author = $this->resolve_author($post);
        $item->author = $item->source_author;

        $item->source_image_url = $this->resolve_image_url($post, $item->source_content_html);
        $item->image_url = $item->source_image_url;
        $item->image_mime_type = $this->resolve_image_mime_type($item->source_image_url, $post);
        $item->image_width = $this->resolve_image_width($post, $item->source_image_url);
        $item->image_height = $this->resolve_image_height($post, $item->source_image_url);
        $item->mobile_link = $this->resolve_mobile_link($post);

        $item->source_terms = $this->collect_terms($post);
        $item->metadata = [
            'post_name' => $post->post_name,
            'post_date_gmt' => $post->post_date_gmt,
            'post_modified_gmt' => $post->post_modified_gmt,
            'is_featured' => (bool) get_post_meta($post->ID, 'pedobraz_featured', true),
        ];

        return $item;
    }

    private function resolve_title(WP_Post $post): string
    {
        $override = (string) get_post_meta($post->ID, Dzen_RSS_Constants::META_TITLE_OVERRIDE, true);
        if ($override !== '') {
            return $override;
        }

        return get_the_title($post);
    }

    private function resolve_link(WP_Post $post): string
    {
        $override = (string) get_post_meta($post->ID, Dzen_RSS_Constants::META_SOURCE_URL_OVERRIDE, true);
        if ($override !== '') {
            return esc_url_raw($override);
        }

        return (string) get_permalink($post);
    }

    private function resolve_guid(WP_Post $post): string
    {
        return sha1(home_url('/') . '|' . $post->ID . '|' . $post->post_type);
    }

    private function resolve_pub_date(WP_Post $post): string
    {
        $override = (string) get_post_meta($post->ID, Dzen_RSS_Constants::META_PUB_DATE_OVERRIDE, true);
        $timestamp = false;

        if ($override !== '') {
            $timestamp = strtotime($override);
        }

        if ($timestamp === false) {
            $timestamp = $post->post_date_gmt !== '0000-00-00 00:00:00'
                ? strtotime($post->post_date_gmt . ' UTC')
                : time();
        }

        return wp_date('D, d M Y H:i:s O', $timestamp);
    }

    /**
     * @return string[]
     */
    private function resolve_publication_directives(WP_Post $post): array
    {
        $directives = $this->options->get_publication_directives();
        $directives = apply_filters('dzen_rss_publication_directives', $directives, $post, $this->options->all());

        return $this->normalize_publication_directives(is_array($directives) ? $directives : []);
    }

    private function resolve_content_html(WP_Post $post): string
    {
        $source = $this->options->get_content_source();
        $content = (string) $post->post_content;

        if ($source === Dzen_RSS_Constants::SOURCE_CONTENT_RENDERED && function_exists('apply_filters')) {
            $content = (string) apply_filters('the_content', $content);
        } elseif (function_exists('do_blocks')) {
            $content = (string) do_blocks($content);
        }

        return $content;
    }

    private function resolve_description(WP_Post $post, string $content_html): string
    {
        $source = $this->options->get_summary_source();
        $override = (string) get_post_meta($post->ID, Dzen_RSS_Constants::META_DESCRIPTION_OVERRIDE, true);

        if ($source === Dzen_RSS_Constants::SOURCE_SUMMARY_META && $override !== '') {
            return $override;
        }

        if ($source === Dzen_RSS_Constants::SOURCE_SUMMARY_NONE) {
            return '';
        }

        if ($source === Dzen_RSS_Constants::SOURCE_SUMMARY_EXCERPT) {
            $excerpt = (string) get_the_excerpt($post);
            if ($excerpt !== '') {
                return $excerpt;
            }
        }

        return $this->extract_first_paragraph($content_html);
    }

    private function resolve_author(WP_Post $post): string
    {
        $source = $this->options->get_author_source();
        if ($source === Dzen_RSS_Constants::SOURCE_AUTHOR_NONE) {
            return '';
        }

        if ($source === Dzen_RSS_Constants::SOURCE_AUTHOR_SITE) {
            return (string) get_bloginfo('name');
        }

        if ($source === Dzen_RSS_Constants::SOURCE_AUTHOR_META) {
            $override = (string) get_post_meta($post->ID, Dzen_RSS_Constants::META_AUTHOR_OVERRIDE, true);
            if ($override !== '') {
                return $override;
            }
        }

        $author = (string) get_the_author_meta('display_name', (int) $post->post_author);

        return $author !== '' ? $author : (string) get_bloginfo('name');
    }

    private function resolve_image_url(WP_Post $post, string $content_html): ?string
    {
        $source = $this->options->get_image_source();
        $override = (string) get_post_meta($post->ID, Dzen_RSS_Constants::META_IMAGE_OVERRIDE, true);
        $featured = $this->resolve_featured_image($post);
        $content_image = $this->extract_first_image($content_html);

        if ($source === Dzen_RSS_Constants::SOURCE_IMAGE_NONE) {
            return null;
        }

        $ordered = match ($source) {
            Dzen_RSS_Constants::SOURCE_IMAGE_META => [$override, $featured['url'] ?? null, $content_image['url'] ?? null],
            Dzen_RSS_Constants::SOURCE_IMAGE_CONTENT => [$content_image['url'] ?? null, $featured['url'] ?? null, $override],
            default => [$featured['url'] ?? null, $content_image['url'] ?? null, $override],
        };

        foreach ($ordered as $url) {
            if (is_string($url) && $url !== '') {
                return esc_url_raw($url);
            }
        }

        return null;
    }

    private function resolve_mobile_link(WP_Post $post): ?string
    {
        return null;
    }

    private function resolve_image_mime_type(?string $image_url, WP_Post $post): ?string
    {
        if ($image_url === null || $image_url === '') {
            return null;
        }

        $attachment_id = $this->get_attachment_id_for_image($post, $image_url);
        if ($attachment_id > 0) {
            $mime = get_post_mime_type($attachment_id);
            if (is_string($mime) && $mime !== '') {
                $mime_type = Dzen_RSS_Constants::normalize_image_mime_type($mime);
                return $mime_type !== '' ? $mime_type : null;
            }
        }

        $filetype = wp_check_filetype($image_url);
        if (! empty($filetype['type'])) {
            $mime_type = Dzen_RSS_Constants::normalize_image_mime_type((string) $filetype['type']);
            return $mime_type !== '' ? $mime_type : null;
        }

        return null;
    }

    private function resolve_image_width(WP_Post $post, ?string $image_url): ?int
    {
        $attachment_id = $this->get_attachment_id_for_image($post, $image_url);
        if ($attachment_id > 0) {
            $meta = wp_get_attachment_metadata($attachment_id);
            if (is_array($meta) && isset($meta['width'])) {
                return absint($meta['width']);
            }
        }

        return null;
    }

    private function resolve_image_height(WP_Post $post, ?string $image_url): ?int
    {
        $attachment_id = $this->get_attachment_id_for_image($post, $image_url);
        if ($attachment_id > 0) {
            $meta = wp_get_attachment_metadata($attachment_id);
            if (is_array($meta) && isset($meta['height'])) {
                return absint($meta['height']);
            }
        }

        return null;
    }

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

    private function extract_first_image(string $html): array
    {
        if ($html === '') {
            return [];
        }

        if (! preg_match('~<img[^>]+src=["\']([^"\']+)["\']~i', $html, $matches)) {
            return [];
        }

        return [
            'url' => html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ];
    }

    private function extract_first_paragraph(string $html): string
    {
        if ($html === '') {
            return '';
        }

        if (preg_match('~<p[^>]*>(.*?)</p>~is', $html, $matches)) {
            return wp_strip_all_tags($matches[1]);
        }

        return wp_strip_all_tags($html);
    }

    /**
     * @return array<string, string[]>
     */
    private function collect_terms(WP_Post $post): array
    {
        $taxonomies = get_object_taxonomies($post->post_type, 'names');
        $out = [];
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($post->ID, $taxonomy, ['fields' => 'names']);
            if (! is_wp_error($terms) && $terms !== []) {
                $out[$taxonomy] = array_map('strval', $terms);
            }
        }

        return $out;
    }

    /**
     * @param mixed[] $directives
     * @return string[]
     */
    private function normalize_publication_directives(array $directives): array
    {
        $allowed = Dzen_RSS_Constants::allowed_publication_directives();
        $normalized = [];

        foreach ($directives as $directive) {
            if (! is_string($directive)) {
                continue;
            }

            $directive = sanitize_key($directive);
            if ($directive === '' || $directive === Dzen_RSS_Constants::PUBLICATION_AUTO) {
                continue;
            }

            if (in_array($directive, $allowed, true) && ! in_array($directive, $normalized, true)) {
                $normalized[] = $directive;
            }
        }

        return $normalized;
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
        }

        return 0;
    }
}
