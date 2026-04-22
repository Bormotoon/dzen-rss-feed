<?php
/**
 * Settings API-backed admin screen for the plugin configuration.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Settings_Page
{
    public function __construct(
        private readonly Dzen_RSS_Options $options,
        private readonly Dzen_RSS_Cache $cache,
        private readonly Dzen_RSS_Diagnostics $diagnostics,
        private readonly Dzen_RSS_Logger $logger,
        private readonly string $plugin_file
    ) {
    }

    public function register_settings(): void
    {
        register_setting(
            'dzen_rss_settings_group',
            Dzen_RSS_Constants::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this->options, 'sanitize_options'],
                'default' => Dzen_RSS_Constants::default_options(),
            ]
        );

        add_settings_section(
            'dzen_rss_main_section',
            __('Feed Settings', 'dzen-rss-feed'),
            static function (): void {
                echo '<p>' . esc_html__('Configure which posts are exported to Dzen and how their content is normalized.', 'dzen-rss-feed') . '</p>';
            },
            'dzen-rss'
        );
    }

    public function add_menu(): void
    {
        add_menu_page(
            __('Dzen RSS', 'dzen-rss-feed'),
            __('Dzen RSS', 'dzen-rss-feed'),
            'manage_options',
            'dzen-rss',
            [$this, 'render_page'],
            'dashicons-rss',
            58
        );

        add_submenu_page(
            'dzen-rss',
            __('Settings', 'dzen-rss-feed'),
            __('Settings', 'dzen-rss-feed'),
            'manage_options',
            'dzen-rss',
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'dzen-rss-feed'));
        }

        $options = $this->options->all();
        $feed_url = Dzen_RSS_Constants::get_feed_url($this->options->get_feed_slug());
        $aliases = $this->options->get_feed_slug_aliases();
        ?>
        <div class="wrap dzen-rss-admin">
            <h1><?php esc_html_e('Dzen RSS Settings', 'dzen-rss-feed'); ?></h1>
            <p class="description">
                <?php echo esc_html(sprintf(__('Current feed URL: %s', 'dzen-rss-feed'), $feed_url)); ?>
            </p>
            <?php if ($aliases !== []) : ?>
                <p class="description">
                    <?php echo esc_html(sprintf(__('Legacy aliases: %s', 'dzen-rss-feed'), implode(', ', $aliases))); ?>
                </p>
            <?php endif; ?>
            <p class="description">
                <?php esc_html_e('Dzen publication directives are optional. Set any field to Auto to omit the corresponding category tag and let Dzen choose its default behavior.', 'dzen-rss-feed'); ?>
            </p>
            <form method="post" action="options.php">
                <?php settings_fields('dzen_rss_settings_group'); ?>
                <?php settings_errors(); ?>
                <?php do_settings_sections('dzen-rss'); ?>
                <table class="form-table" role="presentation">
                    <?php $this->render_select_field('publication_state', __('Publication mode', 'dzen-rss-feed'), $options['publication_state'], $this->publication_labels('state')); ?>
                    <?php $this->render_select_field('publication_format', __('Publication format', 'dzen-rss-feed'), $options['publication_format'], $this->publication_labels('format')); ?>
                    <?php $this->render_select_field('publication_index', __('Indexing', 'dzen-rss-feed'), $options['publication_index'], $this->publication_labels('index')); ?>
                    <?php $this->render_select_field('publication_comments', __('Comments', 'dzen-rss-feed'), $options['publication_comments'], $this->publication_labels('comments')); ?>
                    <?php $this->render_bool_field('enabled', __('Enable feed', 'dzen-rss-feed'), $options['enabled']); ?>
                    <?php $this->render_text_field('feed_slug', __('Feed slug', 'dzen-rss-feed'), $options['feed_slug'], __('dzen', 'dzen-rss-feed')); ?>
                    <?php $this->render_post_types_field($options['allowed_post_types']); ?>
                    <?php $this->render_number_field('limit', __('Item limit', 'dzen-rss-feed'), $options['limit'], 1, Dzen_RSS_Constants::MAX_QUERY_LIMIT); ?>
                    <?php $this->render_select_field('inclusion_mode', __('Inclusion mode', 'dzen-rss-feed'), $options['inclusion_mode'], [
                        Dzen_RSS_Constants::INCLUSION_ALL => __('All eligible posts', 'dzen-rss-feed'),
                        Dzen_RSS_Constants::INCLUSION_EXPLICIT => __('Only explicitly included posts', 'dzen-rss-feed'),
                    ]); ?>
                    <?php $this->render_select_field('author_source', __('Author source', 'dzen-rss-feed'), $options['author_source'], $this->source_labels('author')); ?>
                    <?php $this->render_select_field('summary_source', __('Summary source', 'dzen-rss-feed'), $options['summary_source'], $this->source_labels('summary')); ?>
                    <?php $this->render_select_field('content_source', __('Full content source', 'dzen-rss-feed'), $options['content_source'], $this->source_labels('content')); ?>
                    <?php $this->render_select_field('image_source', __('Image source', 'dzen-rss-feed'), $options['image_source'], $this->source_labels('image')); ?>
                    <?php $this->render_textarea_field('excluded_taxonomies', __('Excluded taxonomies', 'dzen-rss-feed'), implode(', ', $this->normalize_string_list($options['excluded_taxonomies'] ?? [])), __('category, post_tag, pedobraz_region', 'dzen-rss-feed')); ?>
                    <?php $this->render_number_field('minimum_content_length', __('Minimum content length', 'dzen-rss-feed'), $options['minimum_content_length'], 0, 10000); ?>
                    <?php $this->render_bool_field('debug_mode', __('Debug mode', 'dzen-rss-feed'), $options['debug_mode']); ?>
                    <?php $this->render_bool_field('diagnostics_enabled', __('Enable diagnostics', 'dzen-rss-feed'), $options['diagnostics_enabled']); ?>
                    <?php $this->render_number_field('cache_ttl', __('Cache TTL, seconds', 'dzen-rss-feed'), $options['cache_ttl'], 0, DAY_IN_SECONDS); ?>
                    <?php $this->render_select_field('sanitation_mode', __('Sanitation mode', 'dzen-rss-feed'), $options['sanitation_mode'], [
                        Dzen_RSS_Constants::SANITATION_CONSERVATIVE => __('Conservative', 'dzen-rss-feed'),
                        Dzen_RSS_Constants::SANITATION_STRICT => __('Strict', 'dzen-rss-feed'),
                    ]); ?>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * @param string[] $post_types
     */
    private function render_post_types_field(array $post_types): void
    {
        $public_types = get_post_types([
            'public' => true,
            'show_ui' => true,
        ], 'objects');

        if (! is_array($public_types) || $public_types === []) {
            return;
        }

        echo '<tr>';
            echo '<th scope="row">' . esc_html__('Allowed post types', 'dzen-rss-feed') . '</th>';
            echo '<td>';
            foreach ($public_types as $post_type) {
                if (! $post_type instanceof WP_Post_Type || $post_type->name === 'attachment') {
                    continue;
                }
                printf(
                    '<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label>',
                    esc_attr(Dzen_RSS_Constants::OPTION_NAME . '[allowed_post_types]'),
                    esc_attr($post_type->name),
                    checked(in_array($post_type->name, $post_types, true), true, false),
                    esc_html($post_type->labels->name)
                );
            }
        echo '<p class="description">' . esc_html__('Only public post types should usually be selected.', 'dzen-rss-feed') . '</p>';
        echo '</td>';
        echo '</tr>';
    }

    private function render_bool_field(string $key, string $label, mixed $value): void
    {
        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th>';
        echo '<td><label><input type="checkbox" id="' . esc_attr($key) . '" name="' . esc_attr(Dzen_RSS_Constants::OPTION_NAME . '[' . $key . ']') . '" value="1" ' . checked((bool) $value, true, false) . ' /> ' . esc_html__('Enabled', 'dzen-rss-feed') . '</label></td>';
        echo '</tr>';
    }

    private function render_text_field(string $key, string $label, mixed $value, string $placeholder = ''): void
    {
        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th>';
        echo '<td><input type="text" class="regular-text" id="' . esc_attr($key) . '" name="' . esc_attr(Dzen_RSS_Constants::OPTION_NAME . '[' . $key . ']') . '" value="' . esc_attr((string) $value) . '" placeholder="' . esc_attr($placeholder) . '" /></td>';
        echo '</tr>';
    }

    private function render_number_field(string $key, string $label, mixed $value, int $min, int $max): void
    {
        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th>';
        echo '<td><input type="number" class="small-text" id="' . esc_attr($key) . '" name="' . esc_attr(Dzen_RSS_Constants::OPTION_NAME . '[' . $key . ']') . '" value="' . esc_attr((string) $value) . '" min="' . esc_attr((string) $min) . '" max="' . esc_attr((string) $max) . '" /></td>';
        echo '</tr>';
    }

    /**
     * @param array<string, string> $choices
     */
    private function render_select_field(string $key, string $label, mixed $value, array $choices): void
    {
        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th>';
        echo '<td><select id="' . esc_attr($key) . '" name="' . esc_attr(Dzen_RSS_Constants::OPTION_NAME . '[' . $key . ']') . '">';
        foreach ($choices as $choice_value => $choice_label) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr($choice_value),
                selected((string) $value, (string) $choice_value, false),
                esc_html($choice_label)
            );
        }
        echo '</select></td>';
        echo '</tr>';
    }

    private function render_textarea_field(string $key, string $label, mixed $value, string $placeholder = ''): void
    {
        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th>';
        echo '<td><textarea class="large-text" rows="3" id="' . esc_attr($key) . '" name="' . esc_attr(Dzen_RSS_Constants::OPTION_NAME . '[' . $key . ']') . '" placeholder="' . esc_attr($placeholder) . '">' . esc_textarea((string) $value) . '</textarea></td>';
        echo '</tr>';
    }

    /**
     * @return array<string, string>
     */
    private function source_labels(string $kind): array
    {
        return match ($kind) {
            'author' => [
                Dzen_RSS_Constants::SOURCE_AUTHOR_POST => __('Post author', 'dzen-rss-feed'),
                Dzen_RSS_Constants::SOURCE_AUTHOR_META => __('Author override meta', 'dzen-rss-feed'),
                Dzen_RSS_Constants::SOURCE_AUTHOR_SITE => __('Site name', 'dzen-rss-feed'),
                Dzen_RSS_Constants::SOURCE_AUTHOR_NONE => __('Hide author', 'dzen-rss-feed'),
            ],
            'summary' => [
                Dzen_RSS_Constants::SOURCE_SUMMARY_META => __('Summary override meta', 'dzen-rss-feed'),
                Dzen_RSS_Constants::SOURCE_SUMMARY_EXCERPT => __('Post excerpt', 'dzen-rss-feed'),
                Dzen_RSS_Constants::SOURCE_SUMMARY_PARAGRAPH => __('First paragraph', 'dzen-rss-feed'),
                Dzen_RSS_Constants::SOURCE_SUMMARY_NONE => __('Hide description', 'dzen-rss-feed'),
            ],
            'content' => [
                Dzen_RSS_Constants::SOURCE_CONTENT_RENDERED => __('Rendered content', 'dzen-rss-feed'),
                Dzen_RSS_Constants::SOURCE_CONTENT_RAW => __('Raw post content', 'dzen-rss-feed'),
            ],
            'image' => [
                Dzen_RSS_Constants::SOURCE_IMAGE_META => __('Image override meta', 'dzen-rss-feed'),
                Dzen_RSS_Constants::SOURCE_IMAGE_FEATURED => __('Featured image', 'dzen-rss-feed'),
                Dzen_RSS_Constants::SOURCE_IMAGE_CONTENT => __('First image in content', 'dzen-rss-feed'),
                Dzen_RSS_Constants::SOURCE_IMAGE_NONE => __('No image', 'dzen-rss-feed'),
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function publication_labels(string $kind): array
    {
        return match ($kind) {
            'state' => [
                Dzen_RSS_Constants::PUBLICATION_AUTO => __('Auto publish', 'dzen-rss-feed'),
                Dzen_RSS_Constants::PUBLICATION_NATIVE_DRAFT => __('Save as draft in Dzen', 'dzen-rss-feed'),
            ],
            'format' => [
                Dzen_RSS_Constants::PUBLICATION_AUTO => __('Auto-detect', 'dzen-rss-feed'),
                Dzen_RSS_Constants::PUBLICATION_FORMAT_ARTICLE => __('Article', 'dzen-rss-feed'),
                Dzen_RSS_Constants::PUBLICATION_FORMAT_POST => __('Post', 'dzen-rss-feed'),
            ],
            'index' => [
                Dzen_RSS_Constants::PUBLICATION_AUTO => __('Auto', 'dzen-rss-feed'),
                Dzen_RSS_Constants::PUBLICATION_INDEX => __('Index', 'dzen-rss-feed'),
                Dzen_RSS_Constants::PUBLICATION_NOINDEX => __('Noindex', 'dzen-rss-feed'),
            ],
            'comments' => [
                Dzen_RSS_Constants::PUBLICATION_AUTO => __('Auto', 'dzen-rss-feed'),
                Dzen_RSS_Constants::PUBLICATION_COMMENT_ALL => __('All users', 'dzen-rss-feed'),
                Dzen_RSS_Constants::PUBLICATION_COMMENT_SUBSCRIBERS => __('Subscribers only', 'dzen-rss-feed'),
                Dzen_RSS_Constants::PUBLICATION_COMMENT_NONE => __('Comments off', 'dzen-rss-feed'),
            ],
            default => [],
        };
    }

    /**
     * @return string[]
     */
    private function normalize_string_list(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,\n\r]+/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn(mixed $item): string => sanitize_key((string) $item),
            $value
        )));
    }
}
