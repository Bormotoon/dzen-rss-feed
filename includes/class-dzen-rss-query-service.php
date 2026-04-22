<?php
/**
 * Queries candidate posts using WP_Query and keeps the selection logic centralized.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Query_Service
{
    public function __construct(private readonly Dzen_RSS_Options $options)
    {
    }

    /**
     * @return WP_Post[]
     */
    public function get_candidate_posts(): array
    {
        if (! $this->options->is_enabled()) {
            return [];
        }

        $limit = $this->options->get_limit();
        $query_limit = min(max($limit * 4, $limit), Dzen_RSS_Constants::MAX_QUERY_LIMIT);

        $query_args = [
            'post_type' => $this->options->get_allowed_post_types(),
            'post_status' => 'publish',
            'posts_per_page' => $query_limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'ignore_sticky_posts' => true,
            'no_found_rows' => true,
            'has_password' => false,
            'cache_results' => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => true,
            'suppress_filters' => false,
        ];

        $query = new WP_Query($query_args);
        if (! $query->have_posts()) {
            return [];
        }

        $posts = $query->posts;
        if (! is_array($posts)) {
            return [];
        }

        $candidate_ids = array_map(
            static fn(WP_Post $post): int => (int) $post->ID,
            array_filter($posts, static fn($post): bool => $post instanceof WP_Post)
        );

        $candidate_ids = apply_filters('dzen_rss_candidate_post_ids', $candidate_ids, $query_args, $this->options->all());
        $candidate_ids = array_map('intval', is_array($candidate_ids) ? $candidate_ids : []);
        $candidate_ids = array_values(array_unique(array_filter($candidate_ids)));

        if ($candidate_ids === []) {
            return [];
        }

        $by_id = [];
        foreach ($posts as $post) {
            if ($post instanceof WP_Post) {
                $by_id[$post->ID] = $post;
            }
        }

        $filtered = [];
        foreach ($candidate_ids as $candidate_id) {
            if (isset($by_id[$candidate_id])) {
                $filtered[] = $by_id[$candidate_id];
            }
        }

        return $filtered;
    }
}

