<?php
/**
 * Composition root for the Dzen RSS plugin.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-dzen-rss-constants.php';
require_once __DIR__ . '/class-dzen-rss-options.php';
require_once __DIR__ . '/class-dzen-rss-feed-item.php';
require_once __DIR__ . '/class-dzen-rss-validation-result.php';
require_once __DIR__ . '/class-dzen-rss-logger.php';
require_once __DIR__ . '/class-dzen-rss-diagnostics.php';
require_once __DIR__ . '/class-dzen-rss-html-normalizer.php';
require_once __DIR__ . '/class-dzen-rss-content-sanitizer.php';
require_once __DIR__ . '/class-dzen-rss-image-resolver.php';
require_once __DIR__ . '/class-dzen-rss-validator.php';
require_once __DIR__ . '/class-dzen-rss-mapper.php';
require_once __DIR__ . '/class-dzen-rss-query-service.php';
require_once __DIR__ . '/class-dzen-rss-renderer.php';
require_once __DIR__ . '/class-dzen-rss-cache.php';
require_once __DIR__ . '/class-dzen-rss-feed-controller.php';
require_once __DIR__ . '/class-dzen-rss-post-meta.php';
require_once __DIR__ . '/class-dzen-rss-settings-page.php';
require_once __DIR__ . '/class-dzen-rss-admin-diagnostics-page.php';
require_once __DIR__ . '/class-dzen-rss-hooks.php';

final class Dzen_RSS_Plugin
{
    private readonly string $plugin_file;
    private Dzen_RSS_Options $options;
    private Dzen_RSS_Logger $logger;
    private Dzen_RSS_Cache $cache;
    private Dzen_RSS_Diagnostics $diagnostics;
    private Dzen_RSS_Hooks $hooks;

    public function __construct(string $plugin_file)
    {
        $this->plugin_file = $plugin_file;
    }

    public function boot(): void
    {
        $this->options = new Dzen_RSS_Options();
        $this->logger = new Dzen_RSS_Logger($this->options->is_debug_mode());
        $this->cache = new Dzen_RSS_Cache($this->options);
        $this->diagnostics = new Dzen_RSS_Diagnostics();

        $image_resolver = new Dzen_RSS_Image_Resolver();
        $mapper = new Dzen_RSS_Mapper($this->options, $image_resolver);
        $query_service = new Dzen_RSS_Query_Service($this->options);
        $normalizer = new Dzen_RSS_HTML_Normalizer();
        $sanitizer = new Dzen_RSS_Content_Sanitizer($this->options, $normalizer, $this->logger);
        $validator = new Dzen_RSS_Validator($this->options);
        $renderer = new Dzen_RSS_Renderer();
        $post_meta = new Dzen_RSS_Post_Meta($this->options, $this->cache, $this->logger);
        $settings_page = new Dzen_RSS_Settings_Page($this->options, $this->cache, $this->diagnostics, $this->logger, $this->plugin_file);
        $diagnostics_page = new Dzen_RSS_Admin_Diagnostics_Page($this->options, $this->cache, $this->diagnostics, $this->logger, $this->plugin_file);

        $controller = new Dzen_RSS_Feed_Controller(
            $this->options,
            $query_service,
            $mapper,
            $sanitizer,
            $validator,
            $renderer,
            $this->cache,
            $this->diagnostics,
            $this->logger
        );

        $this->hooks = new Dzen_RSS_Hooks(
            $this->plugin_file,
            $this->options,
            $controller,
            $post_meta,
            $settings_page,
            $diagnostics_page,
            $this->cache,
            $this->logger
        );

        $this->hooks->register();
    }
}
