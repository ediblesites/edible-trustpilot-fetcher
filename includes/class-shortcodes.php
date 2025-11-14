<?php
/**
 * Shortcodes for Trustpilot Fetcher
 * Handles rendering of Trustpilot business statistics
 */

class Trustpilot_Shortcodes {

    private $business_manager;

    public function __construct() {
        $this->business_manager = new Trustpilot_Business_Manager();
    }

    /**
     * Register all shortcodes
     */
    public static function register_shortcodes() {
        $instance = new self();
        add_shortcode('trustpilot_stats', array($instance, 'render_stats_shortcode'));
    }

    /**
     * Render trustpilot_stats shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered output
     */
    public function render_stats_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'slug' => '',
            'type' => 'rating'
        ), $atts, 'trustpilot_stats');

        $business = null;

        // If slug is provided, use it
        if (!empty($atts['slug'])) {
            $business = $this->business_manager->find_business_by_slug($atts['slug']);
        }
        // Otherwise, try to get from current post in loop
        else {
            global $post;
            if ($post && $post->post_type === 'tp_businesses') {
                $business = $post;
            }
        }

        // Validate we have a business
        if (!$business) {
            if (empty($atts['slug'])) {
                return $this->render_error('Slug parameter is required when not in a business post loop');
            } else {
                return $this->render_error('Business not found for slug: ' . esc_html($atts['slug']));
            }
        }

        // Get business metadata
        $aggregate_rating = get_post_meta($business->ID, 'aggregate_rating', true);
        $total_reviews = get_post_meta($business->ID, 'total_reviews', true);

        // Render based on type
        switch (strtolower($atts['type'])) {
            case 'rating':
                return $this->render_rating($aggregate_rating);

            case 'count':
                return $this->render_count($total_reviews);

            case 'stars':
                return $this->render_stars($aggregate_rating);

            default:
                return $this->render_error('Invalid type parameter. Use: rating, count, or stars');
        }
    }

    /**
     * Render numeric rating
     *
     * @param float $rating Rating value
     * @return string Rendered rating
     */
    private function render_rating($rating) {
        if (empty($rating)) {
            return '';
        }

        // Format to 1 decimal place
        return number_format((float)$rating, 1);
    }

    /**
     * Render review count
     *
     * @param int $count Review count
     * @return string Rendered count
     */
    private function render_count($count) {
        if (empty($count)) {
            return '0';
        }

        // Format with comma separators
        return number_format((int)$count);
    }

    /**
     * Render star rating
     *
     * @param float $rating Rating value
     * @return string Star characters with Trustpilot green color
     */
    private function render_stars($rating) {
        if (empty($rating)) {
            return '';
        }

        $rating = (float)$rating;
        $stars = '';

        // Full stars
        $full_stars = floor($rating);
        for ($i = 0; $i < $full_stars; $i++) {
            $stars .= '★';
        }

        // Half star if needed
        $remainder = $rating - $full_stars;
        if ($remainder >= 0.25 && $remainder < 0.75) {
            $stars .= '⯪';
        } elseif ($remainder >= 0.75) {
            $stars .= '★';
        }

        // Empty stars to fill up to 5
        $total_stars = strlen($stars) / 3; // Unicode stars are 3 bytes each
        $empty_stars = 5 - ceil($rating);
        for ($i = 0; $i < $empty_stars; $i++) {
            $stars .= '☆';
        }

        // Wrap in span with Trustpilot green color (#00B67A)
        return '<span style="color: #00B67A;">' . $stars . '</span>';
    }

    /**
     * Render error message
     *
     * @param string $message Error message
     * @return string Rendered error
     */
    private function render_error($message) {
        // Return empty string for cleaner output (no error shown to end users)
        // For debugging, you can uncomment the line below:
        // return '<!-- Trustpilot Error: ' . esc_html($message) . ' -->';
        return '';
    }
}
