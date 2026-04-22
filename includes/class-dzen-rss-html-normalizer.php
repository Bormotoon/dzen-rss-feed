<?php
/**
 * Low-level HTML normalization for Dzen-friendly content fragments.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_HTML_Normalizer
{
    /**
     * Normalize a content fragment before whitelist sanitation.
     */
    public function normalize(string $html, Dzen_RSS_Feed_Item $item, string $sanitation_mode): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $html = $this->strip_noise($html);
        $html = $this->repair_fragment($html);

        if (! class_exists('DOMDocument')) {
            return $this->rewrite_relative_urls_with_regex($html);
        }

        return $this->rewrite_fragment_with_dom($html, $sanitation_mode);
    }

    private function strip_noise(string $html): string
    {
        $html = preg_replace('~<!--\s*/?wp:[\s\S]*?-->~i', '', $html) ?? $html;
        $html = preg_replace('~<!--more-->~i', '', $html) ?? $html;
        if (function_exists('strip_shortcodes')) {
            $html = strip_shortcodes($html);
        }
        if (function_exists('force_balance_tags')) {
            $html = force_balance_tags($html);
        }

        return $html;
    }

    private function repair_fragment(string $html): string
    {
        return preg_replace('~\s+~u', ' ', $html) ?? $html;
    }

    private function rewrite_fragment_with_dom(string $html, string $sanitation_mode): string
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapper_id = 'dzen-rss-root';
        $wrapped = '<div id="' . $wrapper_id . '">' . $html . '</div>';
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        if (! $loaded) {
            return $this->rewrite_relative_urls_with_regex($html);
        }

        $xpath = new DOMXPath($dom);
        $dangerous_tags = ['script', 'style', 'noscript', 'form', 'button', 'input', 'textarea', 'select', 'option', 'canvas'];
        foreach ($dangerous_tags as $tag) {
            /** @var DOMNode $node */
            foreach ($this->xpath_nodes($xpath, '//' . $tag) as $node) {
                $this->remove_node($node);
            }
        }

        foreach ($this->xpath_nodes($xpath, '//*[@href or @src or @poster or @srcset or @data-src or @data-original]') as $node) {
            if ($node instanceof DOMElement) {
                $this->rewrite_url_attributes($node, $sanitation_mode);
            }
        }

        if ($sanitation_mode === Dzen_RSS_Constants::SANITATION_STRICT) {
            foreach ($this->xpath_nodes($xpath, '//iframe') as $node) {
                $this->remove_node($node);
            }
        }

        $root = $xpath->query(sprintf("//*[@id='%s']", $wrapper_id))->item(0);
        if (! $root instanceof DOMNode) {
            return $html;
        }

        $normalized = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $normalized .= $dom->saveHTML($child);
        }

        return $normalized;
    }

    /**
     * @return array<int, DOMNode>
     */
    private function xpath_nodes(DOMXPath $xpath, string $query): array
    {
        $nodes = [];
        $list = $xpath->query($query);
        if (! $list) {
            return [];
        }

        foreach ($list as $node) {
            if ($node instanceof DOMNode) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function remove_node(DOMNode $node): void
    {
        if ($node->parentNode instanceof DOMNode) {
            $node->parentNode->removeChild($node);
        }
    }

    private function rewrite_url_attributes(DOMElement $node, string $sanitation_mode): void
    {
        if ($node->hasAttribute('href')) {
            $node->setAttribute('href', $this->make_absolute_url((string) $node->getAttribute('href')));
        }

        foreach (['src', 'poster'] as $attr) {
            if ($node->hasAttribute($attr)) {
                $node->setAttribute($attr, $this->make_absolute_url((string) $node->getAttribute($attr)));
            }
        }

        if ($node->hasAttribute('srcset')) {
            $node->removeAttribute('srcset');
        }

        if ($node->hasAttribute('data-src') && ! $node->hasAttribute('src')) {
            $node->setAttribute('src', $this->make_absolute_url((string) $node->getAttribute('data-src')));
        }

        if ($node->hasAttribute('data-original') && ! $node->hasAttribute('src')) {
            $node->setAttribute('src', $this->make_absolute_url((string) $node->getAttribute('data-original')));
        }

        if ($node->tagName === 'iframe') {
            $src = (string) $node->getAttribute('src');
            if ($src === '' || ! $this->is_allowed_embed_url($src) || $sanitation_mode === Dzen_RSS_Constants::SANITATION_STRICT) {
                $this->remove_node($node);
            }
        }
    }

    private function is_allowed_embed_url(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            return false;
        }

        $allowed_hosts = (array) apply_filters('dzen_rss_allowed_embed_hosts', Dzen_RSS_Constants::allowed_embed_hosts(), $url);

        return in_array(strtolower($host), array_map('strtolower', $allowed_hosts), true);
    }

    private function rewrite_relative_urls_with_regex(string $html): string
    {
        $callback = function (array $matches): string {
            $attribute = $matches[1];
            $url = $matches[2];

            return sprintf('%s="%s"', $attribute, esc_attr($this->make_absolute_url($url)));
        };

        $html = preg_replace_callback('/(href|src|poster)=["\']([^"\']+)["\']/i', $callback, $html) ?? $html;
        $html = preg_replace('/\ssrcset=["\'][^"\']*["\']/i', '', $html) ?? $html;

        return $html;
    }

    private function make_absolute_url(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || str_starts_with($url, '#')) {
            return $url;
        }
        if (preg_match('~^(mailto:|tel:|data:)~i', $url)) {
            return $url;
        }
        if (preg_match('~^[a-z][a-z0-9+\-.]*://~i', $url)) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return set_url_scheme($url);
        }
        if (str_starts_with($url, '/')) {
            return home_url($url);
        }

        return trailingslashit(home_url('/')) . ltrim($url, '/');
    }
}
