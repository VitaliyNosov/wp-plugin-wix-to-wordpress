<?php
/**
 * Unit Test: Content Processor & Wix HTML Normalizer.
 *
 * @package WixToWordPressMigrator
 */

// 1. Verify autoloader loads W2W_Content_Processor
w2w_assert_true( class_exists( 'W2W_Content_Processor' ), 'Autoloader should load W2W_Content_Processor' );

$processor = new W2W_Content_Processor();

// 2. Test URL Normalization
$dynamic_wix_url = 'https://static.wixstatic.com/media/9d8ed5_b3d5b78b05fc4948a43f88f1a1e0b571~mv2.jpg/v1/fill/w_800,h_600,al_c,q_85,enc_auto/9d8ed5_b3d5b78b05fc4948a43f88f1a1e0b571~mv2.jpg';
$expected_original = 'https://static.wixstatic.com/media/9d8ed5_b3d5b78b05fc4948a43f88f1a1e0b571~mv2.jpg';

w2w_assert_equals(
	$expected_original,
	$processor->normalize_wix_image_url( $dynamic_wix_url ),
	'Dynamic Wix URL should be normalized to high-res base URL'
);

// Test non-Wix URL untouched
$non_wix_url = 'https://example.com/uploads/photo.png';
w2w_assert_equals(
	$non_wix_url,
	$processor->normalize_wix_image_url( $non_wix_url ),
	'Non-Wix URLs must remain untouched'
);

// 3. Test Image Extraction
$sample_html = '
	<div data-mesh-id="comp-123">
		<p>Some text</p>
		<img src="https://static.wixstatic.com/media/abc_123~mv2.png/v1/fill/w_400,h_300/abc_123~mv2.png" alt="First" />
		<img src="https://example.com/banner.jpg" alt="Second" />
		<img src="https://static.wixstatic.com/media/abc_123~mv2.png/v1/fill/w_800,h_600/abc_123~mv2.png" alt="Duplicate" />
	</div>
';

$extracted = $processor->extract_image_urls( $sample_html );
w2w_assert_equals( 2, count( $extracted ), 'Should extract exactly 2 unique normalized images' );
w2w_assert_contains( 'https://static.wixstatic.com/media/abc_123~mv2.png', $extracted, 'Should contain normalized Wix image' );
w2w_assert_contains( 'https://example.com/banner.jpg', $extracted, 'Should contain external banner' );

// 4. Test HTML Cleaning (Wix artifacts removal)
$dirty_html = '
	<script>alert("hack");</script>
	<div data-mesh-id="wix-box-01" data-testid="inline-content" style="width: 980px; position: absolute;">
		<p>Valid paragraph content.</p>
		<p>&nbsp;</p>
		<p></p>
		<p><br></p>
		<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="560" height="315"></iframe>
	</div>
';

$cleaned = $processor->clean_html( $dirty_html );

w2w_assert_false( strpos( $cleaned, '<script>' ), 'Scripts must be removed' );
w2w_assert_false( strpos( $cleaned, 'data-mesh-id' ), 'data-mesh-id must be removed' );
w2w_assert_false( strpos( $cleaned, 'data-testid' ), 'data-testid must be removed' );
w2w_assert_false( strpos( $cleaned, 'width: 980px' ), 'Hardcoded width must be removed' );
w2w_assert_false( strpos( $cleaned, '<p>&nbsp;</p>' ), 'Empty spacers must be removed' );
w2w_assert_contains( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', $cleaned, 'YouTube iframe must be normalized to clean URL' );

// 5. Test Image URL Replacement (ensuring /v1/fill/... dynamic tails are completely stripped and responsive styles applied)
$html_before = '<p><img src="https://static.wixstatic.com/media/img01~mv2.jpg/v1/fill/w_500,h_500,al_c,q_85,enc_auto/img01~mv2.jpg" alt="Photo" /><img src="https://static.wixstatic.com/media/img02~mv2.png?originUrl=test" /></p>';
$url_mapping = array(
	'https://static.wixstatic.com/media/img01~mv2.jpg' => 'http://example.org/wp-content/uploads/2026/10/img01.jpg',
	'https://static.wixstatic.com/media/img02~mv2.png' => 'http://example.org/wp-content/uploads/2026/10/img02.png',
);

$html_after = $processor->replace_image_urls( $html_before, $url_mapping );
w2w_assert_contains( 'http://example.org/wp-content/uploads/2026/10/img01.jpg', $html_after, 'External Wix image should be replaced with local WordPress URL' );
w2w_assert_false( strpos( $html_after, '/v1/fill/' ), 'Dynamic Wix crop/fill suffix must NOT remain attached to local URL' );
w2w_assert_contains( 'style="max-width: 100%; height: auto;"', $html_after, 'Responsive inline style must be applied to prevent container overflow' );
w2w_assert_contains( 'class="aligncenter size-full', $html_after, 'WordPress image classes must be added to replaced images' );

// 5b. Test standalone image wrapping in semantic <figure class="wp-block-image"> and attachment ID injection
$standalone_html = '<p><img src="https://static.wixstatic.com/media/hero_img~mv2.jpg" alt="Hero Banner" /></p>';
$standalone_mapping = array(
	'https://static.wixstatic.com/media/hero_img~mv2.jpg' => array(
		'url'           => 'http://example.org/wp-content/uploads/2026/10/hero_img.jpg',
		'attachment_id' => 99,
	),
);
$standalone_after = $processor->replace_image_urls( $standalone_html, $standalone_mapping );
w2w_assert_contains( '<figure class="wp-block-image size-full">', $standalone_after, 'Standalone image in paragraph must be converted to WordPress standard figure block' );
w2w_assert_contains( 'wp-image-99', $standalone_after, 'wp-image-99 class must be injected when attachment_id is provided' );
w2w_assert_contains( 'max-width: 100%', $standalone_after, 'max-width style must be preserved on figure image' );

// 6. Test Data-Src extraction and skipping inline base64 data URIs
$html_with_data_src = '
	<div>
		<img src="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=" data-src="https://static.wixstatic.com/media/lazy_img~mv2.jpg" />
	</div>
';
$lazy_extracted = $processor->extract_image_urls( $html_with_data_src );
w2w_assert_equals( 1, count( $lazy_extracted ), 'Should extract image from data-src while ignoring inline data URI' );
w2w_assert_contains( 'https://static.wixstatic.com/media/lazy_img~mv2.jpg', $lazy_extracted, 'Normalized lazy loaded image must be present' );

