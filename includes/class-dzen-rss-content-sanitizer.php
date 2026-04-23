<?php
/**
 * High-level sanitization pipeline for mapped feed items.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Content_Sanitizer
{
    public function __construct(
        private readonly Dzen_RSS_Options $options,
        private readonly Dzen_RSS_HTML_Normalizer $html_normalizer,
        private readonly Dzen_RSS_Logger $logger
    ) {
    }

    public function sanitize(Dzen_RSS_Feed_Item $item): Dzen_RSS_Feed_Item
    {
        $mode = $this->options->get_sanitation_mode();

        $item->content_html = $this->html_normalizer->normalize($item->source_content_html, $item, $mode);

        $allowed_html = Dzen_RSS_Constants::allowed_html($mode);
        $item->content_html = wp_kses($item->content_html, $allowed_html, Dzen_RSS_Constants::allowed_protocols());
        $item->content_html = $this->rewrite_converted_image_urls($item->content_html, $item);
        $item->content_html = trim((string) apply_filters('dzen_rss_sanitized_html', $item->content_html, $item, $mode));

        $item->title = $this->sanitize_text($item->title);
        $item->description = $this->sanitize_text($item->description);
        $item->author = $item->author !== '' ? $this->sanitize_text($item->author) : '';
        $item->source_content_html = trim($item->source_content_html);

        if ($item->image_url !== null) {
            $item->image_url = esc_url_raw($item->image_url);
        }

        if ($item->mobile_link !== null) {
            $item->mobile_link = esc_url_raw($item->mobile_link);
        }

        return $item;
    }

    private function rewrite_converted_image_urls(string $html, Dzen_RSS_Feed_Item $item): string
    {
        if ($html === '') {
            return $html;
        }

        if ($item->source_image_url === null || $item->source_image_url === '') {
            return $html;
        }

        if (empty($item->metadata['image_converted'])) {
            return $html;
        }

        if ($item->image_url === null || $item->image_url === '' || $item->image_url === $item->source_image_url) {
            return $html;
        }

        $source_variants = [
            $item->source_image_url,
            html_entity_decode($item->source_image_url, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            esc_url($item->source_image_url),
        ];
        $source_variants = array_values(array_unique(array_filter($source_variants, static fn(string $value): bool => $value !== '')));

        foreach ($source_variants as $source_variant) {
            $html = str_replace($source_variant, $item->image_url, $html);
        }

        return $html;
    }

    private function sanitize_text(string $value): string
    {
        $value = wp_strip_all_tags($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
