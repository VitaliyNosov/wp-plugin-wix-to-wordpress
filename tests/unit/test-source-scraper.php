<?php
/**
 * Unit Tests for W2W_Source_Scraper.
 *
 * @package WixToWordPressMigrator
 */

class W2W_Test_Source_Scraper {

	/**
	 * Realistic sample Wix single post HTML.
	 *
	 * @var string
	 */
	private static string $sample_post_html = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <title>What to Pack for 3 Days in Vegas: Ultimate Outfit Guide</title>
  <link rel="canonical" href="https://www.wheretonau.com/post/packing-list-for-las-vegas-vegas-outfits"/>
  <meta property="og:title" content="What to Pack for 3 Days in Vegas: Ultimate Outfit Guide"/>
  <meta property="og:url" content="https://www.wheretonau.com/post/packing-list-for-las-vegas-vegas-outfits"/>
  <meta property="og:description" content="Planning a trip to the Strip and wondering what to wear in Las Vegas? This guide is filled with stylish outfits."/>
  <meta property="og:image" content="https://static.wixstatic.com/media/a5b892_hero~mv2.jpg/v1/fill/w_1000,h_563/hero.jpg"/>
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "BlogPosting",
    "headline": "What to Pack for 3 Days in Vegas: Ultimate Outfit Guide",
    "url": "https://www.wheretonau.com/post/packing-list-for-las-vegas-vegas-outfits",
    "author": {
      "@type": "Person",
      "name": "Naureen Chhipa"
    },
    "datePublished": "2026-07-19T19:32:30.000Z",
    "image": {
      "@type": "ImageObject",
      "url": "https://static.wixstatic.com/media/a5b892_hero~mv2.jpg/v1/fill/w_1000,h_563/hero.jpg"
    },
    "description": "Planning a trip to the Strip and wondering what to wear in Las Vegas?"
  }
  </script>
</head>
<body>
  <div id="site-root">
    <article>
      <div data-hook="post-title">
        <h1>What to Pack for 3 Days in Vegas: Ultimate Outfit Guide</h1>
      </div>
      <div class="NtBDdE"><span>By Naureen Chhipa</span></div>
      <section><ul aria-label="Post categories"><li><a href="https://www.wheretonau.com/blog/categories/lifestyle">Lifestyle</a></li><li><a href="https://www.wheretonau.com/blog/categories/travel">Travel</a></li></ul></section>
      <section><ul aria-label="Post tags"><li><a href="https://www.wheretonau.com/blog/tags/vegas">Vegas</a></li></ul></section>
      <p>Putting together the perfect packing list for 3 days in Las Vegas is half the fun!</p>
      <wow-image data-image-info='{"imageData":{"uri":"a5b892_casino_walk~mv2.jpg","alt":"Vegas Strip Walk"}}'>
        <img alt="Vegas Strip Walk" />
      </wow-image>
      <h2>What to Wear During Daytime</h2>
      <p>Between walking miles on hard casino floors, prioritize comfort.</p>
      <p><img src="https://static.wixstatic.com/media/a5b892_shoes~mv2.jpg/v1/fill/w_800,h_600/shoes.jpg" alt="Comfortable Shoes" /></p>
      <div class="ShareButtons"><ul><li>Facebook</li><li>Twitter</li></ul></div>
      <footer><div class="comments">Comments Section</div></footer>
    </article>
  </div>
