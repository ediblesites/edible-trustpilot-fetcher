<?php
/**
 * Standalone Trustpilot Scraper Tester
 * Run this directly to test scraping functionality
 * 
 * Usage: php test-scraper.php <trustpilot_url>
 * Example: php test-scraper.php https://www.trustpilot.com/review/www.microsoft.com
 */

// Check command line arguments
if ($argc < 2) {
    echo "Usage: php test-scraper.php <trustpilot_url>\n";
    echo "Example: php test-scraper.php https://www.trustpilot.com/review/www.microsoft.com\n";
    exit(1);
}

// Get URL from command line
$test_url = $argv[1];

// Validate URL format
if (!filter_var($test_url, FILTER_VALIDATE_URL)) {
    echo "Error: Invalid URL format\n";
    exit(1);
}

if (strpos($test_url, 'trustpilot.com') === false) {
    echo "Error: URL must be a Trustpilot URL\n";
    exit(1);
}

// Mock WordPress functions for standalone testing
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        
        public function __construct($code, $message) {
            $this->code = $code;
            $this->message = $message;
        }
        
        public function get_error_message() {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('current_time')) {
    function current_time($type = 'mysql') {
        return date('c');
    }
}

// Include the actual scraper class
require_once 'includes/class-trustpilot-scraper.php';

// Create scraper instance
$scraper = new Trustpilot_Scraper();

echo "=== Trustpilot Scraper Tester ===\n\n";

echo "Testing: $test_url\n";
echo str_repeat("-", 50) . "\n";

$results = $scraper->test_scraping($test_url);

if ($results['success']) {
    echo "✅ SUCCESS\n";
    echo "Execution time: " . number_format($results['execution_time'], 2) . " seconds\n\n";
    
    echo "Extracted Data:\n";
    echo "- Business URL: " . $results['data']['business_url'] . "\n";
    echo "- Business Name: " . $results['data']['business_name'] . "\n";
    echo "- Aggregate Rating: " . $results['data']['aggregate_rating'] . "/5\n";
    echo "- Total Reviews: " . $results['data']['total_reviews'] . "\n";
    echo "- Best Rating: " . $results['data']['best_rating'] . "\n";
    echo "- Worst Rating: " . $results['data']['worst_rating'] . "\n";
    echo "- Last Scraped: " . $results['data']['last_scraped'] . "\n";
    echo "- Status: " . $results['data']['status'] . "\n\n";
    
    echo "JSON Output:\n";
    echo json_encode($results['data'], JSON_PRETTY_PRINT) . "\n";
    
} else {
    echo "❌ FAILED\n";
    echo "Execution time: " . number_format($results['execution_time'], 2) . " seconds\n";
    echo "Error: " . $results['error'] . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n\n";

echo "Testing complete!\n"; 