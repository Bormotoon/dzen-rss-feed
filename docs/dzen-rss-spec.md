# Dzen RSS Spec

Источник истины для этого плагина: [Разметка и подключение ленты RSS](https://dzen.ru/help/ru/website/rss-modify.html).

## Канонические выводы из документации

- Dzen использует RSS 2.0.
- В корневом элементе примера присутствуют namespaces:
  - `xmlns:content="http://purl.org/rss/1.0/modules/content/"`
  - `xmlns:dc="http://purl.org/dc/elements/1.1/"`
  - `xmlns:media="http://search.yahoo.com/mrss/"`
  - `xmlns:atom="http://www.w3.org/2005/Atom"`
  - `xmlns:georss="http://www.georss.org/georss"`
- На уровне `channel` в примере показаны:
  - `title`
  - `link`
  - `language`
- На уровне `item` в таблице документации показаны:
  - `title`
  - `category`
  - `guid`
  - `pubDate`
  - `enclosure`
  - `content:encoded`
  - `link`
  - `pdalink`
  - `description`
  - `author`
- В примере фида дополнительно присутствует `media:rating scheme="urn:simple">nonadult</media:rating`.
  - Это видно в example block.
  - В таблице на странице оно не выделено как отдельное обязательное поле.
  - В плагине мы оставляем его как безопасный необязательный маркер и не считаем его критическим для валидации.

## Сопоставление WordPress -> Dzen

| WordPress | Поле Dzen | Источник | Fallback | Комментарий |
| --- | --- | --- | --- | --- |
| `get_bloginfo('name')` | `channel/title` | пример на странице | нет | Название сайта как заголовок канала. |
| `home_url('/')` | `channel/link` | пример на странице | нет | Канонический URL сайта. |
| `get_locale()` -> compact code | `channel/language` | пример на странице | `ru` | В примере указан `ru`. |
| `post_title` / `_dzen_rss_title_override` | `item/title` | таблица `title` | нет | Должен быть непустым. |
| `get_permalink()` / `_dzen_rss_source_url_override` | `item/link` | таблица `link` | нет | URL статьи, трансляция которой идёт в RSS. |
| stable hash of site URL + post ID | `item/guid` | таблица `guid` + пример | URL поста или hash | Для дедупликации лучше стабильный идентификатор; в плагине GUID пишется как opaque value с `isPermaLink="false"`. |
| `post_date_gmt` / `_dzen_rss_pub_date_override` | `item/pubDate` | таблица `pubDate` | нет | Формат RFC822. |
| publication directives | `item/category` | таблица `category` | `native-draft`, `format-article` / `format-post`, `index` / `noindex`, `comment-all` / `comment-subscribers` / `comment-none` | В Dzen `category` перегружен как набор публикационных директив. Если все публикационные настройки стоят на Auto, тег `category` можно не выводить. |
| featured image / first content image / `_dzen_rss_image_override` | `item/enclosure` | таблица `enclosure` + media section | нет | Описание изображения обложки. |
| rendered or raw post content | `item/content:encoded` | таблица `content:encoded` | пустой HTML запрещён | Тело статьи в CDATA. |
| `_dzen_rss_description_override` / excerpt / first paragraph | `item/description` | таблица `description` | пустая строка | Краткое описание карточки. |
| post author / `_dzen_rss_author_override` | `item/author` | таблица `author` | omit | Поле опционально и по документации ограничено партнёрскими новостными сценариями. |
| mobile URL source | `item/pdalink` | таблица `pdalink` | omit | В плагине поле предусмотрено, но без отдельного mobile mirror source оно не сериализуется. |
| `nonadult` + `scheme="urn:simple"` | `item/media:rating` | пример фида | `nonadult` | Безопасный дефолт, не считаем его критическим для валидации. |

## Обязательные правила по контенту

### Разрешённый HTML внутри `content:encoded`

Из таблицы и примера:

- `p`
- `a`
- `b`
- `i`
- `u`
- `s`
- `h1`, `h2`, `h3`, `h4`
- `blockquote`
- `ul` + `li`
- `ol` + `li`
- `figure`
- `figcaption`
- `img`
- `video` + `source`

### Изображения

- Форматы: JPEG, GIF, PNG.
- Минимальная ширина: 700 px.
- Первое изображение, размеченное в контенте, попадает на карточку.
- `enclosure` может быть единственным упоминанием медиа или дублировать `figure/img`.
- Если MIME-тип изображения не удаётся определить локально, enclosure не выводится и в diagnostics появляется warning.

### Видео

- Поддерживается только MP4.
- Минимальное разрешение: 800 × 400.

### Важные ограничения

- Не использовать сложную вёрстку и нестандартные параметры.
- Не доверять сырому HTML записи.
- Не позволять пользовательскому вводу ломать XML.
- Для повторной публикации использовать тот же `guid`, чтобы не создавать дубликаты.
- При первой разметке лента должна содержать минимум 10 материалов, а на сайте должно быть не менее 3 публикаций за последний месяц.
- За один раз не отправляйте больше 500 публикаций.
- Материал должен быть актуальным, а сами RSS-материалы не стоит слать повторно без необходимости.

## Решения, принятые в плагине

1. `category` сериализуется как набор отдельных RSS `category`-элементов, по одному на директиву.
2. По умолчанию публикуем как `format-article`, с индексированием и закрытыми комментариями, но каждую директиву можно перевести в Auto, чтобы тег не выводился.
3. `media:rating` остаётся в потоке как `nonadult` с `scheme="urn:simple"`, потому что это есть в официальном примере.
4. `pdalink` не выводится, пока не появится реальный источник mobile mirror URL.
5. Санитайзер использует conservative whitelist по умолчанию и может работать в strict режиме.
6. `figure/img/video/source/iframe` не пропускаются без дополнительной проверки URL и атрибутов.
