# Architecture

Этот плагин выделен в отдельный git subrepo внутри репозитория:

- `news/wordpress/plugins/dzen-rss-feed/`

## Цель

Сформировать отдельную RSS-ленту для Яндекс Дзена, не ломая стандартные ленты WordPress и не смешивая query, sanitation, validation, rendering и admin UI в одном файле.

## Слои и классы

| Класс | Роль |
| --- | --- |
| `Dzen_RSS_Plugin` | Composition root. Собирает сервисы и запускает hooks. |
| `Dzen_RSS_Hooks` | Центральная регистрация WP hooks, activation/deactivation, invalidation. |
| `Dzen_RSS_Options` | Единственная обёртка над `dzen_rss_options`. |
| `Dzen_RSS_Query_Service` | Выборка кандидатов через `WP_Query`. |
| `Dzen_RSS_Mapper` | `WP_Post` -> `Dzen_RSS_Feed_Item`. |
| `Dzen_RSS_Feed_Item` | DTO для нормализованного item. |
| `Dzen_RSS_HTML_Normalizer` | Низкоуровневое приведение HTML к безопасному виду. |
| `Dzen_RSS_Content_Sanitizer` | Whitelist sanitation pipeline. |
| `Dzen_RSS_Image_Resolver` | Feed-safe image selection и локальная конвертация неподдерживаемых форматов. |
| `Dzen_RSS_Validator` | Проверка обязательных полей и Dzen-ограничений. |
| `Dzen_RSS_Validation_Result` | Вердикт, причины и предупреждения. |
| `Dzen_RSS_Renderer` | Единственное место генерации XML через `XMLWriter`. |
| `Dzen_RSS_Cache` | Transient cache и cache versioning. |
| `Dzen_RSS_Diagnostics` | Хранение последнего отчёта о генерации. |
| `Dzen_RSS_Post_Meta` | Метаполя записи, metabox, save handler. |
| `Dzen_RSS_Settings_Page` | Settings API-backed config screen. |
| `Dzen_RSS_Admin_Diagnostics_Page` | Админская диагностика и manual reset actions. |
| `Dzen_RSS_Logger` | Единый фасад логирования. |
| `Dzen_RSS_Constants` | Константы, default options, shared whitelists. |

## Feed generation pipeline

1. Пользователь или Dzen bot запрашивает feed endpoint.
2. Controller проверяет, включён ли feed.
3. Cache layer пытается вернуть готовый XML.
4. Если кэша нет:
   - `Query_Service` собирает кандидатов.
   - Для каждого кандидата `Mapper` создаёт DTO.
   - `Mapper` использует `Image_Resolver`, чтобы подготовить feed-safe image URL и при необходимости локально транскодировать WebP/unsupported uploads в JPEG.
   - DTO проходит `dzen_rss_feed_item`.
   - `Content_Sanitizer` нормализует HTML.
   - `Validator` проверяет готовый item.
   - Причины исключения пишутся в diagnostics.
5. `Renderer` serializes valid items through `XMLWriter`.
6. XML сохраняется в transient cache, если caching включён.
7. Ответ отдаётся как `application/rss+xml; charset=UTF-8`.

## Sanitation policy

### Default mode

`conservative`:

- разрешает только те HTML-теги, которые есть в официальной таблице Дзена;
- дополнительно позволяет `iframe` и `span` для совместимости с embed-кодами и caption-обвязкой;
- переписывает относительные URL в абсолютные;
- вычищает unsafe tags и attributes.

## Validation policy

- `guid` сериализуется как opaque identifier с `isPermaLink="false"`.
- `enclosure` выводится только для проверяемых image MIME types: JPEG, PNG, GIF.
- Если MIME определить невозможно или формат не поддерживается, item может пройти с warning, но без `enclosure`.
- Локальные WebP-изображения транскодируются в JPEG только для feed-обложки, чтобы не менять экономный storage сайта.
- `pubDate` формируется как RFC822 в UTC через `gmdate(DATE_RSS, ...)`, чтобы не зависеть от локали WordPress.

### Strict mode

`strict`:

- оставляет только более узкий набор тегов;
- удаляет `iframe`;
- используется для максимально осторожного режима.

## Cache strategy

- Кэш только на transients.
- Формируется по `cache_version` + значимому срезу опций.
- `debug_mode` отключает кэш.
- Инвалидация происходит при:
  - сохранении релевантной записи;
  - изменении релевантных meta keys;
  - смене таксономий;
  - изменении attachment-ов, которые могут быть обложками;
  - сохранении настроек;
  - activation/deactivation.

## Hooks we intentionally expose

- `dzen_rss_candidate_post_ids`
- `dzen_rss_feed_item`
- `dzen_rss_publication_directives`
- `dzen_rss_sanitized_html`
- `dzen_rss_valid_items`
- `dzen_rss_cache_ttl`
- `dzen_rss_allowed_html`
- `dzen_rss_allowed_embed_hosts`
- `dzen_rss_feed_slugs`

## Decisions worth remembering

1. `category` в Dzen не используется для WP taxonomy categories. Это публикационные директивы.
2. `guid` должен быть стабильным, поэтому он вычисляется из site URL + post ID и пишется как `isPermaLink="false"`.
3. `author` и `description` опциональны, но наличие этих полей улучшает карточку материала.
4. `category` относится не к WP taxonomy, а к Dzen publication directives; если все publication settings стоят на `Auto`, тег можно не выводить.
5. Стандартные WordPress feeds не трогаются.
6. URL endpoint можно менять, но старые slugs сохраняются как aliases, чтобы не ломать внешние интеграции.
