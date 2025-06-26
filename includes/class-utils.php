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
}