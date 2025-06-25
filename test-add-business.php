<?php
/**
 * External tester for Trustpilot Business Management Endpoints
 * 
 * This script tests the creation and deletion endpoints with proper authentication
 * 
 * Usage: php test-add-business.php [trustpilot_url]
 * Example: php test-add-business.php https://trustpilot.com/review/dazedcarry.com
 * If no URL provided, uses a random URL from the predefined list
 */

// Configuration
$site_url = 'https://edible-sandbox.local'; // Update this to your site URL
define('WP_USERNAME', '{Insert username here}'); // Update this to your admin username
define('WP_APP_PASSWORD', '{Insert app password here}'); // Update this to your application password

// Load secrets configuration if available, otherwise use fallback credentials
$secrets_file = __DIR__ . '/secrets.php';
if (file_exists($secrets_file)) {
    require_once $secrets_file;
} else {
    // Fallback credentials - update these if secrets.php is not available
    $wp_username = 'WP_USERNAME';
    $wp_app_password = 'WP_APP_PASSWORD';
}

// Test URLs (fallback list)
$test_urls = array(
    'https://trustpilot.com/review/www.salonsdirect.com',
    'https://trustpilot.com/review/www.rangeplus.com',
    'https://trustpilot.com/review/yelp.com',
    'https://trustpilot.com/review/dazedcarry.com',
    'https://trustpilot.com/review/direct2compensation.co.uk',
    'https://trustpilot.com/review/ramnode.com',
    'https://trustpilot.com/review/welovekeys.co.uk'
);

/**
 * Make an authenticated REST API request to WordPress
 * 
 * @param string $endpoint The REST API endpoint (e.g., '/wp-json/wp/v2/posts')
 * @param string $method HTTP method (GET, POST, PUT, DELETE)
 * @param array $data Data to send with POST/PUT requests
 * @return array Response with 'success', 'data', 'http_code', and 'error' keys
 */
function wp_rest_request($endpoint, $method = 'GET', $data = null) {
    global $site_url, $wp_username, $wp_app_password;
    
    $url = rtrim($site_url, '/') . '/' . ltrim($endpoint, '/');
    
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERPWD => $wp_username . ':' . $wp_app_password,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        )
    ));
    
    // Set HTTP method
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    }
    
    // Add data for POST/PUT requests
    if ($data && in_array($method, ['POST', 'PUT'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return array(
            'success' => false,
            'error' => 'cURL Error: ' . $error,
            'http_code' => 0,
            'data' => null
        );
    }
    
    $decoded = json_decode($response, true);
    
    return array(
        'success' => $http_code >= 200 && $http_code < 300,
        'data' => $decoded,
        'http_code' => $http_code,
        'error' => null
    );
}

// Get URL from command line or use random selection
$selected_url = null;
$url_source = '';

if ($argc > 1) {
    $command_line_url = $argv[1];
    
    // Validate URL format
    if (filter_var($command_line_url, FILTER_VALIDATE_URL)) {
        if (strpos($command_line_url, 'trustpilot.com') !== false) {
            $selected_url = $command_line_url;
            $url_source = 'command line';
        } else {
            echo "Error: URL must be a Trustpilot URL\n";
            echo "Usage: php test-add-business.php [trustpilot_url]\n";
            exit(1);
        }
    } else {
        echo "Error: Invalid URL format\n";
        echo "Usage: php test-add-business.php [trustpilot_url]\n";
        exit(1);
    }
} else {
    $selected_url = $test_urls[array_rand($test_urls)];
    $url_source = 'random selection';
}

$business_title = 'Test Business - ' . date('Y-m-d H:i:s');

echo "=== Trustpilot Business Management Tester ===\n\n";
echo "Site URL: $site_url\n";
echo "Username: $wp_username\n";
echo "App Password: " . str_repeat('*', strlen($wp_app_password)) . "\n";
echo "Selected URL: $selected_url (from $url_source)\n";
echo "Business Title: $business_title\n\n";

// Test 1: Delete business endpoint
echo "Test 1: Calling delete business endpoint...\n";
$delete_result = wp_rest_request('/wp-json/trustpilot-fetcher/v1/delete-business', 'POST', array(
    'url' => $selected_url
));

echo "Delete Endpoint Response:\n";
echo "- HTTP Code: " . $delete_result['http_code'] . "\n";
if ($delete_result['success']) {
    echo "- Success: Yes\n";
    if ($delete_result['data']) {
        echo "- Message: " . ($delete_result['data']['message'] ?? 'N/A') . "\n";
        if (isset($delete_result['data']['errors']) && !empty($delete_result['data']['errors'])) {
            echo "- Errors: " . implode(', ', $delete_result['data']['errors']) . "\n";
        }
    }
} else {
    echo "- Success: No\n";
    if ($delete_result['error']) {
        echo "- Error: " . $delete_result['error'] . "\n";
    }
    if ($delete_result['data']) {
        echo "- Message: " . ($delete_result['data']['message'] ?? 'N/A') . "\n";
        if (isset($delete_result['data']['errors']) && !empty($delete_result['data']['errors'])) {
            echo "- Errors: " . implode(', ', $delete_result['data']['errors']) . "\n";
        }
    }
}
echo "\n";

// Wait a moment between calls
sleep(2);

// Test 2: Create business endpoint
echo "Test 2: Calling create business endpoint...\n";
$create_result = wp_rest_request('/wp-json/trustpilot-fetcher/v1/create-business', 'POST', array(
    'title' => $business_title,
    'url' => $selected_url
));

echo "Create Endpoint Response:\n";
echo "- HTTP Code: " . $create_result['http_code'] . "\n";
if ($create_result['success']) {
    echo "- Success: Yes\n";
    if ($create_result['data']) {
        echo "- Message: " . ($create_result['data']['message'] ?? 'N/A') . "\n";
        if (isset($create_result['data']['data']['post_id'])) {
            echo "- Post ID: " . $create_result['data']['data']['post_id'] . "\n";
        }
        if (isset($create_result['data']['errors']) && !empty($create_result['data']['errors'])) {
            echo "- Errors: " . implode(', ', $create_result['data']['errors']) . "\n";
        }
    }
} else {
    echo "- Success: No\n";
    if ($create_result['error']) {
        echo "- Error: " . $create_result['error'] . "\n";
    }
    if ($create_result['data']) {
        echo "- Message: " . ($create_result['data']['message'] ?? 'N/A') . "\n";
        if (isset($create_result['data']['errors']) && !empty($create_result['data']['errors'])) {
            echo "- Errors: " . implode(', ', $create_result['data']['errors']) . "\n";
        }
    }
}
echo "\n";

echo "=== Test Complete ===\n"; 