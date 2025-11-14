# Edible Trustpilot Fetcher

A WordPress plugin that automatically imports Trustpilot reviews to your WordPress site.

## Version: 1.0.12

## What It Does

This plugin helps you display Trustpilot reviews on your WordPress website by:

- **Importing reviews automatically** from Trustpilot business pages
- **Creating WordPress posts** for each business and review
- **Keeping reviews up to date** with scheduled updates
- **Managing everything** through a simple admin interface

## Features

- ✅ **Easy Setup** - Just add a Trustpilot URL and the plugin does the rest
- ✅ **Automatic Updates** - Reviews are refreshed automatically
- ✅ **Customizable** - Control update frequency and rate limiting
- ✅ **Safe & Respectful** - Built-in delays to avoid overwhelming Trustpilot
- ✅ **Admin Dashboard** - Manage everything from WordPress admin

## Installation

1. Click the **"<> Code"** button on this GitHub page
2. Select **"Download ZIP"**
3. Upload the ZIP file to your WordPress site via **Plugins > Add New > Upload Plugin**
4. Activate the plugin

## Updates

This plugin supports automatic updates via the [Git Updater](https://github.com/afragen/git-updater) plugin:

1. After installing the plugin as described above, install the [Git Updater](https://github.com/afragen/git-updater/releases/) plugin
2. Activate it and click 'Activate Free License' (no license required)
3. The plugin will automatically detect this repository and offer updates

## Quick Start

### Adding Your First Business

1. Go to **Trustpilot Review Fetcher > Add Business** in your WordPress admin
2. Enter a Trustpilot review URL (e.g., `https://www.trustpilot.com/review/example.com`)
3. Click "Add Business"

That's it! The plugin will automatically:
- Import the business information
- Download the latest reviews
- Create WordPress posts for everything
- Set up automatic updates

### Managing Settings

Go to **Trustpilot Review Fetcher > Settings** to configure:

- **Scraping Frequency** - How often to check for new reviews (default: 72 hours)
- **Rate Limit** - Delay between scraping multiple businesses (default: 5 seconds)
- **Debug Mode** - Enable detailed logging for troubleshooting

The plugin fetches the ~20 most recent reviews from each business (Trustpilot page 1). Duplicate reviews are automatically skipped.

## What Gets Created

When you add a business, the plugin creates:

- **Business Post** - Contains business name, rating, and total review count
- **Review Posts** - Individual posts for each review with rating, author, and content
- **Automatic Linking** - Reviews are automatically connected to their business

## Displaying Trustpilot Stats

Use the `[trustpilot_stats]` shortcode to display business ratings and review counts anywhere on your site.

### Shortcode Usage

The shortcode supports three output types:

```
[trustpilot_stats slug="faxonline" type="rating"]  // Outputs: 4.7
[trustpilot_stats slug="payperfax" type="count"]   // Outputs: 1,234
[trustpilot_stats slug="microsoft" type="stars"]   // Outputs: ★★★★⯪ (in Trustpilot green)
```

**Parameters:**
- `slug` - Business slug (derived from domain: `payperfax.com` → `payperfax`, `faxonline.app` → `faxonline`)
- `type` - Output type: `rating` (numeric), `count` (review count), or `stars` (star characters)

### Using in WordPress Loops

When displaying business posts in a loop, the `slug` parameter is optional:

```php
<?php
$businesses = get_posts(array('post_type' => 'tp_businesses'));
foreach ($businesses as $post) {
    setup_postdata($post);
    ?>
    <h2><?php the_title(); ?></h2>
    <p>Rating: <?php echo do_shortcode('[trustpilot_stats type="rating"]'); ?></p>
    <p><?php echo do_shortcode('[trustpilot_stats type="stars"]'); ?></p>
    <p><?php echo do_shortcode('[trustpilot_stats type="count"]'); ?> reviews</p>
    <?php
}
wp_reset_postdata();
?>
```

The shortcode automatically detects the current business post and uses its data.

## Automatic Review Updates

The plugin automatically checks for new reviews based on your frequency setting. You don't need to do anything - it runs in the background using WordPress's built-in scheduling system.

## Support

For support and feature requests, please [create an issue](https://github.com/ediblesites/edible-trustpilot-fetcher/issues) here on Github.

---

**Made this for us at [Edible Sites](https://ediblesites.com), sharing it with you ❤️** We're also behind:

* [PayPerFax.com](https://payperfax.com/), a pay-per-use online fax service, and
* [Faxbeep.com](https://faxbeep.com), a free fax testing service