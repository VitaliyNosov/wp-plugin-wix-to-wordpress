=== Wix to WordPress Post Migrator ===
Contributors: vitaliynosov
Donate link: https://github.com/VitaliyNosov/wp-plugin-wix-to-wordpress
Tags: wix, migration, import, wix to wordpress, sitemap, scraper, rss, blog, redirect, media
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Migrate Wix blog posts, high-resolution media assets, categories, tags, and SEO metadata into WordPress via Sitemap XML, Single Post Scraper, or RSS Feed.

== Description ==

**Wix to WordPress Post Migrator** is an enterprise-grade migration solution built to import blog posts, full-resolution media assets, author profiles, categories, tags, and SEO metadata from any Wix website into WordPress.

### The Problem with Standard Wix RSS Feeds
By default, Wix Cloudflare and blogging engine cap `blog-feed.xml` at **only 20 recent posts**. If your Wix site has 50, 100, or 500 articles, standard RSS importers miss the vast majority of your content!

### The Solution: 3 Intelligent Ingestion Modes
Wix to WordPress Post Migrator solves this problem by providing three automated ingestion channels:

1. **Full Sitemap XML (Recommended for Complete Archive Migration)**
   * Ingest your complete blog archive by entering your Wix sitemap (e.g. `https://yourdomain.com/blog-posts-sitemap.xml` or `sitemap.xml`).
   * Bypasses the 20-post limit to retrieve all published historical articles (65+ posts).
   * Parses nested `<sitemapindex>` files and fetches complete article content dynamically in manageable batches.
2. **Single Post URL (Direct Rich Scraper)**
   * Ingest or re-import a specific article by simply pasting its URL (e.g. `https://yourdomain.com/post/my-article-title`).
   * Automatically extracts Schema.org JSON-LD (`BlogPosting`/`Article`), OpenGraph meta, and clean semantic HTML from `<article>`.
   * Automatically converts proprietary Wix `<wow-image>` components into native, full-resolution WordPress images.
3. **Standard RSS Feed (Fast Syndication)**
   * Supports standard RSS 2.0 XML feeds (`blog-feed.xml`) for quick synchronization of the latest 20 posts with zero scraping overhead.

### Key Enterprise Features

* **Lossless High-Resolution Media Ingestion**: Downloads full-resolution original images from Wix media CDN (`static.wixstatic.com`), imports them into the WordPress Media Library, sets featured images, and updates embedded URLs.
* **MD5 Media Deduplication**: Prevents duplicate file uploads across migration runs by tracking MD5 file hashes.
* **Chunked Asynchronous AJAX Runner**: Imports posts in adjustable batches (2, 3, or 5 posts per request) to prevent server timeouts and memory exhaustion on shared hosting.
* **Pause & Resume Controls**: Pause ongoing migrations and resume without losing progress.
* **1-Click Atomic Batch Rollback**: Every migration batch receives a unique UUID. If anything looks off, click "Undo" to permanently remove imported posts and associated media attachments in one click.
* **Automated 301 Redirect Rules Generator**: Exports exact mapping rules in `.htaccess` (Apache), Nginx config syntax, and CSV format to preserve your SEO rankings and domain authority.
* **System Health & Compatibility Check**: Audits PHP version, cURL, SimpleXML, DOMDocument, file upload sizes, and timeout limits before migration starts.
* **Live Activity Stream & Persistent Logs**: Monitor migration steps in real-time and review diagnostic logs.

== Installation ==

1. Upload the `wix-to-wp-plugin` folder to the `/wp-content/plugins/` directory, or install the `.zip` archive via the WordPress Plugins menu.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **Tools → Wix to WP** in your WordPress admin menu.
4. Paste your Wix source URL (Sitemap XML, Single Post, or RSS feed), select your target WordPress author, and click **Fetch & Preview Posts**.
5. Review the detected articles in the preview table and click **Start Migration Process**.

== Frequently Asked Questions ==

= Why did my Wix RSS feed only show 20 posts? =
Wix limits public RSS feeds (`blog-feed.xml`) to the 20 most recent entries. To migrate your entire post archive, use your Wix blog sitemap URL instead (e.g. `https://yourdomain.com/blog-posts-sitemap.xml`). The plugin will automatically detect the sitemap and import all historical posts!

= How does the plugin handle images hosted on Wix? =
The plugin automatically detects images hosted on Wix's CDN (`static.wixstatic.com` and `<wow-image>` elements), strips dynamic downscaling parameters (`/v1/fit/w_740...`), downloads the original high-resolution graphic into your WordPress Media Library, and sets the featured image.

= Can I undo an import if something goes wrong? =
Yes! Every migration run is assigned a unique Batch UUID. Head to the **Undo (Rollback)** tab, enter or select the batch, and click **Delete Batch & Associated Media** to completely revert the changes.

= What SEO plugins are supported? =
The plugin natively maps Wix SEO titles and meta descriptions to **Yoast SEO**, **Rank Math**, **All in One SEO (AIOSEO)**, and standard custom fields.

== Screenshots ==

1. Quick-Start Guide and Content Source configuration screen.
2. Interactive Post Selection and Preview table with thumbnails.
3. Live migration runner with progress bar, counters, and pause/resume controls.
4. One-click Batch Rollback management screen.
5. Automated 301 Redirect Rules generator (Apache, Nginx, CSV).
6. System Health & Environment diagnostic checker.

== Changelog ==

= 1.0.0 =
* Initial enterprise release.
* Support for Full Sitemap XML ingestion (`blog-posts-sitemap.xml`) bypassing the 20-post RSS limit.
* Support for Single Post direct scraping with Schema.org JSON-LD and OpenGraph metadata extraction.
* Support for Standard RSS 2.0 feed parsing.
* Proprietary Wix `<wow-image>` component parser and high-resolution media converter.
* Chunked AJAX migration engine with pause, resume, and real-time activity stream.
* Atomic Batch Rollback manager.
* 301 Redirect rule export in Apache `.htaccess`, Nginx, and CSV formats.
* In-plugin mini-documentation with 1-click live example links.
* 100% unit test coverage across 15 suites.
