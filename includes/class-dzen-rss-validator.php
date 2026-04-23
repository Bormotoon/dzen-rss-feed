<?php
/**
 * Applies Dzen publication rules to a normalized feed item.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Validator
{
    public function __construct(private readonly Dzen_RSS_Options $options)
    {
    }

    public function validate(Dzen_RSS_Feed_Item $item, WP_Post $post): Dzen_RSS_Validation_Result
    {
        $result = new Dzen_RSS_Validation_Result(true);

        if ($item->title === '') {
            $result->add_reason('missing_title', __('Title is empty.', 'dzen-rss-feed'));
        }

        if (! $this->is_valid_url($item->link)) {
            $result->add_reason('invalid_link', __('Primary link is missing or invalid.', 'dzen-rss-feed'));
        }

        if ($item->guid === '') {
            $result->add_reason('missing_guid', __('GUID is missing.', 'dzen-rss-feed'));
        }

        if (! $this->is_valid_pub_date($item->pub_date)) {
            $result->add_reason('invalid_pub_date', __('Publication date is not valid RFC822 text.', 'dzen-rss-feed'));
        }

        if ($this->text_length($item->content_html) < $this->options->get_minimum_content_length()) {
            $reason = __('Content is shorter than the configured minimum length.', 'dzen-rss-feed');
            if ($item->has_image()) {
                $result->add_warning('short_content', $reason);
            } else {
                $result->add_reason('short_content', $reason);
            }
        }

        $item_image_width = $item->image_width ?? 0;
        if ($item->has_image() && $item->image_width === null) {
            $result->add_warning('image_size_unknown', __('Image dimensions could not be verified locally.', 'dzen-rss-feed'));
        }

        if ($item->has_image()) {
            $image_mime_type = $item->image_mime_type !== null && $item->image_mime_type !== ''
                ? Dzen_RSS_Constants::normalize_image_mime_type($item->image_mime_type)
                : '';

            if ($image_mime_type === '') {
                $result->add_warning('image_mime_unknown', __('Image MIME type could not be verified locally; enclosure will be omitted.', 'dzen-rss-feed'));
            } elseif (! in_array($image_mime_type, Dzen_RSS_Constants::allowed_image_mime_types(), true)) {
                $result->add_warning(
                    'unsupported_image_format',
                    sprintf(__('Unsupported image format: %s. Allowed formats are JPEG, PNG, GIF and WebP; enclosure will be omitted.', 'dzen-rss-feed'), $image_mime_type)
                );
            }
        }

        if ($item->has_image() && $item_image_width > 0 && $item_image_width < 700) {
            $result->add_reason('image_too_small', __('Image width is below the Dzen recommendation of 700px.', 'dzen-rss-feed'));
        }

        foreach ($item->publication_directives as $directive) {
            if (! in_array($directive, Dzen_RSS_Constants::allowed_publication_directives(), true)) {
                $result->add_reason('invalid_publication_directive', sprintf(__('Unsupported directive: %s', 'dzen-rss-feed'), $directive));
            }
        }

        if ($this->post_is_disallowed($post)) {
            $result->add_reason('disallowed_post', __('The source post is not eligible for the feed.', 'dzen-rss-feed'));
        }

        if ($this->post_is_password_protected($post)) {
            $result->add_reason('password_protected', __('Password-protected posts are excluded.', 'dzen-rss-feed'));
        }

        if ($this->post_is_excluded_by_meta($post)) {
            $result->add_reason('meta_excluded', __('The post is explicitly excluded by meta.', 'dzen-rss-feed'));
        }

        $excluded_taxonomies = $this->options->get_excluded_taxonomies();
        if ($excluded_taxonomies !== [] && $this->post_hits_excluded_taxonomies($post, $excluded_taxonomies)) {
            $result->add_reason('taxonomy_excluded', __('The post belongs to an excluded taxonomy.', 'dzen-rss-feed'));
        }

        if ($this->options->get_inclusion_mode() === Dzen_RSS_Constants::INCLUSION_EXPLICIT && ! $this->post_is_explicitly_included($post)) {
            $result->add_reason('missing_explicit_include', __('The post is not explicitly included.', 'dzen-rss-feed'));
        }

        if ($item->media_rating !== '' && $item->media_rating !== 'nonadult') {
            $result->add_warning('media_rating', __('Unexpected media rating value; the feed will fall back to nonadult.', 'dzen-rss-feed'));
            $item->media_rating = 'nonadult';
        }

        if ($item->content_html === '') {
            $result->add_reason('empty_content', __('Sanitized content is empty.', 'dzen-rss-feed'));
        }

        if ($this->has_forbidden_markup($item->content_html)) {
            $result->add_reason('forbidden_markup', __('The sanitized content still contains disallowed markup.', 'dzen-rss-feed'));
        }

        if ($item->author === '') {
            $result->add_warning('missing_author', __('Author was not resolved; the feed will omit the author element.', 'dzen-rss-feed'));
        }

        if ($item->description === '') {
            $result->add_warning('missing_description', __('Description is empty; the card will rely on the full content.', 'dzen-rss-feed'));
        }

        return $result;
    }

    private function is_valid_url(string $url): bool
    {
        return $url !== '' && (bool) filter_var($url, FILTER_VALIDATE_URL);
    }

    private function is_valid_pub_date(string $date): bool
    {
        if ($date === '') {
            return false;
        }

        $parsed = DateTimeImmutable::createFromFormat(DATE_RSS, $date);
        if ($parsed === false) {
            return false;
        }

        $errors = DateTimeImmutable::getLastErrors();
        if ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
            return false;
        }

        return $parsed->format(DATE_RSS) === $date;
    }

    private function text_length(string $html): int
    {
        $text = wp_strip_all_tags($html);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return 0;
        }

        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }

    private function post_is_password_protected(WP_Post $post): bool
    {
        return $post->post_password !== '';
    }

    private function post_is_disallowed(WP_Post $post): bool
    {
        return $post->post_status !== 'publish' || ! in_array($post->post_type, $this->options->get_allowed_post_types(), true);
    }

    private function post_is_excluded_by_meta(WP_Post $post): bool
    {
        $exclude = (string) get_post_meta($post->ID, Dzen_RSS_Constants::META_EXCLUDE, true);
        return $exclude !== '' && $this->as_bool($exclude);
    }

    private function post_is_explicitly_included(WP_Post $post): bool
    {
        $include = (string) get_post_meta($post->ID, Dzen_RSS_Constants::META_INCLUDE, true);
        return $include !== '' && $this->as_bool($include);
    }

    /**
     * @param string[] $excluded_taxonomies
     */
    private function post_hits_excluded_taxonomies(WP_Post $post, array $excluded_taxonomies): bool
    {
        foreach ($excluded_taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($post->ID, $taxonomy, ['fields' => 'ids']);
            if (! is_wp_error($terms) && $terms !== []) {
                return true;
            }
        }

        return false;
    }

    private function has_forbidden_markup(string $html): bool
    {
        return stripos($html, '<script') !== false || stripos($html, '<style') !== false || stripos($html, 'javascript:') !== false;
    }

    private function as_bool(string $value): bool
    {
        $normalized = strtolower(trim($value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
