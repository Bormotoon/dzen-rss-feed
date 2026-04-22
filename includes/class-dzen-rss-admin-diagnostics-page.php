<?php
/**
 * Admin diagnostics screen with cache and feed status controls.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Admin_Diagnostics_Page
{
    public function __construct(
        private readonly Dzen_RSS_Options $options,
        private readonly Dzen_RSS_Cache $cache,
        private readonly Dzen_RSS_Diagnostics $diagnostics,
        private readonly Dzen_RSS_Logger $logger,
        private readonly string $plugin_file
    ) {
    }

    public function add_menu(): void
    {
        add_submenu_page(
            'dzen-rss',
            __('Diagnostics', 'dzen-rss-feed'),
            __('Diagnostics', 'dzen-rss-feed'),
            'manage_options',
            'dzen-rss-diagnostics',
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'dzen-rss-feed'));
        }

        $report = $this->diagnostics->get_report();
        $cache_status = $this->cache->get_status();
        $feed_url = Dzen_RSS_Constants::get_feed_url($this->options->get_feed_slug());
        ?>
        <div class="wrap dzen-rss-admin">
            <h1><?php esc_html_e('Dzen RSS Diagnostics', 'dzen-rss-feed'); ?></h1>
            <?php if (isset($_GET['dzen_rss_notice'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        $notice = sanitize_key((string) wp_unslash((string) $_GET['dzen_rss_notice']));
                        echo esc_html(match ($notice) {
                            'cache-flushed' => __('Cache has been flushed.', 'dzen-rss-feed'),
                            'rewrite-flushed' => __('Rewrite rules have been flushed.', 'dzen-rss-feed'),
                            default => __('Action completed.', 'dzen-rss-feed'),
                        });
                        ?>
                    </p>
                </div>
            <?php endif; ?>
            <p><strong><?php esc_html_e('Feed URL', 'dzen-rss-feed'); ?>:</strong> <a href="<?php echo esc_url($feed_url); ?>" target="_blank" rel="noreferrer noopener"><?php echo esc_html($feed_url); ?></a></p>
            <p><strong><?php esc_html_e('Enabled', 'dzen-rss-feed'); ?>:</strong> <?php echo $this->options->is_enabled() ? esc_html__('Yes', 'dzen-rss-feed') : esc_html__('No', 'dzen-rss-feed'); ?></p>
            <p><strong><?php esc_html_e('Cache', 'dzen-rss-feed'); ?>:</strong> <?php echo $cache_status['enabled'] ? esc_html__('Enabled', 'dzen-rss-feed') : esc_html__('Disabled', 'dzen-rss-feed'); ?> <?php echo esc_html(sprintf('(%s)', $cache_status['key'])); ?></p>
            <p><strong><?php esc_html_e('Last generated', 'dzen-rss-feed'); ?>:</strong> <?php echo esc_html($this->format_timestamp($report['generated_at'] ?? null)); ?></p>

            <div class="dzen-rss-actions">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;">
                    <?php wp_nonce_field('dzen_rss_flush_cache', 'dzen_rss_nonce'); ?>
                    <input type="hidden" name="action" value="dzen_rss_flush_cache" />
                    <?php submit_button(__('Flush cache', 'dzen-rss-feed'), 'secondary', 'submit', false); ?>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                    <?php wp_nonce_field('dzen_rss_flush_rewrite', 'dzen_rss_nonce'); ?>
                    <input type="hidden" name="action" value="dzen_rss_flush_rewrite" />
                    <?php submit_button(__('Flush rewrite rules', 'dzen-rss-feed'), 'secondary', 'submit', false); ?>
                </form>
            </div>

            <h2><?php esc_html_e('Recent candidates', 'dzen-rss-feed'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Post', 'dzen-rss-feed'); ?></th>
                        <th><?php esc_html_e('Status', 'dzen-rss-feed'); ?></th>
                        <th><?php esc_html_e('Reasons', 'dzen-rss-feed'); ?></th>
                        <th><?php esc_html_e('Warnings', 'dzen-rss-feed'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (! empty($report['items']) && is_array($report['items'])) : ?>
                    <?php foreach ($report['items'] as $item) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html((string) ($item['title'] ?? '')); ?></strong><br />
                                <span class="description"><?php echo esc_html('ID #' . (string) ($item['post_id'] ?? '')); ?></span>
                            </td>
                            <td><?php echo esc_html((string) ($item['status'] ?? '')); ?></td>
                            <td><?php echo esc_html($this->flatten_messages((array) ($item['reasons'] ?? []))); ?></td>
                            <td><?php echo esc_html($this->flatten_messages((array) ($item['warnings'] ?? []))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="4"><?php esc_html_e('No diagnostics have been generated yet.', 'dzen-rss-feed'); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e('Notes', 'dzen-rss-feed'); ?></h2>
            <?php if (! empty($report['notes']) && is_array($report['notes'])) : ?>
                <ul>
                    <?php foreach ($report['notes'] as $note) : ?>
                        <li><?php echo esc_html((string) $note); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="description"><?php esc_html_e('No notes recorded.', 'dzen-rss-feed'); ?></p>
            <?php endif; ?>

            <h2><?php esc_html_e('Errors', 'dzen-rss-feed'); ?></h2>
            <?php if (! empty($report['errors']) && is_array($report['errors'])) : ?>
                <ul>
                    <?php foreach ($report['errors'] as $error) : ?>
                        <li><?php echo esc_html(is_array($error) && isset($error['message']) ? (string) $error['message'] : (string) $error); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="description"><?php esc_html_e('No generation errors recorded.', 'dzen-rss-feed'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_flush_cache(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'dzen-rss-feed'));
        }

        check_admin_referer('dzen_rss_flush_cache', 'dzen_rss_nonce');
        $this->cache->invalidate();
        $this->diagnostics->clear();
        wp_safe_redirect(add_query_arg(['page' => 'dzen-rss-diagnostics', 'dzen_rss_notice' => 'cache-flushed'], admin_url('admin.php')));
        exit;
    }

    public function handle_flush_rewrite(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'dzen-rss-feed'));
        }

        check_admin_referer('dzen_rss_flush_rewrite', 'dzen_rss_nonce');
        flush_rewrite_rules(false);
        wp_safe_redirect(add_query_arg(['page' => 'dzen-rss-diagnostics', 'dzen_rss_notice' => 'rewrite-flushed'], admin_url('admin.php')));
        exit;
    }

    private function flatten_messages(array $entries): string
    {
        $messages = [];
        foreach ($entries as $entry) {
            if (is_array($entry) && isset($entry['message'])) {
                $messages[] = (string) $entry['message'];
            } elseif (is_string($entry)) {
                $messages[] = $entry;
            }
        }

        return implode(' | ', $messages);
    }

    private function format_timestamp(mixed $timestamp): string
    {
        if (! is_int($timestamp) && ! ctype_digit((string) $timestamp)) {
            return __('Never', 'dzen-rss-feed');
        }

        return wp_date('Y-m-d H:i:s', (int) $timestamp);
    }
}
