=== Checklist Seokar ===
Contributors: Sajjad Akbari (نام کاربری شما در WordPress.org را اینجا وارد کنید)
Donate link: https://example.com/donate (اختیاری: لینک حمایت مالی)
Tags: seo, checklist, content, publish, editor, metabox, pre-publish, optimize, on-page seo, readability, keyword, Farsi, Persian
Requires at least: 5.5
Tested up to: 6.5 (نسخه‌ای از وردپرس که با آن تست کرده‌اید)
Requires PHP: 7.2
Stable tag: 2.0.0 (آخرین نسخه پایدار شما)
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Provides a real-time SEO and content checklist in the WordPress editor for posts, pages, products, and taxonomies to help optimize content before publishing. Includes configurable checks and Persian language support.

== Description ==

Checklist Seokar adds a helpful meta box to your WordPress editor (supporting Classic Editor and Gutenberg) for various content types like Posts, Pages, WooCommerce Products, Categories, and Tags. This box provides a real-time checklist that analyzes your content based on important SEO and readability factors.

Stop publishing content without essential optimization! This plugin acts as your pre-flight check, ensuring your content meets basic quality standards before going live.

**Key Features:**

*   **Real-time Checklist:** Get instant feedback as you write or edit.
*   **Comprehensive Checks:** Analyzes content length, heading usage, keyword presence (title, description, headings, content, slug), keyword density, internal/external links, image alt texts, featured image, readability (Flesch score approximation for Persian), and text structure (sentence/paragraph length).
*   **Google SERP Preview:** See an approximate preview of how your content might appear in Google search results (Title, URL, Description).
*   **Customizable Meta Fields:** Set your main keyword, related keywords, custom SEO title, and meta description directly within the editor.
*   **Configurable Settings:**
    *   Enable/disable specific checklist items.
    *   Customize thresholds (e.g., minimum content length, keyword density range).
    *   Select which post types and taxonomies the checklist should appear on.
*   **Editor Integration:** Works seamlessly within the Classic Editor and the Block Editor (Gutenberg).
*   **AJAX Powered:** Features like the featured image check update dynamically (where possible).
*   **Lightweight & Focused:** Built with performance in mind.
*   **Translation Ready:** Fully prepared for translation (Persian included by default if .mo file is provided).
*   **Persian Optimized:** Includes basic Persian stop words and readability analysis considerations.

This plugin helps you improve your on-page SEO and content quality systematically, leading to better user engagement and potentially higher search engine rankings.

== Installation ==

**From your WordPress dashboard:**

1.  Navigate to Plugins > Add New.
2.  Search for "Checklist Seokar".
3.  Find the plugin by Sajjad Akbari and click "Install Now".
4.  Activate the plugin through the 'Plugins' menu in WordPress.
5.  Go to Settings > Checklist Seokar to configure the plugin options.

**Manual Installation:**

1.  Download the plugin zip file (checklist-seokar.zip).
2.  Navigate to Plugins > Add New in your WordPress dashboard.
3.  Click the "Upload Plugin" button at the top.
4.  Upload the `checklist-seokar.zip` file you downloaded.
5.  Activate the plugin through the 'Plugins' menu in WordPress.
6.  Go to Settings > Checklist Seokar to configure the plugin options.

**Via FTP:**

1.  Download the plugin zip file and unzip it.
2.  Upload the `checklist-seokar` folder to the `/wp-content/plugins/` directory on your server.
3.  Activate the plugin through the 'Plugins' menu in WordPress.
4.  Go to Settings > Checklist Seokar to configure the plugin options.

== Frequently Asked Questions ==

= Does this plugin replace Yoast SEO or Rank Math? =

No. Checklist Seokar is designed to be a focused pre-publish checklist and content analysis tool working *within* the editor. It complements broader SEO plugins like Yoast SEO or Rank Math, which handle site-wide settings, XML sitemaps, schema markup, etc. You can often use them together, although there might be some overlap in on-page analysis features.

