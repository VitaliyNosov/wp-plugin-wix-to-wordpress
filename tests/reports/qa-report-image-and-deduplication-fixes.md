# Quality Assurance Report: Content Media Ingestion & Chunk Deduplication Fixes

> **Report:** QA Verification & Regression Audit  
> **Plugin Version:** `1.0.0`  
> **Date:** `2026-10-06`  
> **PHP Environment:** `PHP 7.4.21 (cli) / Windows x64`  
> **Overall Status:** 🟢 **100% PASS (15 test suites, 286 assertions, 0 failures)**

---

## 1. Executive Summary

During testing of bulk migration with full blog datasets (65 posts from `wheretonau.com/blog-posts-sitemap.xml`), two critical issues were reported and diagnosed:
1. **Duplicate Post Creation on Full Ingestion**:
   - The first post in the preview list ("Best Day Tours From Paris And Top Inside The City Trips") was duplicated across every chunk execution (~22 times), while posts at the start of subsequent chunks (posts 3, 6, 9, 12, etc.) were skipped.
2. **Missing In-Content Photos & Broken Local Image URLs**:
   - In-content images were failing to render or appearing missing in WordPress articles, with only the main featured image (post thumbnail) displaying properly.

Both root causes were analyzed, isolated, fixed, and covered with dedicated unit tests.

---

## 2. Root Cause Analysis & Solutions Implemented

