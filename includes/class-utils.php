<?php
/**
 * Utility functions for Trustpilot Fetcher
 * Contains shared functionality used across the plugin
 */

 class Trustpilot_Utils {
    public static function debug_log($message) {
        if (get_option('trustpilot_debug', false)) {
            error_log($message);
        }
    }

    /**
     * Extract review hash from full Trustpilot review ID URL
     * This ensures deduplication works regardless of regional domain (www, uk, ca, etc.)
     *
     * @param string $review_id Full review ID URL (e.g., https://www.trustpilot.com/#/schema/Review/www.wisefax.com/690bccd2)
     * @return string Hash suffix (e.g., 690bccd2)
     */
    public static function extract_review_hash($review_id) {
        // Extract last path segment after final slash
        $parts = explode('/', $review_id);
        return end($parts);
    }

    /**
     * Send email alert when scraper fails repeatedly for a business
     *
     * @param int $business_id Business post ID that's failing
     */
    public static function send_scraper_failure_alert($business_id) {
        $business = get_post($business_id);
        $failures = get_post_meta($business_id, '_scraper_consecutive_failures', true);
        $error = get_post_meta($business_id, '_scraper_last_error', true);
        $url = get_post_meta($business_id, 'business_url', true);

        $to = get_option('admin_email');
        $subject = '[Trustpilot] Scraper failing for: ' . $business->post_title;

        $message = "The Trustpilot scraper has failed {$failures} times in a row for:\n\n";
        $message .= "Business: {$business->post_title}\n";
        $message .= "URL: {$url}\n\n";
        $message .= "Error: {$error}\n\n";
        $message .= "This may indicate that Trustpilot has changed their page structure.\n";
        $message .= "Action required: Check the Trustpilot page manually and update the scraper if needed.\n\n";
        $message .= "View business in WordPress: " . admin_url("post.php?post={$business_id}&action=edit");

        wp_mail($to, $subject, $message);

        // Always log alert emails (not conditional on debug mode)
        error_log("Trustpilot: Sent scraper failure alert for business {$business_id} ({$business->post_title})");
    }
}