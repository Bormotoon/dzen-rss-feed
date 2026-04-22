<?php
/**
 * Coordinates the feed-generation pipeline and handles HTTP output.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Feed_Controller
{
    public function __construct(
        private readonly Dzen_RSS_Options $options,
        private readonly Dzen_RSS_Query_Service $query_service,
        private readonly Dzen_RSS_Mapper $mapper,
        private readonly Dzen_RSS_Content_Sanitizer $sanitizer,
        private readonly Dzen_RSS_Validator $validator,
        private readonly Dzen_RSS_Renderer $renderer,
        private readonly Dzen_RSS_Cache $cache,
        private readonly Dzen_RSS_Diagnostics $diagnostics,
        private readonly Dzen_RSS_Logger $logger
    ) {
    }

    public function serve_feed(): void
    {
        if (! $this->options->is_enabled()) {
            $this->respond_not_found();
        }

        $this->send_headers();

        if ($this->cache->is_enabled()) {
            $cached = $this->cache->get_payload();
            if (is_array($cached) && isset($cached['xml']) && is_string($cached['xml']) && $cached['xml'] !== '') {
                if ($this->options->diagnostics_enabled()) {
                    $report = $this->diagnostics->get_report();
                    $report['served_at'] = time();
                    $report['cache_hit'] = true;
                    $report['served_from_cache'] = true;
                    $report['notes'][] = __('Served from transient cache.', 'dzen-rss-feed');
                    $this->diagnostics->save_report($report);
                }

                echo $cached['xml'];
                exit;
            }
        }

        $payload = $this->build_feed_payload();
        $xml = (string) ($payload['xml'] ?? '');

        if ($xml === '') {
            $this->logger->error('Renderer returned an empty XML payload.');
            $this->respond_not_found();
        }

        if ($this->cache->is_enabled()) {
            $this->cache->set_xml($xml);
        }

        if ($this->options->diagnostics_enabled()) {
            $this->diagnostics->save_report((array) $payload['report']);
        }

        echo $xml;
        exit;
    }

    /**
     * @return array{xml:string, report: array<string, mixed>, items: Dzen_RSS_Feed_Item[]}
     */
    public function build_feed_payload(): array
    {
        $candidate_posts = $this->query_service->get_candidate_posts();
        $valid_items = [];
        $report = $this->empty_report();
        $report['generated_at'] = time();
        $report['feed_url'] = Dzen_RSS_Constants::get_feed_url($this->options->get_feed_slug());
        $report['cache_key'] = $this->cache->get_cache_key();
        $report['enabled'] = $this->options->is_enabled();
        $report['debug_mode'] = $this->options->is_debug_mode();
        $report['diagnostics_enabled'] = $this->options->diagnostics_enabled();
        $report['cache_hit'] = false;
        $report['served_from_cache'] = false;
        $report['candidate_count'] = count($candidate_posts);

        foreach ($candidate_posts as $post) {
            if (! $post instanceof WP_Post) {
                continue;
            }

            $item = $this->mapper->map_post_to_feed_item($post);
            $item = apply_filters('dzen_rss_feed_item', $item, $post, $this->options->all());

            if (! $item instanceof Dzen_RSS_Feed_Item) {
                $report['items'][] = [
                    'post_id' => $post->ID,
                    'title' => get_the_title($post),
                    'status' => 'excluded',
                    'reasons' => [
                        [
                            'code' => 'filtered_item',
                            'message' => __('A filter replaced the DTO with an unsupported value.', 'dzen-rss-feed'),
                            'severity' => 'error',
                        ],
                    ],
                    'warnings' => [],
                ];
                continue;
            }

            $item = $this->sanitizer->sanitize($item);
            $result = $this->validator->validate($item, $post);

            $status = $result->is_valid ? 'included' : 'excluded';
            $report['items'][] = [
                'post_id' => $post->ID,
                'post_type' => $post->post_type,
                'title' => $item->title,
                'status' => $status,
                'guid' => $item->guid,
                'link' => $item->link,
                'reasons' => $result->reasons,
                'warnings' => $result->warnings,
            ];

            if ($result->is_valid) {
                $valid_items[] = $item;
            }
        }

        $valid_items = apply_filters('dzen_rss_valid_items', $valid_items, $this->options->all());
        $valid_items = array_values(array_filter($valid_items, static fn($item): bool => $item instanceof Dzen_RSS_Feed_Item));

        $report['valid_count'] = count($valid_items);
        $report['invalid_count'] = max(0, $report['candidate_count'] - $report['valid_count']);
        $report['items'] = array_slice($report['items'], 0, 50);

        if ($report['candidate_count'] === 0) {
            $report['notes'][] = __('No candidate posts were found for the current query.', 'dzen-rss-feed');
        } elseif ($report['valid_count'] === 0) {
            $report['notes'][] = __('Every candidate was excluded by validation or filters.', 'dzen-rss-feed');
        }

        $xml = $this->renderer->render($valid_items, $this->options);

        return [
            'xml' => $xml,
            'report' => $report,
            'items' => $valid_items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function empty_report(): array
    {
        return $this->diagnostics->empty_report();
    }

    private function send_headers(): void
    {
        nocache_headers();
        status_header(200);
        header('Content-Type: application/rss+xml; charset=UTF-8');
    }

    private function respond_not_found(): void
    {
        status_header(404);
        nocache_headers();
        exit;
    }
}
