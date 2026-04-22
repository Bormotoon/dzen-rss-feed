<?php
/**
 * Small logging facade that keeps WP error_log usage centralized.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Logger
{
    private bool $debug_enabled;
    private string $prefix = '[Dzen RSS]';

    public function __construct(bool $debug_enabled = false)
    {
        $this->debug_enabled = $debug_enabled;
    }

    public function debug(string $message, array $context = []): void
    {
        if ($this->debug_enabled) {
            $this->log('debug', $message, $context);
        }
    }

    public function info(string $message, array $context = []): void
    {
        if ($this->debug_enabled) {
            $this->log('info', $message, $context);
        }
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $payload = $context === [] ? '' : ' ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        error_log($this->prefix . ' ' . strtoupper($level) . ': ' . $message . $payload);
    }
}

