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
}