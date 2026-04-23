<?php
/**
 * XMLWriter-based renderer for the final Dzen RSS document.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Renderer
{
    public function render(array $items, Dzen_RSS_Options $options): string
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('rss');
        $writer->writeAttribute('version', '2.0');
        $writer->writeAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
        $writer->writeAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $writer->writeAttribute('xmlns:media', 'http://search.yahoo.com/mrss/');
        $writer->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $writer->writeAttribute('xmlns:georss', 'http://www.georss.org/georss');
        $writer->startElement('channel');
        $writer->writeElement('title', (string) get_bloginfo('name'));
        $writer->writeElement('link', home_url('/'));
        $writer->writeElement('language', Dzen_RSS_Constants::get_language_code());

        foreach ($items as $item) {
            if (! $item instanceof Dzen_RSS_Feed_Item) {
                continue;
            }

            $this->write_item($writer, $item);
        }

        $writer->endElement(); // channel
        $writer->endElement(); // rss
        $writer->endDocument();

        return $writer->outputMemory();
    }

    private function write_item(XMLWriter $writer, Dzen_RSS_Feed_Item $item): void
    {
        $writer->startElement('item');
        $writer->writeElement('title', $item->title);
        $writer->writeElement('link', $item->link);

        if ($item->mobile_link !== null && $item->mobile_link !== '') {
            $writer->writeElement('pdalink', $item->mobile_link);
        }

        $writer->startElement('guid');
        $writer->writeAttribute('isPermaLink', 'false');
        $writer->text($item->guid);
        $writer->endElement();
        $writer->writeElement('pubDate', $item->pub_date);
        $writer->startElement('media:rating');
        $writer->writeAttribute('scheme', 'urn:simple');
        $writer->text($item->media_rating !== '' ? $item->media_rating : 'nonadult');
        $writer->endElement();

        foreach ($this->normalize_publication_directives($item->publication_directives) as $directive) {
            $writer->writeElement('category', $directive);
        }

        $image_mime_type = $item->image_mime_type !== null && $item->image_mime_type !== ''
            ? Dzen_RSS_Constants::normalize_image_mime_type($item->image_mime_type)
            : '';

        if (
            $item->has_image()
            && $item->image_url !== null
            && $this->is_valid_url($item->image_url)
            && $image_mime_type !== ''
            && in_array($image_mime_type, Dzen_RSS_Constants::allowed_image_mime_types(), true)
        ) {
            $writer->startElement('enclosure');
            $writer->writeAttribute('url', $item->image_url);
            $writer->writeAttribute('type', $image_mime_type);
            $writer->endElement();
        }

        if ($item->author !== '') {
            $writer->writeElement('author', $item->author);
        }

        if ($item->description !== '') {
            $writer->writeElement('description', $item->description);
        }

        $writer->startElement('content:encoded');
        $writer->writeRaw($this->wrap_cdata($item->content_html));
        $writer->endElement();

        $writer->endElement(); // item
    }

    /**
     * @param string[] $directives
     * @return string[]
     */
    private function normalize_publication_directives(array $directives): array
    {
        $allowed = Dzen_RSS_Constants::allowed_publication_directives();

        $filtered = [];
        foreach ($directives as $directive) {
            if (! is_string($directive)) {
                continue;
            }

            $directive = sanitize_key($directive);
            if ($directive !== '' && in_array($directive, $allowed, true)) {
                $filtered[] = $directive;
            }
        }

        return array_values(array_unique($filtered));
    }

    private function wrap_cdata(string $content): string
    {
        $content = str_replace(']]>', ']]]]><![CDATA[>', $content);

        return '<![CDATA[' . $content . ']]>';
    }

    private function is_valid_url(string $url): bool
    {
        return $url !== '' && (bool) filter_var($url, FILTER_VALIDATE_URL);
    }
}
