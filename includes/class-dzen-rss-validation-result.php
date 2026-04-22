<?php
/**
 * Validation verdict object with reasons and warnings for diagnostics.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Validation_Result
{
    public bool $is_valid;

    /**
     * @var array<int, array<string, string>>
     */
    public array $reasons = [];

    /**
     * @var array<int, array<string, string>>
     */
    public array $warnings = [];

    public function __construct(bool $is_valid = true)
    {
        $this->is_valid = $is_valid;
    }

    public function add_reason(string $code, string $message, string $severity = 'error'): void
    {
        $this->is_valid = false;
        $this->reasons[] = [
            'code' => $code,
            'message' => $message,
            'severity' => $severity,
        ];
    }

    public function add_warning(string $code, string $message, string $severity = 'warning'): void
    {
        $this->warnings[] = [
            'code' => $code,
            'message' => $message,
            'severity' => $severity,
        ];
    }
}

