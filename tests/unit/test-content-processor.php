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

// 5. Test Image URL Replacement
$html_before = '<p><img src="https://static.wixstatic.com/media/img01~mv2.jpg/v1/fill/w_500,h_500/img01~mv2.jpg" /></p>';
$url_mapping = array(
	'https://static.wixstatic.com/media/img01~mv2.jpg' => 'http://example.org/wp-content/uploads/2026/10/img01.jpg',
);

$html_after = $processor->replace_image_urls( $html_before, $url_mapping );
w2w_assert_contains( 'http://example.org/wp-content/uploads/2026/10/img01.jpg', $html_after, 'External Wix image should be replaced with local WordPress URL' );
