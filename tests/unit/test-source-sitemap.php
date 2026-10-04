<?php
/**
 * Unit Tests for W2W_Source_Sitemap.
 *
 * @package WixToWordPressMigrator
 */

class W2W_Test_Source_Sitemap {

	/**
	 * Sample sitemapindex XML.
	 *
	 * @var string
	 */
	private static string $sample_sitemapindex = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" generatedBy="WIX">
  <sitemap>
    <loc>https://www.wheretonau.com/blog-posts-sitemap.xml</loc>
    <lastmod>2026-10-02</lastmod>
  </sitemap>
  <sitemap>
    <loc>https://www.wheretonau.com/pages-sitemap.xml</loc>
    <lastmod>2026-09-24</lastmod>
  </sitemap>
</sitemapindex>
XML;

	/**
	 * Sample blog-posts-sitemap.xml.
	 *
	 * @var string
	 */
	private static string $sample_posts_sitemap = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
  <url>
    <loc>https://www.wheretonau.com/post/packing-list-for-las-vegas-vegas-outfits</loc>
    <lastmod>2026-07-19</lastmod>
    <image:image>
      <image:loc>https://static.wixstatic.com/media/a5b892_hero~mv2.jpg/v1/fill/w_1000,h_563/hero.jpg</image:loc>
    </image:image>
  </url>
  <url>
    <loc>https://www.wheretonau.com/post/non-touristy-things-to-do-in-nyc</loc>
    <lastmod>2026-08-15</lastmod>
    <image:image>
      <image:loc>https://static.wixstatic.com/media/a5b892_nyc~mv2.jpg</image:loc>
    </image:image>
  </url>
  <url>
    <loc>https://www.wheretonau.com/about-me</loc>
    <lastmod>2026-01-01</lastmod>
  </url>
</urlset>
XML;

	/**
	 * Run all test assertions.
	 *
	 * @return void
	 */
	public function run(): void {
		$adapter = new W2W_Source_Sitemap();

		// 1. Adapter Metadata.
		w2w_assert_equals( 'sitemap', $adapter->get_id(), 'Adapter ID must be "sitemap".' );
		w2w_assert_equals( 'Wix Sitemap XML', $adapter->get_name(), 'Adapter name must match.' );

		// 2. Integration with W2W_Source_Manager.
		$manager = new W2W_Source_Manager();
		w2w_assert_true( $manager->has_adapter( 'sitemap' ), 'Source manager must register sitemap adapter by default.' );

		// 3. Validation: URLs vs SSRF.
		w2w_assert_true( $adapter->validate_source( 'https://www.wheretonau.com/blog-posts-sitemap.xml' ), 'Public sitemap URL should pass validation.' );
		w2w_assert_false( $adapter->validate_source( 'http://localhost/sitemap.xml' ), 'SSRF: localhost URL must be rejected.' );
		w2w_assert_true( $adapter->validate_source( self::$sample_posts_sitemap ), 'Raw XML with <urlset> should pass validation.' );
		w2w_assert_true( $adapter->validate_source( self::$sample_sitemapindex ), 'Raw XML with <sitemapindex> should pass validation.' );

		// 4. Smart Auto-Detection.
		w2w_assert_equals( 'single_post', W2W_Source_Manager::detect_source_type( 'https://www.wheretonau.com/post/packing-list-for-las-vegas-vegas-outfits' ), 'Single post URL should auto-detect as "single_post".' );
		w2w_assert_equals( 'sitemap', W2W_Source_Manager::detect_source_type( 'https://www.wheretonau.com/blog-posts-sitemap.xml' ), 'Sitemap URL should auto-detect as "sitemap".' );
		w2w_assert_equals( 'sitemap', W2W_Source_Manager::detect_source_type( 'https://www.wheretonau.com/sitemap.xml' ), 'Root sitemap should auto-detect as "sitemap".' );
		w2w_assert_equals( 'rss', W2W_Source_Manager::detect_source_type( 'https://www.wheretonau.com/blog-feed.xml' ), 'Feed URL should auto-detect as "rss".' );

		// 5. XML Parsing into DTOs.
		// Note: The sample XML has 3 URLs, but one is a static page (/about-me), so only 2 blog posts should be parsed.
		$posts = $adapter->parse_sitemap_xml( self::$sample_posts_sitemap );
		w2w_assert_equals( 2, count( $posts ), 'Sitemap should filter out non-blog URLs and return 2 blog post DTOs.' );

		$item1 = $posts[0];
		w2w_assert_true( $item1 instanceof W2W_Post_DTO, 'Item 1 must be instance of W2W_Post_DTO.' );
		w2w_assert_equals( 'packing-list-for-las-vegas-vegas-outfits', $item1->slug, 'Slug must be extracted from URL path.' );
		w2w_assert_equals( 'Packing List For Las Vegas Vegas Outfits', $item1->title, 'Title should be humanized from slug.' );
		w2w_assert_equals( 'https://www.wheretonau.com/post/packing-list-for-las-vegas-vegas-outfits', $item1->original_url, 'Original URL must match.' );
		w2w_assert_equals(
			'https://static.wixstatic.com/media/a5b892_hero~mv2.jpg',
			$item1->featured_image_url,
			'Featured image from <image:loc> must be normalized to full-res original.'
		);
		w2w_assert_equals( '2026-07-19 00:00:00', $item1->date_published, 'Publication date must be formatted from lastmod.' );

		$item2 = $posts[1];
		w2w_assert_equals( 'non-touristy-things-to-do-in-nyc', $item2->slug, 'Item 2 slug must match.' );
		w2w_assert_equals( 'https://static.wixstatic.com/media/a5b892_nyc~mv2.jpg', $item2->featured_image_url, 'Item 2 image must match.' );

		// 6. Pagination arguments.
		$paged = $adapter->parse_sitemap_xml( self::$sample_posts_sitemap, array( 'limit' => 1 ) );
		w2w_assert_equals( 1, count( $paged ), 'Limit parameter should return 1 post.' );
	}
}

// Execute test suite.
( new W2W_Test_Source_Sitemap() )->run();
