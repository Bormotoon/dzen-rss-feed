<?php
/**
 * Transient-based cache layer for the rendered XML feed.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Cache
{
    public function __construct(private readonly Dzen_RSS_Options $options)
    {
    }

    public function is_enabled(): bool
    {
        return $this->options->get_cache_ttl() > 0 && ! $this->options->is_debug_mode();
    }

    public function get_cache_key(): string
    {
        $version = (int) get_option(Dzen_RSS_Constants::CACHE_VERSION_OPTION, 1);
        $signature = $this->options->get_cache_signature();

        return 'dzen_rss_feed_' . get_current_blog_id() . '_' . $version . '_' . $signature;
    }

    public function get_xml(): ?string
    {
        if (! $this->is_enabled()) {
            return null;
        }

        $payload = get_transient($this->get_cache_key());
        if (is_string($payload)) {
            return $payload;
        }

        if (is_array($payload) && isset($payload['xml']) && is_string($payload['xml'])) {
            return $payload['xml'];
        }

        return null;
    }

    public function get_payload(): ?array
    {
        if (! $this->is_enabled()) {
            return null;
        }

        $payload = get_transient($this->get_cache_key());
        return is_array($payload) ? $payload : null;
    }

    public function set_xml(string $xml): void
    {
        if (! $this->is_enabled()) {
            return;
        }

        set_transient(
            $this->get_cache_key(),
            [
                'xml' => $xml,
                'generated_at' => time(),
                'signature' => $this->options->get_cache_signature(),
            ],
            $this->options->get_cache_ttl()
        );
    }

    public function invalidate(): void
    {
        $version = (int) get_option(Dzen_RSS_Constants::CACHE_VERSION_OPTION, 1);
        update_option(Dzen_RSS_Constants::CACHE_VERSION_OPTION, $version + 1, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function get_status(): array
    {
        $payload = $this->get_payload();

        return [
            'enabled' => $this->is_enabled(),
            'ttl' => $this->options->get_cache_ttl(),
            'key' => $this->get_cache_key(),
            'hit' => is_array($payload),
            'generated_at' => is_array($payload) ? ($payload['generated_at'] ?? null) : null,
        ];
    }
}

