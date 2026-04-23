<?php
/**
 * Central hook registration for the plugin lifecycle.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Hooks
{
    public function __construct(
        private readonly string $plugin_file,
        private readonly Dzen_RSS_Options $options,
        private readonly Dzen_RSS_Feed_Controller $controller,
        private readonly Dzen_RSS_Post_Meta $post_meta,
        private readonly Dzen_RSS_Settings_Page $settings_page,
        private readonly Dzen_RSS_Admin_Diagnostics_Page $diagnostics_page,
        private readonly Dzen_RSS_Cache $cache,
        private readonly Dzen_RSS_Logger $logger
    ) {
    }

    public function register(): void
    {
        register_activation_hook($this->plugin_file, [$this, 'activate']);
        register_deactivation_hook($this->plugin_file, [$this, 'deactivate']);

        add_action('init', [$this, 'register_feed_endpoints'], 5);
        add_action('init', [$this->post_meta, 'register'], 10);
        add_action('add_meta_boxes', [$this->post_meta, 'add_meta_boxes']);
        add_action('save_post', [$this->post_meta, 'save_post_meta'], 10, 2);

        add_action('admin_menu', [$this->settings_page, 'add_menu']);
        add_action('admin_menu', [$this->diagnostics_page, 'add_menu']);
        add_action('admin_init', [$this->settings_page, 'register_settings']);

        add_action('admin_post_dzen_rss_flush_cache', [$this->diagnostics_page, 'handle_flush_cache']);
        add_action('admin_post_dzen_rss_flush_rewrite', [$this->diagnostics_page, 'handle_flush_rewrite']);

        add_action('updated_option', [$this, 'handle_option_update'], 10, 3);
        add_action('save_post', [$this, 'invalidate_on_post_save'], 20, 2);
        add_action('deleted_post', [$this, 'invalidate_cache']);
        add_action('set_object_terms', [$this, 'invalidate_on_term_change'], 20, 6);
        add_action('updated_post_meta', [$this, 'invalidate_on_meta_change'], 20, 4);
        add_action('added_post_meta', [$this, 'invalidate_on_meta_change'], 20, 4);
        add_action('deleted_post_meta', [$this, 'invalidate_on_meta_change'], 20, 4);

        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        }
    }

    public function activate(): void
    {
        $this->post_meta->register();
        $this->register_feed_endpoints();
        $this->cache->invalidate();
        flush_rewrite_rules(false);
    }

    public function deactivate(): void
    {
        flush_rewrite_rules(false);
    }

    public function register_feed_endpoints(): void
    {
        foreach ($this->options->get_feed_slugs() as $slug) {
            add_feed($slug, [$this->controller, 'serve_feed']);
        }
    }

    /**
     * @param string $option
     * @param mixed $old_value
     * @param mixed $value
     */
    public function handle_option_update(string $option, mixed $old_value, mixed $value): void
    {
        if ($option !== Dzen_RSS_Constants::OPTION_NAME) {
            return;
        }

        $old_slug = is_array($old_value) ? sanitize_title((string) ($old_value['feed_slug'] ?? '')) : '';
        $new_slug = is_array($value) ? sanitize_title((string) ($value['feed_slug'] ?? '')) : '';

        if ($old_slug !== '' && $new_slug !== '' && $old_slug !== $new_slug) {
            flush_rewrite_rules(false);
        }

        $this->cache->invalidate();
    }

    public function invalidate_on_post_save(int $post_id, WP_Post $post): void
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (! in_array($post->post_type, $this->options->get_allowed_post_types(), true)) {
            return;
        }

        $this->cache->invalidate();
    }

    public function invalidate_on_term_change(int $object_id, array $terms, array $tt_ids, string $taxonomy, bool $append, array $old_tt_ids): void
    {
        $post = get_post($object_id);
        if ($post instanceof WP_Post && in_array($post->post_type, $this->options->get_allowed_post_types(), true)) {
            $this->cache->invalidate();
        }
    }

    public function invalidate_on_meta_change(int $meta_id, int $post_id, string $meta_key, mixed $meta_value): void
    {
        if (! str_starts_with($meta_key, Dzen_RSS_Constants::META_PREFIX)) {
            return;
        }

        $post = get_post($post_id);
        if ($post instanceof WP_Post && in_array($post->post_type, $this->options->get_allowed_post_types(), true)) {
            $this->cache->invalidate();
        }
    }

    public function invalidate_cache(): void
    {
        $this->cache->invalidate();
    }

    public function enqueue_admin_assets(string $hook_suffix): void
    {
        if (! in_array($hook_suffix, ['toplevel_page_dzen-rss', 'dzen-rss_page_dzen-rss-diagnostics'], true)) {
            return;
        }

        $base = plugins_url('', $this->plugin_file);
        wp_enqueue_style('dzen-rss-admin', $base . '/assets/admin.css', [], Dzen_RSS_Constants::VERSION);
        wp_enqueue_script('dzen-rss-admin', $base . '/assets/admin.js', ['jquery'], Dzen_RSS_Constants::VERSION, true);
    }
}

