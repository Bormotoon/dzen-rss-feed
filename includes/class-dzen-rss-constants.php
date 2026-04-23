<?php
/**
 * Centralized constants and shared configuration for the Dzen RSS plugin.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Constants
{
    public const VERSION = '1.0.4';

    public const OPTION_NAME = 'dzen_rss_options';
    public const CACHE_VERSION_OPTION = 'dzen_rss_cache_version';
    public const PLUGIN_VERSION_OPTION = 'dzen_rss_plugin_version';
    public const LAST_DIAGNOSTICS_OPTION = 'dzen_rss_last_diagnostics';

    public const META_PREFIX = '_dzen_rss_';
    public const META_INCLUDE = '_dzen_rss_include';
    public const META_EXCLUDE = '_dzen_rss_exclude';
    public const META_TITLE_OVERRIDE = '_dzen_rss_title_override';
    public const META_DESCRIPTION_OVERRIDE = '_dzen_rss_description_override';
    public const META_AUTHOR_OVERRIDE = '_dzen_rss_author_override';
    public const META_IMAGE_OVERRIDE = '_dzen_rss_image_override';
    public const META_SOURCE_URL_OVERRIDE = '_dzen_rss_source_url_override';
    public const META_PUB_DATE_OVERRIDE = '_dzen_rss_pub_date_override';

    public const DEFAULT_FEED_SLUG = 'dzen';
    public const DEFAULT_CACHE_TTL = 1800;
    public const DEFAULT_MIN_CONTENT_LENGTH = 300;
    public const DEFAULT_LIMIT = 20;
    public const MAX_QUERY_LIMIT = 500;

    public const PUBLICATION_AUTO = 'auto';
    public const PUBLICATION_NATIVE_DRAFT = 'native-draft';
    public const PUBLICATION_FORMAT_ARTICLE = 'format-article';
    public const PUBLICATION_FORMAT_POST = 'format-post';
    public const PUBLICATION_INDEX = 'index';
    public const PUBLICATION_NOINDEX = 'noindex';
    public const PUBLICATION_COMMENT_ALL = 'comment-all';
    public const PUBLICATION_COMMENT_SUBSCRIBERS = 'comment-subscribers';
    public const PUBLICATION_COMMENT_NONE = 'comment-none';

    public const SANITATION_CONSERVATIVE = 'conservative';
    public const SANITATION_STRICT = 'strict';

    public const SOURCE_AUTHOR_POST = 'post_author';
    public const SOURCE_AUTHOR_META = 'meta_override';
    public const SOURCE_AUTHOR_SITE = 'site_name';
    public const SOURCE_AUTHOR_NONE = 'none';

    public const SOURCE_SUMMARY_META = 'meta_override';
    public const SOURCE_SUMMARY_EXCERPT = 'post_excerpt';
    public const SOURCE_SUMMARY_PARAGRAPH = 'first_paragraph';
    public const SOURCE_SUMMARY_NONE = 'none';

    public const SOURCE_CONTENT_RENDERED = 'rendered_content';
    public const SOURCE_CONTENT_RAW = 'raw_content';

    public const SOURCE_IMAGE_META = 'meta_override';
    public const SOURCE_IMAGE_FEATURED = 'featured_image';
    public const SOURCE_IMAGE_CONTENT = 'first_content_image';
    public const SOURCE_IMAGE_NONE = 'none';

    public const INCLUSION_ALL = 'all';
    public const INCLUSION_EXPLICIT = 'explicit';

    /**
     * Default option values used to bootstrap and sanitize the plugin state.
     *
     * @return array<string, mixed>
     */
    public static function default_options(): array
    {
        return [
            'enabled' => 1,
            'feed_slug' => self::DEFAULT_FEED_SLUG,
            'feed_slug_aliases' => [],
            'allowed_post_types' => ['post'],
            'limit' => self::DEFAULT_LIMIT,
            'publication_state' => self::PUBLICATION_AUTO,
            'publication_format' => self::PUBLICATION_FORMAT_ARTICLE,
            'publication_index' => self::PUBLICATION_INDEX,
            'publication_comments' => self::PUBLICATION_COMMENT_NONE,
            'inclusion_mode' => self::INCLUSION_ALL,
            'author_source' => self::SOURCE_AUTHOR_POST,
            'summary_source' => self::SOURCE_SUMMARY_EXCERPT,
            'content_source' => self::SOURCE_CONTENT_RENDERED,
            'image_source' => self::SOURCE_IMAGE_FEATURED,
            'excluded_taxonomies' => [],
            'minimum_content_length' => self::DEFAULT_MIN_CONTENT_LENGTH,
            'debug_mode' => 0,
            'diagnostics_enabled' => 1,
            'cache_ttl' => self::DEFAULT_CACHE_TTL,
            'sanitation_mode' => self::SANITATION_CONSERVATIVE,
        ];
    }

    /**
     * Publication directives that are safe defaults for a production feed.
     *
     * @return string[]
     */
    public static function default_publication_directives(): array
    {
        return [self::PUBLICATION_FORMAT_ARTICLE, self::PUBLICATION_INDEX, self::PUBLICATION_COMMENT_NONE];
    }

    /**
     * Publication directives that are accepted by the Dzen RSS format.
     *
     * @return string[]
     */
    public static function allowed_publication_directives(): array
    {
        return [
            self::PUBLICATION_NATIVE_DRAFT,
            self::PUBLICATION_FORMAT_ARTICLE,
            self::PUBLICATION_FORMAT_POST,
            self::PUBLICATION_INDEX,
            self::PUBLICATION_NOINDEX,
            self::PUBLICATION_COMMENT_ALL,
            self::PUBLICATION_COMMENT_SUBSCRIBERS,
            self::PUBLICATION_COMMENT_NONE,
        ];
    }

    /**
     * Dzen publication option values exposed in the admin UI.
     *
     * @return array<string, string[]>
     */
    public static function publication_directive_options(): array
    {
        return [
            'state' => [
                self::PUBLICATION_AUTO,
                self::PUBLICATION_NATIVE_DRAFT,
            ],
            'format' => [
                self::PUBLICATION_AUTO,
                self::PUBLICATION_FORMAT_ARTICLE,
                self::PUBLICATION_FORMAT_POST,
            ],
            'index' => [
                self::PUBLICATION_AUTO,
                self::PUBLICATION_INDEX,
                self::PUBLICATION_NOINDEX,
            ],
            'comments' => [
                self::PUBLICATION_AUTO,
                self::PUBLICATION_COMMENT_ALL,
                self::PUBLICATION_COMMENT_SUBSCRIBERS,
                self::PUBLICATION_COMMENT_NONE,
            ],
        ];
    }

    /**
     * Allowed image MIME types for Dzen enclosure output.
     *
     * @return string[]
     */
    public static function allowed_image_mime_types(): array
    {
        return [
            'image/jpeg',
            'image/gif',
            'image/png',
            'image/webp',
        ];
    }

    /**
     * Normalize common image MIME aliases to canonical values.
     */
    public static function normalize_image_mime_type(string $mime_type): string
    {
        $mime_type = strtolower(trim($mime_type));

        return match ($mime_type) {
            'image/jpg', 'image/pjpeg' => 'image/jpeg',
            'image/x-png' => 'image/png',
            default => $mime_type,
        };
    }

    /**
     * Allowed source strategies for the settings UI and sanitizer.
     *
     * @return array<string, string[]>
     */
    public static function source_strategy_options(): array
    {
        return [
            'author' => [
                self::SOURCE_AUTHOR_POST,
                self::SOURCE_AUTHOR_META,
                self::SOURCE_AUTHOR_SITE,
                self::SOURCE_AUTHOR_NONE,
            ],
            'summary' => [
                self::SOURCE_SUMMARY_META,
                self::SOURCE_SUMMARY_EXCERPT,
                self::SOURCE_SUMMARY_PARAGRAPH,
                self::SOURCE_SUMMARY_NONE,
            ],
            'content' => [
                self::SOURCE_CONTENT_RENDERED,
                self::SOURCE_CONTENT_RAW,
            ],
            'image' => [
                self::SOURCE_IMAGE_META,
                self::SOURCE_IMAGE_FEATURED,
                self::SOURCE_IMAGE_CONTENT,
                self::SOURCE_IMAGE_NONE,
            ],
        ];
    }

    /**
     * Returns the RSS language code in a compact Dzen-friendly form.
     */
    public static function get_language_code(): string
    {
        $locale = (string) get_locale();
        $locale = strtolower($locale);
        if ($locale === '') {
            return 'ru';
        }

        $parts = preg_split('/[_-]/', $locale);
        $code = (string) ($parts[0] ?? $locale);
        $code = preg_replace('/[^a-z]/', '', $code) ?: 'ru';

        return $code;
    }

    /**
     * Build the public feed URL for the current slug.
     */
    public static function get_feed_url(string $slug): string
    {
        if (function_exists('get_feed_link')) {
            return (string) get_feed_link($slug);
        }

        return add_query_arg('feed', $slug, home_url('/'));
    }

    /**
     * Allowed protocols for feed content sanitation.
     *
     * @return string[]
     */
    public static function allowed_protocols(): array
    {
        return ['http', 'https', 'mailto', 'tel'];
    }

    /**
     * Returns the allowed HTML whitelist for the selected sanitation mode.
     *
     * @return array<string, array<string, bool>>
     */
    public static function allowed_html(string $mode): array
    {
        $allowed = [
            'p' => [
                'class' => true,
                'id' => true,
            ],
            'br' => [],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'u' => [],
            's' => [],
            'a' => [
                'href' => true,
                'title' => true,
                'rel' => true,
                'target' => true,
            ],
            'blockquote' => [
                'cite' => true,
                'class' => true,
            ],
            'ul' => [
                'class' => true,
            ],
            'ol' => [
                'class' => true,
            ],
            'li' => [
                'class' => true,
            ],
            'h1' => [
                'id' => true,
                'class' => true,
            ],
            'h2' => [
                'id' => true,
                'class' => true,
            ],
            'h3' => [
                'id' => true,
                'class' => true,
            ],
            'h4' => [
                'id' => true,
                'class' => true,
            ],
            'figure' => [
                'class' => true,
            ],
            'figcaption' => [
                'class' => true,
            ],
            'img' => [
                'src' => true,
                'alt' => true,
                'title' => true,
                'width' => true,
                'height' => true,
                'loading' => true,
                'decoding' => true,
            ],
            'video' => [
                'controls' => true,
                'poster' => true,
                'width' => true,
                'height' => true,
                'preload' => true,
                'muted' => true,
                'loop' => true,
                'autoplay' => true,
                'playsinline' => true,
            ],
            'source' => [
                'src' => true,
                'type' => true,
            ],
        ];

        if ($mode !== self::SANITATION_STRICT) {
            $allowed['iframe'] = [
                'src' => true,
                'width' => true,
                'height' => true,
                'title' => true,
                'frameborder' => true,
                'allow' => true,
                'allowfullscreen' => true,
                'loading' => true,
                'referrerpolicy' => true,
                'sandbox' => true,
            ];
            $allowed['span'] = [
                'class' => true,
            ];
        }

        return (array) apply_filters('dzen_rss_allowed_html', $allowed, $mode);
    }

    /**
     * The embed hosts we consider safe enough to keep as iframe content.
     *
     * @return string[]
     */
    public static function allowed_embed_hosts(): array
    {
        return [
            'youtube.com',
            'www.youtube.com',
            'youtu.be',
            'vk.com',
            'vkvideo.ru',
            'dzen.ru',
            'www.dzen.ru',
        ];
    }
}
