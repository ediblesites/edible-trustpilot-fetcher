# Edible Trustpilot Fetcher

A WordPress plugin that scrapes Trustpilot reviews and displays them on your WordPress site using custom post types and taxonomies.

## Version: 1.0.11

## Features

- **Automatic Review Scraping**: Scrape Trustpilot reviews and convert them to WordPress posts
- **Custom Post Types**: Separate post types for businesses (`tp_businesses`) and reviews (`tp_reviews`)
- **Taxonomy Linking**: Reviews are linked to businesses via taxonomy terms
- **Admin Dashboard**: Complete admin interface for managing businesses and settings
- **Frequency Control**: Configurable scraping frequency to avoid overwhelming Trustpilot
- **Review Limits**: Set maximum number of reviews to store per business (default: 5)
- **Scheduled Scraping**: Automatic re-scraping via WordPress cron jobs
- **REST API**: Full REST API for programmatic access
- **Rate Limiting**: Built-in rate limiting to avoid being blocked

## Installation

Download [here](https://github.com/ediblesites/edible-trustpilot-fetcher/archive/refs/heads/master.zip), proceed as usual.

## Updates

This plugin supports automatic updates via the [Git Updater](https://github.com/afragen/git-updater) plugin. To enable automatic updates:

1. Download and install the [Git Updater](https://github.com/afragen/git-updater/releases/) plugin
2. Activate the plugin and click 'Activate Free License' (no license required)
3. Go to **Git Updater > Install Plugin**
4. Add the following details:
   - **Plugin URI**: `ediblesites/edible-trustpilot-fetcher`
   - **Repository Branch**: `master`
   - **Access Token**: Leave empty (not required)
5. Click 'Install Plugin' button

The plugin includes the required header for Git Updater compatibility:
```
GitHub Plugin URI: ediblesites/edible-trustpilot-fetcher
```

## Usage

### Adding a Business

1. Navigate to **Trustpilot Businesses > Add Business**
3. Enter the Trustpilot review URL (e.g., `https://www.trustpilot.com/review/example.com`)
4. Click "Add Business & Start Scraping"

The plugin will:
- Scrape the business page once
- Create a business post with metadata
- Extract and create review posts (limited by your review limit setting)
- Link reviews to the business via taxonomy
- Periodically check Trustpilot for new reviews

### Settings

Configure scraping behavior in **Trustpilot Businesses > Settings**:

- **Maximum Reviews per Business**: How many reviews to store (default: 5)
- **Scraping Frequency**: How often to re-scrape businesses (default: 24 hours)
- **Rate Limit**: Delay between requests (default: 5 seconds)

## Architecture

### Custom Post Types

- **`tp_businesses`**: Stores business information and Trustpilot URLs
- **`tp_reviews`**: Stores individual review data

### Taxonomies

- **`tp_business`**: Links reviews to businesses using business domain as slug

### Data Flow

1. **URL Input** → Business creation
2. **Single Scrape** → Extract business + review data
3. **Business Creation** → Create post with metadata
4. **Review Creation** → Create review posts with taxonomy links
5. **Scheduled Re-scraping** → Update existing businesses based on frequency

## API Endpoints

## Scraping Behavior

### Frequency Control
- Cron job runs hourly
- Each business is only scraped if it's past the configured frequency threshold
- Businesses are skipped if scraped too recently

### Rate Limiting
- Configurable delay between requests
- Built-in cookie and redirect handling
- Automatic retry logic for failed requests

### Data Extraction
- Extracts business metadata (name, rating, total reviews)
- Extracts individual reviews from JSON-LD structured data
- Handles Trustpilot's dynamic content loading

## Development

### Testing
Use the included test scripts:
- `test-add-business.php` - Test business creation via API
- `test-scraper.php` - Test scraper functionality

### File Structure
```
edible-trustpilot-fetcher/
├── assets/
│   └── js/
│       └── admin.js
├── includes/
│   ├── class-trustpilot-admin.php
│   ├── class-trustpilot-api.php
│   ├── class-trustpilot-business-manager.php
│   ├── class-trustpilot-cpt.php
│   └── class-trustpilot-scraper.php
├── docs/
│   └── plan.md
├── edible-trustpilot-fetcher.php
├── README.md
└── test-add-business.php
```

### Hooks and Filters
- `trustpilot_scrape_cron` - Scheduled scraping hook
- `update_option_trustpilot_scraping_frequency` - Frequency setting change

## Troubleshooting

### Common Issues

**"Headers already sent" error**
- Remove any debug `echo` statements from scraper code

**Reviews not linking to businesses**
- Check taxonomy term creation in business manager
- Verify business domain extraction from URLs

**Scraping fails**
- Check rate limiting settings
- Verify Trustpilot URL format
- Check server's cURL configuration

### Debug Mode
Enable WordPress debug logging to see detailed scraping results:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## License

This plugin is proprietary software developed by Edible.

## Support

For support and feature requests, please contact the development team.
