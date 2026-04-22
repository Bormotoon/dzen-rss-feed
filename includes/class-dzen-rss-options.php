<?php
/**
 * Thin wrapper around the plugin option array and its sanitization rules.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Options
{
    /**
     * Return the merged option set with defaults.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = get_option(Dzen_RSS_Constants::OPTION_NAME, []);
        if (! is_array($stored)) {
            $stored = [];
        }

        return wp_parse_args($stored, Dzen_RSS_Constants::default_options());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $options = $this->all();

        return $options[$key] ?? $default;
    }

    public function is_enabled(): bool
    {
        return (bool) $this->get('enabled', 0);
    }

    public function is_debug_mode(): bool
    {
        return (bool) $this->get('debug_mode', 0);
    }

    public function diagnostics_enabled(): bool
    {
        return (bool) $this->get('diagnostics_enabled', 0);
    }

    public function get_feed_slug(): string
    {
        $slug = sanitize_title((string) $this->get('feed_slug', Dzen_RSS_Constants::DEFAULT_FEED_SLUG));

        return $slug !== '' ? $slug : Dzen_RSS_Constants::DEFAULT_FEED_SLUG;
    }

    /**
     * @return string[]
     */
    public function get_feed_slugs(): array
    {
        $current = $this->get_feed_slug();
        $aliases = $this->get_feed_slug_aliases();
        $slugs = array_values(array_unique(array_filter(array_merge([$current], $aliases))));

        return apply_filters('dzen_rss_feed_slugs', $slugs, $this->all());
    }

    /**
     * @return string[]
     */
    public function get_feed_slug_aliases(): array
    {
        $aliases = $this->normalize_list($this->get('feed_slug_aliases', []));
        $aliases = array_map('sanitize_title', $aliases);
        $aliases = array_values(array_filter($aliases));

        return array_values(array_unique($aliases));
    }

    /**
     * @return string[]
     */
    public function get_allowed_post_types(): array
    {
        $stored = $this->normalize_list($this->get('allowed_post_types', ['post']));
        $allowed = [];
        $public_types = get_post_types([
            'public' => true,
            'show_ui' => true,
        ], 'names');

        foreach ($stored as $post_type) {
            $post_type = sanitize_key((string) $post_type);
            if ($post_type !== '' && in_array($post_type, $public_types, true)) {
                $allowed[] = $post_type;
            }
        }

        if ($allowed === []) {
            $allowed = ['post'];
        }

        return array_values(array_unique($allowed));
    }

    public function get_limit(): int
    {
        $limit = absint($this->get('limit', Dzen_RSS_Constants::DEFAULT_LIMIT));
        if ($limit < 1) {
            $limit = Dzen_RSS_Constants::DEFAULT_LIMIT;
        }

        return min($limit, Dzen_RSS_Constants::MAX_QUERY_LIMIT);
    }

    public function get_publication_state(): string
    {
        return $this->normalize_publication_choice(
            'state',
            (string) $this->get('publication_state', Dzen_RSS_Constants::PUBLICATION_AUTO),
            Dzen_RSS_Constants::PUBLICATION_AUTO
        );
    }

    public function get_publication_format(): string
    {
        return $this->normalize_publication_choice(
            'format',
            (string) $this->get('publication_format', Dzen_RSS_Constants::PUBLICATION_FORMAT_ARTICLE),
            Dzen_RSS_Constants::PUBLICATION_FORMAT_ARTICLE
        );
    }

    public function get_publication_index(): string
    {
        return $this->normalize_publication_choice(
            'index',
            (string) $this->get('publication_index', Dzen_RSS_Constants::PUBLICATION_INDEX),
            Dzen_RSS_Constants::PUBLICATION_INDEX
        );
    }

    public function get_publication_comments(): string
    {
        return $this->normalize_publication_choice(
            'comments',
            (string) $this->get('publication_comments', Dzen_RSS_Constants::PUBLICATION_COMMENT_NONE),
            Dzen_RSS_Constants::PUBLICATION_COMMENT_NONE
        );
    }

    /**
     * @return string[]
     */
    public function get_publication_directives(): array
    {
        $directives = [];

        foreach ([
            $this->get_publication_state(),
            $this->get_publication_format(),
            $this->get_publication_index(),
            $this->get_publication_comments(),
        ] as $directive) {
            if ($directive !== Dzen_RSS_Constants::PUBLICATION_AUTO) {
                $directives[] = $directive;
            }
        }

        return array_values(array_unique($directives));
    }

    public function get_inclusion_mode(): string
    {
        $mode = (string) $this->get('inclusion_mode', Dzen_RSS_Constants::INCLUSION_ALL);

        return in_array($mode, [Dzen_RSS_Constants::INCLUSION_ALL, Dzen_RSS_Constants::INCLUSION_EXPLICIT], true)
            ? $mode
            : Dzen_RSS_Constants::INCLUSION_ALL;
    }

    public function get_author_source(): string
    {
        return $this->normalize_strategy('author', (string) $this->get('author_source', Dzen_RSS_Constants::SOURCE_AUTHOR_POST));
    }

    public function get_summary_source(): string
    {
        return $this->normalize_strategy('summary', (string) $this->get('summary_source', Dzen_RSS_Constants::SOURCE_SUMMARY_EXCERPT));
    }

    public function get_content_source(): string
    {
        return $this->normalize_strategy('content', (string) $this->get('content_source', Dzen_RSS_Constants::SOURCE_CONTENT_RENDERED));
    }

    public function get_image_source(): string
    {
        return $this->normalize_strategy('image', (string) $this->get('image_source', Dzen_RSS_Constants::SOURCE_IMAGE_FEATURED));
    }

    /**
     * @return string[]
     */
    public function get_excluded_taxonomies(): array
    {
        $raw = $this->get('excluded_taxonomies', []);
        $list = $this->normalize_list($raw);
        $known_taxonomies = get_taxonomies([
            'public' => true,
        ], 'names');
        $excluded = [];

        foreach ($list as $taxonomy) {
            $taxonomy = sanitize_key((string) $taxonomy);
            if ($taxonomy !== '' && in_array($taxonomy, $known_taxonomies, true)) {
                $excluded[] = $taxonomy;
            }
        }

        return array_values(array_unique($excluded));
    }

    public function get_minimum_content_length(): int
    {
        $min = absint($this->get('minimum_content_length', Dzen_RSS_Constants::DEFAULT_MIN_CONTENT_LENGTH));
        return $min > 0 ? $min : Dzen_RSS_Constants::DEFAULT_MIN_CONTENT_LENGTH;
    }

    public function get_cache_ttl(): int
    {
        $ttl = absint($this->get('cache_ttl', Dzen_RSS_Constants::DEFAULT_CACHE_TTL));

        return $ttl;
    }

    public function get_sanitation_mode(): string
    {
        $mode = (string) $this->get('sanitation_mode', Dzen_RSS_Constants::SANITATION_CONSERVATIVE);

        return in_array($mode, [Dzen_RSS_Constants::SANITATION_CONSERVATIVE, Dzen_RSS_Constants::SANITATION_STRICT], true)
            ? $mode
            : Dzen_RSS_Constants::SANITATION_CONSERVATIVE;
    }

    /**
     * Build a stable signature used by the cache layer.
     */
    public function get_cache_signature(): string
    {
        $snapshot = [
            'enabled' => $this->is_enabled(),
            'feed_slug' => $this->get_feed_slug(),
            'allowed_post_types' => $this->get_allowed_post_types(),
            'limit' => $this->get_limit(),
            'publication_directives' => $this->get_publication_directives(),
            'inclusion_mode' => $this->get_inclusion_mode(),
            'author_source' => $this->get_author_source(),
            'summary_source' => $this->get_summary_source(),
            'content_source' => $this->get_content_source(),
            'image_source' => $this->get_image_source(),
            'excluded_taxonomies' => $this->get_excluded_taxonomies(),
            'minimum_content_length' => $this->get_minimum_content_length(),
            'sanitation_mode' => $this->get_sanitation_mode(),
            'site_url' => home_url('/'),
            'language' => Dzen_RSS_Constants::get_language_code(),
        ];

        return hash('sha256', wp_json_encode($snapshot));
    }

    /**
     * Sanitize raw settings data from the Settings API.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public function sanitize_options(array $raw): array
    {
        $current = $this->all();
        $sanitized = Dzen_RSS_Constants::default_options();

        $sanitized['enabled'] = ! empty($raw['enabled']) ? 1 : 0;
        $sanitized['debug_mode'] = ! empty($raw['debug_mode']) ? 1 : 0;
        $sanitized['diagnostics_enabled'] = ! empty($raw['diagnostics_enabled']) ? 1 : 0;

        $feed_slug = sanitize_title((string) ($raw['feed_slug'] ?? $current['feed_slug']));
        $feed_slug = $feed_slug !== '' ? $feed_slug : Dzen_RSS_Constants::DEFAULT_FEED_SLUG;
        $old_slug = sanitize_title((string) ($current['feed_slug'] ?? ''));
        $aliases = $this->normalize_list($raw['feed_slug_aliases'] ?? $current['feed_slug_aliases'] ?? []);
        if ($old_slug !== '' && $old_slug !== $feed_slug) {
            $aliases[] = $old_slug;
        }
        $aliases = array_values(array_unique(array_filter(array_map('sanitize_title', $aliases))));
        $aliases = array_values(array_diff($aliases, [$feed_slug]));

        $sanitized['feed_slug'] = $feed_slug;
        $sanitized['feed_slug_aliases'] = $aliases;

        $sanitized['allowed_post_types'] = $this->sanitize_post_types($raw['allowed_post_types'] ?? []);

        $limit = absint($raw['limit'] ?? $current['limit']);
        $sanitized['limit'] = max(1, min($limit > 0 ? $limit : Dzen_RSS_Constants::DEFAULT_LIMIT, Dzen_RSS_Constants::MAX_QUERY_LIMIT));

        $sanitized['publication_state'] = $this->normalize_publication_choice(
            'state',
            (string) ($raw['publication_state'] ?? $current['publication_state']),
            Dzen_RSS_Constants::PUBLICATION_AUTO
        );
        $sanitized['publication_format'] = $this->normalize_publication_choice(
            'format',
            (string) ($raw['publication_format'] ?? $current['publication_format']),
            Dzen_RSS_Constants::PUBLICATION_FORMAT_ARTICLE
        );
        $sanitized['publication_index'] = $this->normalize_publication_choice(
            'index',
            (string) ($raw['publication_index'] ?? $current['publication_index']),
            Dzen_RSS_Constants::PUBLICATION_INDEX
        );
        $sanitized['publication_comments'] = $this->normalize_publication_choice(
            'comments',
            (string) ($raw['publication_comments'] ?? $current['publication_comments']),
            Dzen_RSS_Constants::PUBLICATION_COMMENT_NONE
        );

        $inclusion_mode = (string) ($raw['inclusion_mode'] ?? $current['inclusion_mode']);
        $sanitized['inclusion_mode'] = in_array($inclusion_mode, [Dzen_RSS_Constants::INCLUSION_ALL, Dzen_RSS_Constants::INCLUSION_EXPLICIT], true)
            ? $inclusion_mode
            : Dzen_RSS_Constants::INCLUSION_ALL;

        $sanitized['author_source'] = $this->sanitize_strategy('author', (string) ($raw['author_source'] ?? $current['author_source']));
        $sanitized['summary_source'] = $this->sanitize_strategy('summary', (string) ($raw['summary_source'] ?? $current['summary_source']));
        $sanitized['content_source'] = $this->sanitize_strategy('content', (string) ($raw['content_source'] ?? $current['content_source']));
        $sanitized['image_source'] = $this->sanitize_strategy('image', (string) ($raw['image_source'] ?? $current['image_source']));

        $sanitized['excluded_taxonomies'] = $this->sanitize_excluded_taxonomies($raw['excluded_taxonomies'] ?? $current['excluded_taxonomies'] ?? []);

        $minimum = absint($raw['minimum_content_length'] ?? $current['minimum_content_length']);
        $sanitized['minimum_content_length'] = $minimum > 0 ? $minimum : Dzen_RSS_Constants::DEFAULT_MIN_CONTENT_LENGTH;

        $ttl = absint($raw['cache_ttl'] ?? $current['cache_ttl']);
        $sanitized['cache_ttl'] = $ttl;

        $sanitized['sanitation_mode'] = in_array((string) ($raw['sanitation_mode'] ?? $current['sanitation_mode']), [Dzen_RSS_Constants::SANITATION_CONSERVATIVE, Dzen_RSS_Constants::SANITATION_STRICT], true)
            ? (string) ($raw['sanitation_mode'] ?? $current['sanitation_mode'])
            : Dzen_RSS_Constants::SANITATION_CONSERVATIVE;

        return $sanitized;
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function normalize_list(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,\n\r]+/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $list = [];
        foreach ($value as $entry) {
            if (is_string($entry) || is_int($entry)) {
                $entry = trim((string) $entry);
                if ($entry !== '') {
                    $list[] = $entry;
                }
            }
        }

        return array_values(array_unique($list));
    }

    /**
     * @return string[]
     */
    private function sanitize_post_types(mixed $raw): array
    {
        $candidate = $this->normalize_list($raw);
        $public_types = get_post_types([
            'public' => true,
            'show_ui' => true,
        ], 'names');
        $allowed = [];

        foreach ($candidate as $post_type) {
            $post_type = sanitize_key($post_type);
            if ($post_type !== '' && in_array($post_type, $public_types, true)) {
                $allowed[] = $post_type;
            }
        }

        if ($allowed === []) {
            $allowed = ['post'];
        }

        return array_values(array_unique($allowed));
    }

    /**
     * @return string[]
     */
    private function sanitize_excluded_taxonomies(mixed $raw): array
    {
        $candidate = $this->normalize_list($raw);
        $known_taxonomies = get_taxonomies([
            'public' => true,
        ], 'names');
        $excluded = [];

        foreach ($candidate as $taxonomy) {
            $taxonomy = sanitize_key($taxonomy);
            if ($taxonomy !== '' && in_array($taxonomy, $known_taxonomies, true)) {
                $excluded[] = $taxonomy;
            }
        }

        return array_values(array_unique($excluded));
    }

    private function normalize_strategy(string $kind, string $value): string
    {
        $allowed = Dzen_RSS_Constants::source_strategy_options()[$kind] ?? [];
        return in_array($value, $allowed, true) ? $value : ($allowed[0] ?? $value);
    }

    private function sanitize_strategy(string $kind, string $value): string
    {
        return $this->normalize_strategy($kind, $value);
    }

    private function normalize_publication_choice(string $kind, string $value, string $fallback): string
    {
        $allowed = Dzen_RSS_Constants::publication_directive_options()[$kind] ?? [];

        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
