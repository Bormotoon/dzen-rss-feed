# Testing checklist

## Smoke tests

- [ ] Плагин активируется без fatal errors.
- [ ] Feed endpoint открывается без логина.
- [ ] Feed не даёт 404 после активации.
- [ ] XML валиден.
- [ ] Кодировка UTF-8 корректна.
- [ ] До XML declaration нет BOM или мусора.
- [ ] Стандартные RSS WordPress не сломаны.

## Eligibility tests

- [ ] Draft posts не попадают в ленту.
- [ ] Password-protected posts не попадают в ленту.
- [ ] Записи, исключённые метаполем, не попадают в ленту.
- [ ] Записи из неразрешённых post types не попадают в ленту.
- [ ] Explicit inclusion mode работает только для явно включённых записей.
- [ ] Исключённые taxonomies действительно исключают записи.

## Content tests

- [ ] Заголовки с кириллицей корректны.
- [ ] Emoji не ломают XML.
- [ ] URL в `link`, `enclosure` и `img/src` абсолютные.
- [ ] Изображения абсолютные и доступны.
- [ ] WebP-изображение из локальной медиатеки конвертируется в JPEG только для RSS `enclosure`.
- [ ] Пустой excerpt корректно fallback’ится.
- [ ] Пустой author корректно исчезает из XML.
- [ ] GUID стабилен между генерациями.
- [ ] `media:rating` сериализуется с `scheme="urn:simple"`.
- [ ] Very dirty HTML не ломает feed.
- [ ] Запись без изображения обрабатывается корректно.
- [ ] Запись с несколькими изображениями не ломает feed.
- [ ] Изображение в неподдерживаемом формате не попадает в enclosure и помечается warning в diagnostics, но запись не исключается.
- [ ] Запись с shortcode корректно очищается.
- [ ] Запись с embed/iframe корректно очищается.
- [ ] Запись с таблицей не ломает XML.
- [ ] Запись с блоком "Читайте также" очищается.

## Cache and invalidation

- [ ] Изменение релевантной записи сбрасывает кэш.
- [ ] Изменение релевантных meta keys сбрасывает кэш.
- [ ] Изменение attachment-а сбрасывает кэш.
- [ ] Изменение настроек сбрасывает кэш.
- [ ] Debug mode не кэширует так, чтобы мешать проверке.

## Admin tests

- [ ] Settings page сохраняет значения через Settings API.
- [ ] Dzen publication mode can be set to `native-draft`.
- [ ] Publication format can be switched between `format-article`, `format-post`, and Auto.
- [ ] Publication directives can be fully omitted when all four controls are set to Auto.
- [ ] Capability checks работают на save actions.
- [ ] Nonce checks работают на save actions.
- [ ] Diagnostics page показывает причины исключения.
- [ ] Diagnostics page умеет сбросить cache.
- [ ] Diagnostics page умеет flush rewrite rules.

## Manual sanity data

Для ручной проверки полезно завести несколько sample posts:

- пост только с текстом;
- пост с featured image;
- пост с несколькими изображениями;
- пост с shortcodes;
- пост с iframe/embedded video;
- пост с таблицей;
- пост с override meta keys;
- post type из списка allowed;
- post type не из списка allowed.
