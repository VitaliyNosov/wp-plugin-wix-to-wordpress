<?php
/**
 * Unit Tests for W2W_Source_RSS.
 *
 * @package WixToWordPressMigrator
 */

class W2W_Test_Source_RSS {

	/**
	 * Sample realistic Wix RSS feed.
	 *
	 * @var string
	 */
	private static string $sample_wix_rss = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:media="http://search.yahoo.com/mrss/"
     xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>KiviCare Dental Blog</title>
    <link>https://clinic.wixsite.com/smile</link>
    <description>Modern Dentistry and Care Tips</description>
    <item>
      <title><![CDATA[Dental Implants &amp; Oral Health]]></title>
      <link>https://clinic.wixsite.com/smile/post/dental-implants-guide</link>
      <guid isPermaLink="false">wix-post-uuid-101</guid>
      <pubDate>Fri, 10 May 2024 14:30:00 GMT</pubDate>
      <dc:creator><![CDATA[Dr. Alexander Stone]]></dc:creator>
      <description><![CDATA[Everything you need to know about modern dental implants and long-term care.]]></description>
      <content:encoded><![CDATA[
        <p>Dental implants are the gold standard for tooth replacement.</p>
        <p><img src="https://static.wixstatic.com/media/clinic_body_img~mv2.jpg/v1/fill/w_800,h_600/clinic.jpg" alt="Dental Clinic" /></p>
      ]]></content:encoded>
      <category><![CDATA[Dental Health]]></category>
      <category><![CDATA[Implants]]></category>
      <enclosure url="https://static.wixstatic.com/media/implant_hero~mv2.jpg/v1/fill/w_1200,h_800/hero.jpg" type="image/jpeg" length="124500" />
    </item>
    <item>
      <title>5 Ways to Keep Teeth White</title>
      <link>https://clinic.wixsite.com/smile/post/teeth-whitening-tips</link>
      <guid isPermaLink="false">wix-post-uuid-102</guid>
      <pubDate>Mon, 20 May 2024 09:00:00 GMT</pubDate>
      <dc:creator>Sarah Jenkins</dc:creator>
      <description><![CDATA[Simple everyday habits for a brighter and healthier smile.]]></description>
      <content:encoded><![CDATA[<p>Discover easy habits that protect your teeth enamel.</p>]]></content:encoded>
      <category>Cosmetic Dentistry</category>
      <media:content url="https://static.wixstatic.com/media/whitening_feat~mv2.jpg" medium="image" />
    </item>
  </channel>
</rss>
XML;