### 2.1. Chunk Index Truncation Bug in AJAX Controller
* **Root Cause**: The client-side JavaScript (`src/js/admin.js`) serializes array payloads using `JSON.stringify()`, sending `indices="[3,4,5]"`. The backend `W2W_Ajax_Handler::ajax_import_chunk` used `explode(',', $_POST['indices'])` followed by `array_map('intval', ...)`. Because the first element was `'[3'`, PHP's `intval('[3')` evaluated to `0`. Consequently, post index `0` was processed on every chunk, while index `3` was lost.
* **Resolution in [`W2W_Ajax_Handler`](file:///c:/Users/user/Desktop/wp-kivicare-clinic-management-system/wp-content/plugins/wix-to-wp-plugin/includes/ajax/class-ajax-handler.php#L216-L235)**: Replaced naive `explode()` with safe `json_decode()` along with whitespace and square bracket sanitization fallbacks. Verified with both JSON strings (`"[1]"`), comma strings (`"0, 1"`), and arrays.

### 2.2. Broken Local URLs Due to Wix Resizing Tails
* **Root Cause**: Wix content embeds images with dynamic resizing suffixes (`/v1/fill/w_1920,h_2072,al_c,q_90,...`). When `W2W_Content_Processor::replace_image_urls()` ran `str_replace()`, it replaced only the normalized base URL (`~mv2.jpg`), leaving `/v1/fill/...` appended to local WordPress media URLs (`http://localhost/wp-content/uploads/img.jpg/v1/fill/...`), resulting in 404 errors.
* **Resolution in [`W2W_Content_Processor`](file:///c:/Users/user/Desktop/wp-kivicare-clinic-management-system/wp-content/plugins/wix-to-wp-plugin/includes/engine/class-content-processor.php#L155-L167)**: Implemented regex replacement matching `#{escaped_base}(/v1/[^"'\s>]+|\?[^"'\s>]*)?#i` so all dynamic sizing suffixes and query parameters are cleanly stripped, leaving pristine local image paths. Also enhanced `extract_image_urls` to support `data-src` and ignore inline base64 `data:` URIs.

### 2.3. Media Downloader SSRF DNS False-Positives on Windows
* **Root Cause**: `W2W_Media_Downloader` used WordPress core `wp_http_validate_url()` on image URLs, which fails intermittently on local Windows environments when resolving external CDN hostnames.
* **Resolution in [`W2W_Media_Downloader`](file:///c:/Users/user/Desktop/wp-kivicare-clinic-management-system/wp-content/plugins/wix-to-wp-plugin/includes/engine/class-media-downloader.php#L72)**: Replaced with `W2W_Environment_Check::validate_safe_url()`, which provides reliable SSRF and private-network protection without DNS timeout failures on Windows.

### 2.4. Deduplication ID Retrieval in `W2W_Post_Writer` & `W2W_Media_Downloader`
* **Root Cause**: Both classes called `get_posts( array( 'fields' => 'ids', ... ) )` and then attempted `$posts[0]->ID`. Because `fields => ids` returns an array of integer IDs, reading `->ID` evaluated to `null`, causing deduplication checks to fail and insert duplicate records.
* **Resolution in [`W2W_Post_Writer`](file:///c:/Users/user/Desktop/wp-kivicare-clinic-management-system/wp-content/plugins/wix-to-wp-plugin/includes/engine/class-post-writer.php#L40-L190) & [`W2W_Media_Downloader`](file:///c:/Users/user/Desktop/wp-kivicare-clinic-management-system/wp-content/plugins/wix-to-wp-plugin/includes/engine/class-media-downloader.php#L165-L200)**: Corrected ID extraction to handle both integer arrays and object arrays (`is_object($first) ? (int)$first->ID : (int)$first`). Added multi-strategy lookup in `W2W_Post_Writer`: by Wix ID, by original URL (`find_existing_by_url`), and by post slug (`find_existing_by_slug`).

### 2.5. Responsive Image Scaling & Horizontal Overflow Prevention
* **Root Cause**: Wix stores full-resolution source images (often 3000–4500px wide). In WordPress themes without global CSS resets (`img { max-width: 100%; height: auto; }`) and in Gutenberg canvas views, bare `<img>` tags render at their intrinsic resolution, causing wide horizontal scrollbars and breaking column containers.
* **Resolution in [`W2W_Content_Processor`](file:///c:/Users/user/Desktop/wp-kivicare-clinic-management-system/wp-content/plugins/wix-to-wp-plugin/includes/engine/class-content-processor.php#L140-L265)**:
  - Passed `array('url' => $local_url, 'attachment_id' => $attachment_id)` from [`W2W_Migration_Coordinator`](file:///c:/Users/user/Desktop/wp-kivicare-clinic-management-system/wp-content/plugins/wix-to-wp-plugin/includes/engine/class-migration-coordinator.php#L130-L150).
  - Attached standard WordPress class `wp-image-{$attachment_id}` to enable native WP responsive `srcset` generation.
  - Injected safety inline style `style="max-width: 100%; height: auto;"` into every migrated `<img>` tag to guarantee zero horizontal breakout regardless of active theme styling.
  - Wrapped standalone images into standard semantic `<figure class="wp-block-image size-full">` Gutenberg-compatible blocks.
  - Stripped parent fixed-width declarations (`width: \d{3,4}px` and `min-width: \d{3,4}px`) in `clean_html()`.
  - Fixed format string escaping `100%%` in [`W2W_Source_Scraper`](file:///c:/Users/user/Desktop/wp-kivicare-clinic-management-system/wp-content/plugins/wix-to-wp-plugin/includes/sources/class-source-scraper.php#L225) to avoid PHP `sprintf` warnings.

### 2.6. Race Condition Prevention, Double Trigger Lock & Activity Stream Data
* **Root Cause**:
  1. The "Start Migration" button (`#w2w-btn-start-migration`) in `src/js/admin.js` did not check `if (state.isMigrating) return;` and never set `btnStart.disabled = true`. Double-clicking or clicking twice triggered two concurrent asynchronous chunk loops. Both loops processed chunks in parallel, incremented stats (`106` imported out of `65`), caused database race conditions inserting duplicate posts (`slug-2`), and logged two concurrent completion events at `[16:28:39]`.
  2. `W2W_Migration_Coordinator::process_single_post()` omitted `'title'` and `'media_count'` in its return array, causing the Activity Stream to log generic `"Post"` and the UI media stat counter to remain at `0`.
* **Resolution**:
  - **Frontend Lock**: Added `if (state.isMigrating) return;`, immediate `btnStart.disabled = true;`, input control locking, and spinner indicator during execution. Restored in `finally`.
  - **Backend Mutex Lock**: Implemented transient mutex lock (`w2w_lock_post_{hash}`) in [`W2W_Post_Writer::write_post`](file:///c:/Users/user/Desktop/wp-kivicare-clinic-management-system/wp-content/plugins/wix-to-wp-plugin/includes/engine/class-post-writer.php#L50-L75) to serialize concurrent post writes and re-check existence.
  - **Enhanced Deduplication**: Added URL slash normalization (`candidates` check) and 4th fallback match by exact post title (`find_existing_by_title`).
  - **Real-Time Data**: Passed `'title' => $dto->title` and `'media_count'` in coordinator results and updated title during lazy scraping in `W2W_Ajax_Handler`.

---

## 3. Test Suite Metrics

```
============================================================
  Wix to WordPress Post Migrator - CLI Test Runner
============================================================

• Running suite: test-ajax-handler.php                    [ PASS ]
• Running suite: test-autoloader.php                      [ PASS ]
• Running suite: test-content-processor.php               [ PASS ]
• Running suite: test-environment-check.php               [ PASS ]
• Running suite: test-media-importer.php                  [ PASS ]
• Running suite: test-migration-coordinator.php           [ PASS ]
• Running suite: test-post-dto.php                        [ PASS ]
• Running suite: test-post-writer.php                     [ PASS ]
• Running suite: test-rollback.php                        [ PASS ]
• Running suite: test-seo-handler.php                     [ PASS ]
• Running suite: test-source-manager.php                  [ PASS ]
• Running suite: test-source-rss.php                      [ PASS ]
• Running suite: test-source-scraper.php                  [ PASS ]
• Running suite: test-source-sitemap.php                  [ PASS ]
• Running suite: test-taxonomy-manager.php                [ PASS ]

------------------------------------------------------------
Suites executed: 15
Assertions:      286
Passed:          286
Failed:          0
Time:            0.0553 seconds
------------------------------------------------------------

✅ ALL TESTS PASSED SUCCESSFULLY! (100% PASS)
```

---

## 4. Verification Checklist

- [x] `test-ajax-handler.php`: Validates JSON string `indices` (`"[1]"`), comma string (`"0, 1"`), and integer arrays.
- [x] `test-content-processor.php`: Validates complete stripping of `/v1/fill/...` suffixes, `data-src` extraction, responsive styling (`max-width: 100%`), and `<figure>` block wrapping.
- [x] `test-media-importer.php`: Validates hash and URL deduplication with `'fields' => 'ids'`.
- [x] `test-post-writer.php`: Validates 4-way deduplication (Wix ID, Original URL with/without trailing slash, Slug, Exact Title) and concurrency lock.
- [x] `npm run build`: Production Sass (`admin.css`) and esbuild bundle (`admin.js`) compiled cleanly.
- [x] PHP Syntax Check: 33/33 PHP files validated with zero syntax errors.
