=== Dzen RSS Feed ===
Contributors: pedobraz
Tags: rss, dzen, yandex, feed, wordpress
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.4

Generates a dedicated RSS feed for Yandex Dzen crossposting.

== Description ==

This plugin creates a separate feed endpoint for Dzen, sanitizes post content, exposes Dzen publication directives, and provides admin diagnostics with cache invalidation controls.

== Installation ==

1. Copy the plugin folder into `wp-content/plugins/dzen-rss-feed/`.
2. Activate the plugin in WordPress admin.
3. Open `Settings -> Dzen RSS` to configure the feed.

== Notes ==

- The feed slug defaults to `dzen`.
- Old slugs are preserved as aliases when you change the feed slug.
- The feed uses XMLWriter and transients only.
- Dzen publication directives are optional; leaving them on Auto omits the corresponding `category` tag.
- WebP covers are accepted alongside JPEG, PNG, and GIF enclosures.
