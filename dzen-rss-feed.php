<?php
/**
 * Plugin Name: Dzen RSS Feed
 * Description: Generates a dedicated RSS feed for Yandex Dzen crossposting with settings, diagnostics, and XMLWriter-based output.
 * Version: 1.0.0
 * Author: PedObraz
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-dzen-rss-plugin.php';

(new Dzen_RSS_Plugin(__FILE__))->boot();
