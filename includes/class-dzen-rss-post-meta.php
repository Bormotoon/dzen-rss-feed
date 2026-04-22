<?php
/**
 * Registers post meta, renders the metabox UI, and handles secure saves.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Post_Meta
{
    public const NONCE_ACTION = 'dzen_rss_save_post_meta';
    public const NONCE_FIELD = 'dzen_rss_post_meta_nonce';

    public function __construct(
        private readonly Dzen_RSS_Options $options,
        private readonly Dzen_RSS_Cache $cache,
        private readonly Dzen_RSS_Logger $logger
    ) {
    }

    public function register(): void
    {
        foreach ($this->get_supported_post_types() as $post_type) {
            register_post_meta(
                $post_type,
                Dzen_RSS_Constants::META_INCLUDE,
                [
                    'type' => 'boolean',
                    'single' => true,
                    'show_in_rest' => true,
                    'auth_callback' => static fn() => current_user_can('edit_posts'),
                    'sanitize_callback' => [self::class, 'sanitize_bool'],
                ]
            );

            register_post_meta(
                $post_type,
                Dzen_RSS_Constants::META_EXCLUDE,
                [
                    'type' => 'boolean',
                    'single' => true,
                    'show_in_rest' => true,
                    'auth_callback' => static fn() => current_user_can('edit_posts'),
                    'sanitize_callback' => [self::class, 'sanitize_bool'],
                ]
            );

            foreach ([
                Dzen_RSS_Constants::META_TITLE_OVERRIDE,
                Dzen_RSS_Constants::META_DESCRIPTION_OVERRIDE,
                Dzen_RSS_Constants::META_AUTHOR_OVERRIDE,
                Dzen_RSS_Constants::META_PUB_DATE_OVERRIDE,
            ] as $meta_key) {
                register_post_meta(
                    $post_type,
                    $meta_key,
                    [
                        'type' => 'string',
                        'single' => true,
                        'show_in_rest' => true,
                        'auth_callback' => static fn() => current_user_can('edit_posts'),
                        'sanitize_callback' => static fn(mixed $value): string => sanitize_text_field((string) $value),
                    ]
                );
            }

            foreach ([
                Dzen_RSS_Constants::META_IMAGE_OVERRIDE,
                Dzen_RSS_Constants::META_SOURCE_URL_OVERRIDE,
            ] as $meta_key) {
                register_post_meta(
                    $post_type,
                    $meta_key,
                    [
                        'type' => 'string',
                        'single' => true,
                        'show_in_rest' => true,
                        'auth_callback' => static fn() => current_user_can('edit_posts'),
                        'sanitize_callback' => static fn(mixed $value): string => esc_url_raw((string) $value),
                    ]
                );
            }
        }
    }

    public function add_meta_boxes(): void
    {
        foreach ($this->get_supported_post_types() as $post_type) {
            add_meta_box(
                'dzen_rss_meta_box',
                __('Dzen RSS Feed', 'dzen-rss-feed'),
                [$this, 'render_meta_box'],
                $post_type,
                'side',
                'default'
            );
        }
    }

    public function render_meta_box(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $include = $this->is_truthy(get_post_meta($post->ID, Dzen_RSS_Constants::META_INCLUDE, true));
        $exclude = $this->is_truthy(get_post_meta($post->ID, Dzen_RSS_Constants::META_EXCLUDE, true));
        if ($exclude) {
            $include = false;
        }
        $title_override = (string) get_post_meta($post->ID, Dzen_RSS_Constants::META_TITLE_OVERRIDE, true);
        $description_override = (string) get_post_meta($post->ID, Dzen_RSS_Constants::META_DESCRIPTION_OVERRIDE, true);
        $author_override = (string) get_post_meta($post->ID, Dzen_RSS_Constants::META_AUTHOR_OVERRIDE, true);
        $image_override = (string) get_post_meta($post->ID, Dzen_RSS_Constants::META_IMAGE_OVERRIDE, true);
        $source_url_override = (string) get_post_meta($post->ID, Dzen_RSS_Constants::META_SOURCE_URL_OVERRIDE, true);
        $pub_date_override = (string) get_post_meta($post->ID, Dzen_RSS_Constants::META_PUB_DATE_OVERRIDE, true);
        ?>
        <p>
            <label>
                <input type="checkbox" name="dzen_rss_include" value="1" <?php checked($include); ?> />
                <?php esc_html_e('Include in Dzen RSS', 'dzen-rss-feed'); ?>
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="dzen_rss_exclude" value="1" <?php checked($exclude); ?> />
                <?php esc_html_e('Exclude from Dzen RSS', 'dzen-rss-feed'); ?>
            </label>
        </p>
        <p>
            <label for="dzen_rss_title_override"><?php esc_html_e('Title override', 'dzen-rss-feed'); ?></label>
            <input type="text" class="widefat" id="dzen_rss_title_override" name="dzen_rss_title_override" value="<?php echo esc_attr($title_override); ?>" />
        </p>
        <p>
            <label for="dzen_rss_description_override"><?php esc_html_e('Description override', 'dzen-rss-feed'); ?></label>
            <textarea class="widefat" rows="3" id="dzen_rss_description_override" name="dzen_rss_description_override"><?php echo esc_textarea($description_override); ?></textarea>
        </p>
        <p>
            <label for="dzen_rss_author_override"><?php esc_html_e('Author override', 'dzen-rss-feed'); ?></label>
            <input type="text" class="widefat" id="dzen_rss_author_override" name="dzen_rss_author_override" value="<?php echo esc_attr($author_override); ?>" />
        </p>
        <p>
            <label for="dzen_rss_image_override"><?php esc_html_e('Image override URL', 'dzen-rss-feed'); ?></label>
            <input type="url" class="widefat" id="dzen_rss_image_override" name="dzen_rss_image_override" value="<?php echo esc_attr($image_override); ?>" />
        </p>
        <p>
            <label for="dzen_rss_source_url_override"><?php esc_html_e('Source URL override', 'dzen-rss-feed'); ?></label>
            <input type="url" class="widefat" id="dzen_rss_source_url_override" name="dzen_rss_source_url_override" value="<?php echo esc_attr($source_url_override); ?>" />
        </p>
        <p>
            <label for="dzen_rss_pub_date_override"><?php esc_html_e('Publication date override', 'dzen-rss-feed'); ?></label>
            <input type="text" class="widefat" id="dzen_rss_pub_date_override" name="dzen_rss_pub_date_override" value="<?php echo esc_attr($pub_date_override); ?>" placeholder="Wed, 02 Oct 2002 15:00:00 +0300" />
        </p>
        <p class="description">
            <?php esc_html_e('If both include and exclude are checked, exclusion wins.', 'dzen-rss-feed'); ?>
        </p>
        <?php
    }

    public function save_post_meta(int $post_id, WP_Post $post): void
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (! isset($_POST[self::NONCE_FIELD]) || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_FIELD])), self::NONCE_ACTION)) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $this->save_bool_meta($post_id, Dzen_RSS_Constants::META_INCLUDE, ! empty($_POST['dzen_rss_include']));
        $this->save_bool_meta($post_id, Dzen_RSS_Constants::META_EXCLUDE, ! empty($_POST['dzen_rss_exclude']));

        if ($this->is_truthy(get_post_meta($post_id, Dzen_RSS_Constants::META_EXCLUDE, true))) {
            $this->save_bool_meta($post_id, Dzen_RSS_Constants::META_INCLUDE, false);
        }

        $this->save_text_meta($post_id, Dzen_RSS_Constants::META_TITLE_OVERRIDE, $_POST['dzen_rss_title_override'] ?? '');
        $this->save_text_meta($post_id, Dzen_RSS_Constants::META_DESCRIPTION_OVERRIDE, $_POST['dzen_rss_description_override'] ?? '');
        $this->save_text_meta($post_id, Dzen_RSS_Constants::META_AUTHOR_OVERRIDE, $_POST['dzen_rss_author_override'] ?? '');
        $this->save_url_meta($post_id, Dzen_RSS_Constants::META_IMAGE_OVERRIDE, $_POST['dzen_rss_image_override'] ?? '');
        $this->save_url_meta($post_id, Dzen_RSS_Constants::META_SOURCE_URL_OVERRIDE, $_POST['dzen_rss_source_url_override'] ?? '');
        $this->save_text_meta($post_id, Dzen_RSS_Constants::META_PUB_DATE_OVERRIDE, $_POST['dzen_rss_pub_date_override'] ?? '');

        $this->cache->invalidate();
    }

    /**
     * @return string[]
     */
    private function get_supported_post_types(): array
    {
        $types = get_post_types([
            'public' => true,
            'show_ui' => true,
        ], 'names');

        $types = is_array($types) ? array_values(array_filter(array_map('strval', $types))) : [];
        $types = array_filter($types, static fn(string $type): bool => $type !== 'attachment');

        return $types !== [] ? $types : ['post'];
    }

    private function save_bool_meta(int $post_id, string $meta_key, bool $value): void
    {
        update_post_meta($post_id, $meta_key, $value ? '1' : '0');
    }

    private function save_text_meta(int $post_id, string $meta_key, mixed $value): void
    {
        $value = sanitize_text_field(wp_unslash((string) $value));
        if ($value === '') {
            delete_post_meta($post_id, $meta_key);
            return;
        }

        update_post_meta($post_id, $meta_key, $value);
    }

    private function save_url_meta(int $post_id, string $meta_key, mixed $value): void
    {
        $value = esc_url_raw(wp_unslash((string) $value));
        if ($value === '') {
            delete_post_meta($post_id, $meta_key);
            return;
        }

        update_post_meta($post_id, $meta_key, $value);
    }

    private function is_truthy(mixed $value): bool
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    public static function sanitize_bool(mixed $value): bool
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}
