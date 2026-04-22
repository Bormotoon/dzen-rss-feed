<?php
/**
 * Persists the latest generation report for the admin diagnostics page.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Diagnostics
{
    /**
     * @return array<string, mixed>
     */
    public function get_report(): array
    {
        $report = get_option(Dzen_RSS_Constants::LAST_DIAGNOSTICS_OPTION, []);
        return is_array($report) ? $report : $this->empty_report();
    }

    /**
     * @param array<string, mixed> $report
     */
    public function save_report(array $report): void
    {
        update_option(Dzen_RSS_Constants::LAST_DIAGNOSTICS_OPTION, $this->normalize_report($report), false);
    }

    public function clear(): void
    {
        delete_option(Dzen_RSS_Constants::LAST_DIAGNOSTICS_OPTION);
    }

    /**
     * @return array<string, mixed>
     */
    public function empty_report(): array
    {
        return [
            'generated_at' => null,
            'served_at' => null,
            'feed_url' => '',
            'cache_key' => '',
            'cache_hit' => false,
            'served_from_cache' => false,
            'enabled' => false,
            'debug_mode' => false,
            'diagnostics_enabled' => false,
            'candidate_count' => 0,
            'valid_count' => 0,
            'invalid_count' => 0,
            'items' => [],
            'errors' => [],
            'notes' => [],
        ];
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private function normalize_report(array $report): array
    {
        return wp_parse_args($report, $this->empty_report());
    }
}