</body>
</html>
HTML;

	/**
	 * Run all test assertions.
	 *
	 * @return void
	 */
	public function run(): void {
		$scraper = new W2W_Source_Scraper();

		// 1. Adapter Metadata.
		w2w_assert_equals( 'single_post', $scraper->get_id(), 'Adapter ID must be "single_post".' );
		w2w_assert_equals( 'Wix Single Post URL', $scraper->get_name(), 'Adapter name must match.' );

		// 2. Integration with W2W_Source_Manager.
		$manager = new W2W_Source_Manager();
		w2w_assert_true( $manager->has_adapter( 'single_post' ), 'Source manager must register single_post adapter by default.' );

		// 3. Validation: URLs vs SSRF.
		w2w_assert_true( $scraper->validate_source( 'https://www.wheretonau.com/post/packing-list-for-las-vegas-vegas-outfits' ), 'Valid public post URL should pass validation.' );
		w2w_assert_false( $scraper->validate_source( 'http://localhost/post/hack' ), 'SSRF: localhost URL must be rejected.' );
		w2w_assert_false( $scraper->validate_source( 'http://127.0.0.1:8080/post/hack' ), 'SSRF: private IP must be rejected.' );
		w2w_assert_true( $scraper->validate_source( self::$sample_post_html ), 'Raw HTML with <article> should pass validation.' );

		// 4. HTML Parsing into W2W_Post_DTO.
		$posts = $scraper->fetch_posts( self::$sample_post_html );
		w2w_assert_equals( 1, count( $posts ), 'Scraper should return an array with exactly 1 post DTO.' );

		$dto = $posts[0];
		w2w_assert_true( $dto instanceof W2W_Post_DTO, 'Result must be instance of W2W_Post_DTO.' );
		w2w_assert_equals( 'What to Pack for 3 Days in Vegas: Ultimate Outfit Guide', $dto->title, 'Title must be extracted from JSON-LD headline.' );
		w2w_assert_equals( 'packing-list-for-las-vegas-vegas-outfits', $dto->slug, 'Slug must be extracted from canonical URL.' );
		w2w_assert_equals( 'https://www.wheretonau.com/post/packing-list-for-las-vegas-vegas-outfits', $dto->original_url, 'Original URL must match.' );
		w2w_assert_equals( 'Naureen Chhipa', $dto->author_name, 'Author must be extracted from JSON-LD.' );
		w2w_assert_equals( '2026-07-19 19:32:30', $dto->date_published, 'Publication date must be formatted to MySQL datetime.' );

		// Categories and Tags:
		w2w_assert_equals( array( 'Lifestyle', 'Travel' ), $dto->categories, 'Categories must be extracted from post categories section.' );
		w2w_assert_equals( array( 'Vegas' ), $dto->tags, 'Tags must be extracted from post tags section.' );

		// Image normalization:
		w2w_assert_equals(
			'https://static.wixstatic.com/media/a5b892_hero~mv2.jpg',
			$dto->featured_image_url,
			'Featured image must be normalized to full-res original without dynamic fill params.'
		);

		// Article body & content extraction:
		w2w_assert_contains( 'Putting together the perfect packing list', $dto->content, 'Article content must include body text.' );
		w2w_assert_contains( 'What to Wear During Daytime', $dto->content, 'Article content must include h2 heading.' );

		// Wow-image component converted to standard <img> tag:
		w2w_assert_contains( 'https://static.wixstatic.com/media/a5b892_casino_walk~mv2.jpg', $dto->content, 'wow-image must be converted into standard img tag with full-res original URL.' );

		// Content image normalization:
		w2w_assert_contains( 'https://static.wixstatic.com/media/a5b892_shoes~mv2.jpg', $dto->content, 'Inline img tags must have dynamic suffixes stripped.' );

		// Purged elements:
		w2w_assert_false( strpos( $dto->content, 'ShareButtons' ), 'Share buttons must be removed from body content.' );
		w2w_assert_false( strpos( $dto->content, 'Comments Section' ), 'Footer / comments section must be removed from body content.' );

		// SEO Meta:
		w2w_assert_equals( 'What to Pack for 3 Days in Vegas: Ultimate Outfit Guide', $dto->seo_meta['meta_title'], 'SEO meta_title must match.' );
		w2w_assert_contains( 'Planning a trip to the Strip', $dto->seo_meta['meta_description'], 'SEO description must match.' );
	}
}

// Execute test suite.
( new W2W_Test_Source_Scraper() )->run();
