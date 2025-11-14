# Trustpilot Scraper WordPress Plugin - Development Plan

## Overview
A WordPress plugin that scrapes Trustpilot reviews and displays them on a WordPress site. The plugin will fetch review data from Trustpilot's public pages and store/display them in a WordPress-friendly format.

## Core Features

### 1. Review Scraping Engine
- **Web Scraping**: Use PHP cURL or WordPress HTTP API to fetch Trustpilot review pages
- **Data Extraction**: Parse HTML to extract review data including:
  - Review text/content
  - Rating (1-5 stars)
  - Reviewer name
  - Review date
  - Business name
  - Review title (if available)
  - **Aggregate Rating Data**: Extract structured data including:
    - Overall business rating (`ratingValue`)
    - Total review count (`reviewCount`)
    - Best rating possible (typically 5)
    - Worst rating possible (typically 1)
- **Structured Data Parsing**: Parse JSON-LD structured data for aggregate ratings
- **Rate Limiting**: Implement delays between requests to avoid being blocked
- **Error Handling**: Graceful handling of network errors, parsing failures, and blocked requests

### 2. Data Management
- **Business CPT**: Create `tp_businesses` CPT for defining URLs/businesses to scrape
- **Review CPT**: Create `tp_reviews` CPT for individual reviews (not publicly visible or queryable)
- **JSON Metadata**: Store business metadata as JSON in the business CPT post body
- **Custom Fields**: Store review data as post meta fields
- **Data Validation**: Clean and validate scraped data before storage
- **Duplicate Prevention**: Check for existing reviews to avoid duplicates
- **Data Updates**: Scheduled scraping to keep reviews current

### 3. WordPress Integration
- **Admin Interface**: WordPress admin panel for configuration
- **Settings Page**: Configure scraping parameters, business URLs, etc.
- **Shortcodes**: Display reviews using shortcodes like `[trustpilot_reviews]`

### 4. Display Options - Keep for later (we might just implement with post display blocks)
- **Review Grid**: Display reviews in a responsive grid layout
- **Review Slider**: Carousel/slider for featured reviews
- **Rating Display**: Visual star ratings

## Technical Architecture

### File Structure
```
edible-trustpilot-fetcher/
├── edible-trustpilot-fetcher.php          # Main plugin file
├── includes/
│   ├── class-trustpilot-scraper.php       # Core scraping logic
│   ├── class-trustpilot-admin.php         # Admin interface
│   ├── class-trustpilot-display.php       # Frontend display
│   ├── class-trustpilot-cpt.php           # Custom Post Type registration
│   └── class-trustpilot-shortcodes.php    # Shortcode handlers
├── assets/
│   ├── css/
│   │   ├── admin.css                      # Admin styles
│   │   └── frontend.css                   # Frontend styles
│   └── js/
│       ├── admin.js                       # Admin JavaScript
│       └── frontend.js                    # Frontend JavaScript
├── templates/ <- don't implement yet
│   ├── review-grid.php                    # Review grid template
│   ├── review-slider.php                  # Review slider template
│   └── single-review.php                  # Single review template
├── languages/                             # Translation files
├── docs/                                  # Documentation
└── readme.txt                             # Plugin readme
```

### Data Structure

#### Custom Post Type 1: `tp_businesses`
- **Publicly Queryable**: False
- **Show in Admin**: True
- **Supports**: Title, Editor, Custom Fields, Revisions
- **Menu Icon**: dashicons-building
- **Post Title**: URL to business review on Trustpilot <- this is the only thing the user sets to create a new scraping. The plugin subsequently runs on cron to generate the json-filled body.
- **Post Body**: JSON metadata including:
  - Business URL
  - Aggregate rating
  - Total review count
  - Last scraped date 
  - Scraping frequency
  - Display settings

#### Custom Post Type 2: `tp_reviews`
- **Publicly Queryable**: False
- **Show in Admin**: True
- **Supports**: Title, Editor, Custom Fields, Revisions, Excerpt
- **Menu Icon**: dashicons-star-filled
- **Parent Relationship**: Linked to `tp_businesses` via taxonomy

