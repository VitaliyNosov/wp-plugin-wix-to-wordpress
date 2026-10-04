<?php
/**
 * Data Transfer Object for Migrated Posts.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Strongly typed DTO isolating WordPress core from external source formats.
 */
class W2W_Post_DTO {

	/**
	 * Unique original ID or GUID from the source platform.
	 *
	 * @var string
	 */
	public string $original_id;

	/**
	 * Post title.
	 *
	 * @var string
	 */
	public string $title;

	/**
	 * Main post HTML body content.
	 *
	 * @var string
	 */
	public string $content;

	/**
	 * Sanitized URL slug for WordPress post_name preservation.
	 *
	 * @var string
	 */
	public string $slug;

	/**
	 * Original full URL on the source platform (for 301 redirects).
	 *
	 * @var string
	 */
	public string $original_url;

	/**
	 * URL of the main featured image / thumbnail, if available.
	 *
	 * @var string|null
	 */
	public ?string $featured_image_url;

	/**
	 * List of category names or slugs.
	 *
	 * @var array<string>
	 */
	public array $categories;

	/**
	 * List of tag names or slugs.
	 *
	 * @var array<string>
	 */
	public array $tags;

	/**
	 * Author name from the source platform.
	 *
	 * @var string|null
	 */
	public ?string $author_name;

	/**
	 * SEO Metadata (meta_title, meta_description, keywords, og_image).
	 *
	 * @var array<string, mixed>
	 */
	public array $seo_meta;

	/**
	 * Publication datetime string (MySQL or ISO 8601).
	 *
	 * @var string
	 */
	public string $date_published;

	/**
	 * Post status ('publish', 'draft', 'pending').
	 *
	 * @var string
	 */
	public string $status;

	/**
	 * Constructor.
	 *
	 * @param string               $original_id        Original ID.
	 * @param string               $title              Post title.
	 * @param string               $content            Body content.
	 * @param string               $slug               Post slug.
	 * @param string               $original_url       Source URL.
	 * @param string|null          $featured_image_url Featured image URL.
	 * @param array<string>        $categories         Categories.
	 * @param array<string>        $tags               Tags.
	 * @param string|null          $author_name        Author.
	 * @param array<string, mixed> $seo_meta           SEO metadata.
	 * @param string               $date_published     Publication date.
	 * @param string               $status             Post status.
	 */
	public function __construct(
		string $original_id = '',
		string $title = '',
		string $content = '',
		string $slug = '',
		string $original_url = '',
		?string $featured_image_url = null,
		array $categories = array(),
		array $tags = array(),
		?string $author_name = null,
		array $seo_meta = array(),
		string $date_published = '',
		string $status = 'publish'
	) {
		$this->original_id        = $original_id;
		$this->title              = $title;
		$this->content            = $content;
		$this->slug               = $slug;
		$this->original_url       = $original_url;
		$this->featured_image_url = $featured_image_url;
		$this->categories         = $categories;
		$this->tags               = $tags;
		$this->author_name        = $author_name;
		$this->seo_meta           = $seo_meta;
		$this->date_published     = ! empty( $date_published ) ? $date_published : gmdate( 'Y-m-d H:i:s' );
		$this->status             = $status;
	}

	/**
	 * Hydrates a DTO instance from an associative array.
	 *
	 * @param array<string, mixed> $data Raw input array.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self(
			(string) ( $data['original_id'] ?? '' ),
			(string) ( $data['title'] ?? '' ),
			(string) ( $data['content'] ?? '' ),
			(string) ( $data['slug'] ?? '' ),
			(string) ( $data['original_url'] ?? '' ),
			isset( $data['featured_image_url'] ) && ! empty( $data['featured_image_url'] ) ? (string) $data['featured_image_url'] : null,
			isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array(),
			isset( $data['tags'] ) && is_array( $data['tags'] ) ? $data['tags'] : array(),
			isset( $data['author_name'] ) && ! empty( $data['author_name'] ) ? (string) $data['author_name'] : null,
			isset( $data['seo_meta'] ) && is_array( $data['seo_meta'] ) ? $data['seo_meta'] : array(),
			(string) ( $data['date_published'] ?? '' ),
			(string) ( $data['status'] ?? 'publish' )
		);
	}

	/**
	 * Converts DTO to array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'original_id'        => $this->original_id,
			'title'              => $this->title,
			'content'            => $this->content,
			'slug'               => $this->slug,
			'original_url'       => $this->original_url,
			'featured_image_url' => $this->featured_image_url,
			'categories'         => $this->categories,
			'tags'               => $this->tags,
			'author_name'        => $this->author_name,
			'seo_meta'           => $this->seo_meta,
			'date_published'     => $this->date_published,
			'status'             => $this->status,
		);
	}

	/**
	 * Validates required DTO properties.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function validate(): bool {
		return ! empty( trim( $this->original_id ) ) && ! empty( trim( $this->title ) );
	}
}
