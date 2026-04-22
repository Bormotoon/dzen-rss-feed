<?php
/**
 * Normalized feed item DTO used between mapper, sanitizer, validator, and renderer.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Dzen_RSS_Feed_Item
{
    public int $post_id;
    public string $post_type;

    public string $title = '';
    public string $link = '';
    public string $guid = '';
    public string $pub_date = '';
    public string $description = '';
    public string $content_html = '';
    public string $source_content_html = '';
    public string $source_title = '';
    public string $source_link = '';
    public string $source_description = '';
    public string $source_author = '';
    public string $author = '';
    public string $media_rating = 'nonadult';

    public ?string $mobile_link = null;
    public ?string $image_url = null;
    public ?string $source_image_url = null;
    public ?string $image_mime_type = null;
    public ?int $image_width = null;
    public ?int $image_height = null;

    /**
     * Dzen publication directives, e.g. native-draft, format-article, index, comment-none.
     * May be empty when all publication settings are set to Auto.
     *
     * @var string[]
     */
    public array $publication_directives = [];

    /**
     * Taxonomy snapshots used for diagnostics and validation.
     *
     * @var array<string, string[]>
     */
    public array $source_terms = [];

    /**
     * Miscellaneous metadata collected during mapping.
     *
     * @var array<string, mixed>
     */
    public array $metadata = [];

    /**
     * @var string[]
     */
    public array $warnings = [];

    public function __construct(int $post_id, string $post_type)
    {
        $this->post_id = $post_id;
        $this->post_type = $post_type;
    }

    public function has_image(): bool
    {
        return $this->image_url !== null && $this->image_url !== '';
    }
}
