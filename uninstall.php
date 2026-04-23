<?php
/**
 * Safe uninstall routine that removes plugin-owned data only.
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/includes/class-dzen-rss-constants.php';

delete_option(Dzen_RSS_Constants::OPTION_NAME);
delete_option(Dzen_RSS_Constants::CACHE_VERSION_OPTION);
delete_option(Dzen_RSS_Constants::PLUGIN_VERSION_OPTION);
delete_option(Dzen_RSS_Constants::LAST_DIAGNOSTICS_OPTION);

foreach ([
    Dzen_RSS_Constants::META_INCLUDE,
    Dzen_RSS_Constants::META_EXCLUDE,
    Dzen_RSS_Constants::META_TITLE_OVERRIDE,
    Dzen_RSS_Constants::META_DESCRIPTION_OVERRIDE,
    Dzen_RSS_Constants::META_AUTHOR_OVERRIDE,
    Dzen_RSS_Constants::META_IMAGE_OVERRIDE,
    Dzen_RSS_Constants::META_SOURCE_URL_OVERRIDE,
    Dzen_RSS_Constants::META_PUB_DATE_OVERRIDE,
] as $meta_key) {
    delete_post_meta_by_key($meta_key);
}
