# Minimalio SEO

Lightweight SEO for [Minimalio](https://minimalio.org) Premium users: titles, meta descriptions, robots, canonical URLs, Open Graph, structured data and XML sitemaps. Only what search engines and social networks actually use.

Requires the Minimalio theme and the Minimalio Premium plugin. See [readme.txt](readme.txt) for features and developer filters.

## Install

Download `minimalio-seo.zip` from the [latest release](https://github.com/minimalio-theme/minimalio-seo/releases/latest) and upload it in **Plugins → Add New → Upload Plugin**. Later versions appear in **Dashboard → Updates**.

Use the release zip, not GitHub's "Source code" download: that one unpacks into a differently named folder, which WordPress would treat as a separate plugin.

## Releasing a new version

1. Bump the version in `minimalio-seo.php` (the `Version:` header and `MINIMALIO_SEO_VERSION`) and in `readme.txt` (`Stable tag:`), and add a `= x.y.z =` changelog entry to `readme.txt`.
2. Commit and push, then tag:

   ```sh
   git tag v1.2.0
   git push origin main v1.2.0
   ```

3. The **Release** GitHub Action checks that the versions match, builds `minimalio-seo.zip` and `update.json`, and publishes the release. Sites see the update within about 12 hours (immediately via **Dashboard → Updates → Check again**).
