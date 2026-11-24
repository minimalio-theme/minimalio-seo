=== Minimalio SEO ===
Contributors: minimalio
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Lightweight SEO for Minimalio Premium users. Only what search engines and social networks actually use.

== Description ==

Requires the Minimalio theme and the Minimalio Premium plugin (minimalio-portfolio).

* SEO title and meta description per post, page, portfolio item and category, with a live Google preview
* Title templates with variables: %title%, %sitename%, %tagline%, %sep%, %page%
* Robots: index/noindex and nofollow per item, per content type and for author/date archives
* Canonical URLs (automatic, including pagination, with manual override)
* Open Graph + X/Twitter card (title, description, image with sizes) for Facebook, LinkedIn, Pinterest, WhatsApp, X…
* JSON-LD structured data: Organization, LocalBusiness or Person (logo, social profiles, phone, address), WebSite, WebPage, BlogPosting, BreadcrumbList
* XML sitemap (WordPress core sitemap, with noindexed content excluded, lastmod added and images listed for Google Images)
* Google, Bing, Pinterest and Facebook (Meta) site verification
* Attachment page redirects

Deliberately left out (not used by Google): meta keywords, focus keywords and "SEO scores", readability analysis, sitemap priority/changefreq, and the sitelinks search box.

Settings: "SEO - Minimalio" in the main admin menu, directly below Settings.

If another SEO plugin (Yoast, Rank Math, AIOSEO, SEOPress, The SEO Framework) is active, front-end output is disabled to avoid duplicate tags.

Jetpack: Minimalio SEO takes priority. Jetpack's Open Graph tags, Twitter cards, SEO Tools (titles and descriptions), sitemap module (while the Minimalio SEO sitemap is on) and verification tags for Google, Bing, Pinterest and Facebook (when set here) are switched off. All other Jetpack features keep working. Titles, meta descriptions and "hide from search engines" settings entered earlier in Jetpack SEO are still used while the matching Minimalio SEO field is empty, even after Jetpack is removed.

WordPress.com: private sites (and "Coming soon" or "Discourage search engines") get no sitemap and no index directives.

Translations: languages/minimalio-seo.pot is the template. Put minimalio-seo-{locale}.mo files (and the JSON files for the block editor panel) in the languages folder.

== Updates ==

Updates are published as GitHub releases (https://github.com/minimalio-theme/minimalio-seo/releases) and appear in Dashboard > Updates like any other plugin, including automatic updates.

== Developer filters ==

minimalio_seo_title, minimalio_seo_description, minimalio_seo_robots, minimalio_seo_canonical,
minimalio_seo_image, minimalio_seo_head_tags, minimalio_seo_schema_graph, minimalio_seo_breadcrumbs,
minimalio_seo_post_types, minimalio_seo_taxonomies, minimalio_seo_sitemap_images

== Changelog ==

= 1.1.0 =
* New: updates from GitHub releases in the WordPress dashboard.
* New: settings reorganized into General, Content Types, Sitemap, Structured Data and Verification tabs.
* New: Contact & location (phone and address); organizations with an address are marked up as LocalBusiness.
* New: images in the XML sitemap (featured images and images in the content, including the Minimalio gallery).
* New: Facebook (Meta) domain verification.
* New: Jetpack compatibility. Minimalio SEO takes priority over Jetpack's Open Graph, SEO Tools, sitemap and verification output, and keeps using titles, descriptions and noindex settings entered earlier in Jetpack SEO.
* New: translation template (languages/minimalio-seo.pot).
* Fix: WordPress.com private sites are no longer treated as public (no sitemap, no index directives).
* Fix: spacing of radio buttons on the settings page.
* Requires WordPress 5.8 or later.

= 1.0.0 =
* Initial release.