= Does the Readability score work accurately for Persian? =

The Flesch Reading Ease score implementation includes approximations for Persian syllable counting, which is inherently complex. The score should be considered a *relative indicator* rather than an absolute measure. Use it alongside the sentence and paragraph length checks for a better understanding of text complexity.

= How is Keyword Density calculated? =

It's calculated based on the occurrences of the exact main keyword (case-insensitive, as a whole word) divided by the total word count *after* removing common Persian stop words and punctuation. It's an approximate metric.

= Can I customize which checks are performed? =

Yes! Go to Settings > Checklist Seokar > Enabled Checks tab. You can check/uncheck any item to activate or deactivate it.

= Can I choose where the checklist appears? =

Yes. Go to Settings > Checklist Seokar > Content Types tab. You can select the specific post types (like posts, pages, products) and taxonomies (like categories, tags) where you want the checklist to be active.

= Is it compatible with Gutenberg? =

Yes, the plugin is designed to work with both the Classic Editor (including TinyMCE) and the Block Editor (Gutenberg).

= Where do I report bugs or suggest features? =

Please use the plugin's support forum on WordPress.org (link will be available once published) or contact the developer via the Author URI.

== Screenshots ==

1.  The Checklist Seokar meta box in the Block Editor (Gutenberg). (توضیح عکس اول)
2.  Real-time feedback with status icons and messages. (توضیح عکس دوم)
3.  The Google SERP Preview updating based on input. (توضیح عکس سوم)
4.  The plugin's settings page showing general options. (توضیح عکس چهارم)
5.  Settings page: Enabling/disabling specific checks. (توضیح عکس پنجم)
6.  Settings page: Selecting enabled post types and taxonomies. (توضیح عکس ششم)

*(**Note:** You need to actually create these screenshots and upload them to the `assets` directory of your plugin when submitting to WordPress.org. The filenames convention is usually `screenshot-1.png`, `screenshot-2.png`, etc.)*

== Changelog ==

= 2.0.0 =
*   MAJOR UPDATE: Complete rewrite using Settings API.
*   ADD: Settings page (Settings > Checklist Seokar).
*   ADD: Ability to enable/disable specific checklist items.
*   ADD: Ability to select post types and taxonomies for the checklist.
*   ADD: Ability to configure min content length and keyword density ranges.
*   ADD: New Check: External Links.
*   ADD: New Check: Keyword in URL (Slug).
*   ADD: New Check: Image Alt Attributes (Presence & Keyword).
*   ADD: New Check: Text Structure (Sentence & Paragraph Length).
*   ADD: New Check: Readability Score (Flesch Approximation for Persian).
*   IMPROVE: Keyword density calculation now uses Persian stop words.
*   IMPROVE: Internal link detection accuracy using host checking.
*   IMPROVE: Featured image check now uses AJAX for better real-time status (especially in Gutenberg).
*   IMPROVE: Better Gutenberg integration using `wp.data.subscribe`.
*   IMPROVE: Code structure, prefixing, security, and comments.
*   IMPROVE: Refined UI messages and CSS styles.
*   ADD: Added `readme.txt` and `LICENSE.txt`.
*   ADD: Added basic tabbing to settings page.
*   FIX: Various minor bugs and inconsistencies.

= 1.0.0 =
*   Initial release.
*   Features: Content length, Headings, Keyword presence, SERP Preview, Title/Desc meta fields, Basic Overall Score.
*   Support for Posts, Pages, Products, Categories, Tags.
*   Basic real-time updates via JS.

== Upgrade Notice ==

= 2.0.0 =
This is a major update introducing a settings page and many new features. Please review the new settings under Settings > Checklist Seokar after upgrading to configure the checklist according to your needs. Default settings have been applied upon activation.

== Support ==

For support, please visit the [WordPress.org support forum](https://wordpress.org/support/plugin/your-plugin-slug/) (Replace `your-plugin-slug` once published).