	/**
	 * Runs all test assertions for the RSS adapter suite.
	 *
	 * @return void
	 */
	public function run(): void {
		$adapter = new W2W_Source_RSS();

		// 1. Adapter identity.
		w2w_assert_equals( 'rss', $adapter->get_id(), 'RSS adapter ID must be "rss".' );
		w2w_assert_equals( 'Wix RSS Feed', $adapter->get_name(), 'RSS adapter name must be "Wix RSS Feed".' );

		// 2. Integration with W2W_Source_Manager.
		$manager = new W2W_Source_Manager();
		w2w_assert_true( $manager->has_adapter( 'rss' ), 'W2W_Source_Manager must register W2W_Source_RSS by default.' );
		$retrieved = $manager->get_adapter( 'rss' );
		w2w_assert_not_null( $retrieved, 'Manager should return registered RSS adapter instance.' );
		w2w_assert_equals( 'rss', $retrieved->get_id(), 'Retrieved adapter ID must match "rss".' );

		// 3. Validation: Safe URLs vs SSRF attacks.
		w2w_assert_true( $adapter->validate_source( 'https://myclinic.wixsite.com/blog/blog-feed.xml' ), 'Public HTTPS feed URL should be valid.' );
		w2w_assert_true( $adapter->validate_source( 'http://example.com/feed.xml' ), 'Public HTTP feed URL should be valid.' );
		w2w_assert_false( $adapter->validate_source( 'http://localhost/feed.xml' ), 'SSRF: localhost URL must be rejected.' );
		w2w_assert_false( $adapter->validate_source( 'http://127.0.0.1:8080/feed' ), 'SSRF: loopback IP must be rejected.' );
		w2w_assert_false( $adapter->validate_source( 'ftp://example.com/feed.xml' ), 'Non-HTTP protocol must be rejected.' );
		w2w_assert_false( $adapter->validate_source( '' ), 'Empty string must be rejected.' );
		w2w_assert_false( $adapter->validate_source( 'not-a-valid-url' ), 'Plain non-URL string must be rejected.' );

		// 4. Validation: Raw XML string.
		w2w_assert_true( $adapter->validate_source( self::$sample_wix_rss ), 'Raw XML string with <rss root should be valid.' );

		// 5. XML Parsing: Full feed transformation into DTOs.
		$posts = $adapter->fetch_posts( self::$sample_wix_rss );
		w2w_assert_equals( 2, count( $posts ), 'Sample feed must yield exactly 2 posts.' );

		// Item 1 Verification.
		$post1 = $posts[0];
		w2w_assert_true( $post1 instanceof W2W_Post_DTO, 'First item must be an instance of W2W_Post_DTO.' );
		w2w_assert_equals( 'wix-post-uuid-101', $post1->original_id, 'Original ID must match GUID.' );
		w2w_assert_equals( 'Dental Implants & Oral Health', $post1->title, 'Title HTML entities (&amp;) must be decoded.' );
		w2w_assert_equals( 'dental-implants-guide', $post1->slug, 'Slug must be correctly extracted from Wix post URL path.' );
		w2w_assert_equals( 'https://clinic.wixsite.com/smile/post/dental-implants-guide', $post1->original_url, 'Original URL must match link.' );
		w2w_assert_equals( 'Dr. Alexander Stone', $post1->author_name, 'Author must be extracted from <dc:creator>.' );
		w2w_assert_contains( '<p>Dental implants are the gold standard for tooth replacement.</p>', $post1->content, 'Content must be extracted from content:encoded.' );
		w2w_assert_equals( 2, count( $post1->categories ), 'Post 1 must have 2 categories.' );
		w2w_assert_contains( 'Dental Health', $post1->categories, 'Post 1 must have "Dental Health" category.' );
		w2w_assert_contains( 'Implants', $post1->categories, 'Post 1 must have "Implants" category.' );
		w2w_assert_equals( '2024-05-10 14:30:00', $post1->date_published, 'Publication date must be formatted to MySQL format.' );

		// Image Normalization: Enclosure URL should be stripped of dynamic Wix CDN crop params.
		w2w_assert_equals(
			'https://static.wixstatic.com/media/implant_hero~mv2.jpg',
			$post1->featured_image_url,
			'Featured image from enclosure must be normalized to full-res original.'
		);

		// SEO Meta in DTO.
		w2w_assert_equals( 'Dental Implants & Oral Health', $post1->seo_meta['meta_title'], 'SEO meta_title should be present.' );
		w2w_assert_contains( 'Everything you need to know', $post1->seo_meta['meta_description'], 'SEO meta_description should match description snippet.' );

		// Item 2 Verification (Media RSS fallback).
		$post2 = $posts[1];
		w2w_assert_equals( 'wix-post-uuid-102', $post2->original_id, 'Post 2 GUID must match.' );
		w2w_assert_equals( '5 Ways to Keep Teeth White', $post2->title, 'Post 2 title must match.' );
		w2w_assert_equals( 'teeth-whitening-tips', $post2->slug, 'Post 2 slug must be extracted from link.' );
		w2w_assert_equals( 'Sarah Jenkins', $post2->author_name, 'Post 2 author must match.' );
		w2w_assert_equals(
			'https://static.wixstatic.com/media/whitening_feat~mv2.jpg',
			$post2->featured_image_url,
			'Post 2 featured image must be extracted from <media:content>.'
		);
		w2w_assert_contains( 'Cosmetic Dentistry', $post2->categories, 'Post 2 category must be "Cosmetic Dentistry".' );

		// 6. Pagination arguments (offset & limit).
		$paged_posts = $adapter->fetch_posts( self::$sample_wix_rss, array( 'limit' => 1 ) );
		w2w_assert_equals( 1, count( $paged_posts ), 'Query with limit=1 must return 1 post.' );
		w2w_assert_equals( 'wix-post-uuid-101', $paged_posts[0]->original_id, 'First paged post should be item 1.' );

		$offset_posts = $adapter->fetch_posts( self::$sample_wix_rss, array( 'offset' => 1, 'limit' => 1 ) );
		w2w_assert_equals( 1, count( $offset_posts ), 'Query with offset=1, limit=1 must return 1 post.' );
		w2w_assert_equals( 'wix-post-uuid-102', $offset_posts[0]->original_id, 'Offset post should be item 2.' );

		// 7. Error handling: Malformed XML.
		$malformed_xml = '<rss version="2.0"><channel><title>Broken<item></channel>';
		$caught_malformed = false;
		try {
			$adapter->parse_rss_xml( $malformed_xml );
		} catch ( \RuntimeException $e ) {
			$caught_malformed = true;
		}
		w2w_assert_true( $caught_malformed, 'Malformed XML must throw RuntimeException.' );

		// 8. Empty channel.
		$empty_xml = '<?xml version="1.0"?><rss version="2.0"><channel><title>Empty</title></channel></rss>';
		$empty_posts = $adapter->parse_rss_xml( $empty_xml );
		w2w_assert_equals( 0, count( $empty_posts ), 'Feed with no items should return empty array.' );

		// 9. Invalid source input exception.
		$caught_invalid = false;
		try {
			$adapter->fetch_posts( 'http://localhost/hack' );
		} catch ( \InvalidArgumentException $e ) {
			$caught_invalid = true;
		}
		w2w_assert_true( $caught_invalid, 'SSRF target in fetch_posts must throw InvalidArgumentException.' );
	}
}

// Execute the suite.
( new W2W_Test_Source_RSS() )->run();