#### Post Field Usage (Maximal use of existing WordPress fields)
- **Post Title**: Review title/headline
- **Post Content**: Full review text
- **Post Excerpt**: Review summary (if available)
- **Post Date**: Original review date from Trustpilot
- **Post Modified**: When the review was scraped/updated

#### Custom Meta Fields (Only essential data not in native fields)
- `business_url`: Unique Trustpilot URL for deduplication
- `business_name`: Name of the business
- `aggregate_rating`: Overall business rating (0-5)
- `total_reviews`: Total number of reviews
- `last_scraped`: Timestamp of last scrape

#### Business-Review Relationship
- **Taxonomy**: `tp_business` taxonomy links reviews to businesses
- **Business slug**: Used as taxonomy term to group reviews by business
- **Cleaner code**: Taxonomy provides better performance and WordPress integration than meta field relationships

#### Business JSON Structure (stored in post body)
```json
{
  "business_url": "https://www.trustpilot.com/review/example.com",
  "business_name": "Example Business",
  "aggregate_rating": 4.5,
  "total_reviews": 1234,
  "best_rating": 5,
  "worst_rating": 1,
  "last_scraped": "2024-01-15T10:30:00Z",
  "status": "active"
}
```

#### Business Lifecycle Management
- **WordPress Post Statuses**: Use native WordPress post statuses for business management
- **`publish`**: Active business, being scraped automatically
- **`draft`**: Paused business, scraping stopped but data retained
- **`private`**: Inactive business, permanently stopped scraping
- **`trash`**: Deleted business, will be permanently removed

## Deduplication Strategies

### Business Deduplication
- **Unique Identifier**: Trustpilot URL serves as the unique key for each business
- **Strategy**: 
  - Before creating a new business post, query for existing posts with the same Trustpilot URL
  - Use `WP_Query` with `meta_query` to check for `business_url` meta field
  - If found: Update existing post with new aggregate data and metadata
  - If not found: Create new business post
- **Implementation**:
  ```php
  $args = [
      'post_type' => 'tp_business',
      'post_status' => 'publish', // Only check active businesses
      'meta_query' => [
          [
              'key' => 'business_url',
              'value' => $trustpilot_url,
              'compare' => '='
          ]
      ]
  ];
  $query = new WP_Query($args);
  ```

### Review Deduplication
- **Unique Identifier**: Trustpilot review ID (extracted from review URL or JSON-LD data)
- **Strategy**:
  - Each review has a unique Trustpilot review ID
  - Before creating a new review post, check for existing posts with the same review ID
  - If found: Update existing review (in case review content or rating changed)
  - If not found: Create new review post and assign to business taxonomy
- **Implementation**:
  ```php
  $args = [
      'post_type' => 'tp_review',
      'meta_query' => [
          [
              'key' => 'review_id',
              'value' => $trustpilot_review_id,
              'compare' => '='
          ]
      ]
  ];
  $query = new WP_Query($args);
  
  if ($query->have_posts()) {
      // Update existing review post
      $post_id = $query->posts[0]->ID;
      // update_post_meta($post_id, ...);
  } else {
      // Insert new review post
      $post_id = wp_insert_post([...]);
      // Assign to business taxonomy using business slug
      wp_set_object_terms($post_id, $business_slug, 'tp_business');
  }
  ```

### Data Update Strategy
- **Business Updates**: 
  - Aggregate ratings and review counts are updated on each scrape
  - Last scraped timestamp is updated
  - Business metadata JSON is refreshed
- **Review Updates**:
  - Review content, rating, and metadata can be updated if changed on Trustpilot
  - Post modified date reflects when the review was last updated
  - Original review date is preserved in post date field

### Performance Considerations
- **Indexing**: Ensure meta fields used for deduplication are properly indexed
- **Batch Processing**: For large datasets, process reviews in batches to avoid memory issues
- **Cleanup**: Implement cleanup strategies for orphaned reviews when businesses are deleted

## Implementation Phases

### Phase 1: Core Scraping Engine
1. **Custom Post Types Setup**
   - Register `tp_businesses` CPT
   - Register `tp_reviews` CPT
   - **Important**: Use static method call `add_action('init', array('Trustpilot_CPT', 'register_post_types'))` to ensure CPTs appear in admin
   - Set up custom fields and meta boxes
   - Configure admin interface for both CPTs

