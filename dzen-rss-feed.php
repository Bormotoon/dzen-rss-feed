<?php
/**
 * Plugin Name: Dzen RSS Feed
 * Description: Generates a dedicated RSS feed for Yandex Dzen crossposting with settings, diagnostics, and XMLWriter-based output.
 * Version: 1.0.0
 * Author: PedObraz
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-dzen-rss-plugin.php';

(new Dzen_RSS_Plugin(__FILE__))->boot();