2. **Basic Scraper Class**
   - HTTP request handling
   - HTML parsing (using DOMDocument or Simple HTML DOM)
   - **Structured data extraction** (JSON-LD parsing for aggregate ratings)
   - Data extraction methods
   - Error handling

3. **Data Storage Layer**
   - Create/update business posts with JSON metadata
   - Create review posts linked to business posts
   - Store review data as post meta
   - Data validation and sanitization

4. **Basic Admin Interface**
   - Settings page for configuration
   - Business management interface
   - Manual scraping trigger per business
   - View scraped reviews in admin

### Phase 2: WordPress Integration
1. **Shortcodes**
   - `[trustpilot_reviews]` - Display all reviews
   - `[trustpilot_reviews rating="5"]` - Filter by rating
   - `[trustpilot_reviews limit="10"]` - Limit number of reviews

2. **Widget**
   - WordPress widget for sidebar display
   - Configuration options in widget settings

3. **Admin Enhancements**
   - Scheduled scraping (cron jobs)
   - Bulk operations
   - Review management interface

### Phase 3: Display & Styling
1. **Templates**
   - Responsive review grid
   - Review slider/carousel
   - Individual review display

2. **Styling**
   - CSS for different display options
   - Star rating display
   - Responsive design

3. **JavaScript Enhancements**
   - Interactive elements
   - AJAX loading for large datasets
   - Slider functionality

### Phase 4: Advanced Features
1. **Caching**
   - Cache scraped data to reduce server load
   - Cache invalidation strategies

2. **Performance Optimization**
   - Lazy loading of reviews
   - Database query optimization
   - Image optimization for avatars

3. **SEO & Schema**
   - Structured data markup for reviews
   - SEO-friendly URLs
   - Meta tags for review pages

## Legal & Ethical Considerations

### Rate Limiting & Respectful Scraping
- **Delay between requests** (2-5 seconds minimum)
- **User-Agent headers** to maximize chance of successful scrape
- **Error handling** for blocked requests

## Configuration Options

### Admin Settings
- **Scraping Frequency**: How often to scrape (interval in hours)
- **Review Limit**: Maximum number of reviews to store
- **Display Settings**: Template, show aggregate, etc.

### Shortcode Parameters <- keep for later
- `limit`: Number of reviews to display
- `rating`: Minimum rating filter
- `orderby`: Sort by date, rating, etc.
- `order`: ASC or DESC
- `template`: Choose display template
- `business`: Specific business (if multiple)
- `show_aggregate`: Display aggregate rating and review count (true/false)

## Security Considerations

### Input Validation
- **Sanitize all user inputs**
- **Validate URLs and parameters**
- **Prevent SQL injection**
- **XSS protection**

### Output Sanitization
- **Escape all output**
- **Sanitize HTML content**
- **Validate review data**

### Access Control
- **Capability checks** for admin functions
- **Nonce verification** for forms
- **User role restrictions**

## Testing Strategy

### Unit Testing
- **Scraper class methods**
- **Data validation functions**

### Integration Testing
- **WordPress integration**
- **Admin interface functionality**
- **Shortcode rendering**

### Performance Testing
- **Scraping performance**
- **Database query optimization**
- **Memory usage monitoring**

## Deployment & Maintenance

### Installation
- **WordPress plugin standards** compliance
- **Activation/deactivation hooks**
- **Default settings initialization**

### Updates
- **Version control** and changelog
- **Database migration** strategies
- **Backward compatibility**

### Monitoring
- **Error logging**
- **Performance monitoring**
- **Usage analytics**

## Future Enhancements

### Scalability Considerations
- **Caching strategies**

## Conclusion

This WordPress plugin will provide a comprehensive solution for displaying Trustpilot reviews on WordPress sites. The phased approach ensures a solid foundation while allowing for iterative improvements and feature additions.

**Next Steps:**
1. Review and finalize the plan
2. Set up development environment
3. Begin Phase 1 implementation
4. Regular testing and iteration
5. Legal review before public release 