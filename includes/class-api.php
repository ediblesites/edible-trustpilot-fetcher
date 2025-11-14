<?php
/**
 * Trustpilot API Endpoints
 * 
 * Handles all REST API endpoints for the Trustpilot plugin
 */

class Trustpilot_API {
    
    /**
     * Initialize the API endpoints
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_endpoints'));
    }
    
    /**
     * Register all REST API endpoints
     */
    public static function register_endpoints() {
        register_rest_route('trustpilot-fetcher/v1', '/create-business', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'create_business_endpoint'),
            'permission_callback' => array(__CLASS__, 'check_permissions'),
            'args' => array(
                'url' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw'
                )
            )
        ));
        
        register_rest_route('trustpilot-fetcher/v1', '/delete-business', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'delete_business_endpoint'),
            'permission_callback' => array(__CLASS__, 'check_permissions'),
            'args' => array(
                'url' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw'
                )
            )
        ));
    }
    
    /**
     * REST API endpoint for creating businesses
     */
    public static function create_business_endpoint($request) {
        $url = $request->get_param('url');
        
        $response = array(
            'success' => false,
            'message' => '',
            'errors' => array()
        );
        
        try {
            // Minimal validation
            if (empty($url)) {
                throw new Exception('URL is required');
            }

            // Relay to business manager
            $business_manager = new Trustpilot_Business_Manager();
            $result = $business_manager->create_business($url);

            if ($result['success']) {
                $response['success'] = true;
                $response['message'] = $result['message'];
                if (isset($result['post_id'])) {
                    $response['data']['post_id'] = $result['post_id'];
                }
            } else {
                $response['message'] = $result['message'];
                if (isset($result['errors'])) {
                    $response['errors'] = $result['errors'];
                }
            }

        } catch (Exception $e) {
            $response['errors'][] = $e->getMessage();
            $response['message'] = 'Error: ' . $e->getMessage();
        }
        
        return new WP_REST_Response($response, $response['success'] ? 200 : 400);
    }
    
    /**
     * REST API endpoint for deleting businesses
     */
    public static function delete_business_endpoint($request) {
        $url = $request->get_param('url');
        
        $response = array(
            'success' => false,
            'message' => '',
            'errors' => array()
        );
        
        try {
            // Minimal validation
            if (empty($url)) {
                throw new Exception('URL is required');
            }

            // Relay to business manager
            $business_manager = new Trustpilot_Business_Manager();
            $result = $business_manager->delete_business_by_url($url);

            // Always return 200, even if not found
            $response['success'] = $result['success'];
            $response['message'] = $result['message'];
            if (isset($result['errors'])) {
                $response['errors'] = $result['errors'];
            }
            return new WP_REST_Response($response, 200);

        } catch (Exception $e) {
            $response['errors'][] = $e->getMessage();
            $response['message'] = 'Error: ' . $e->getMessage();
            return new WP_REST_Response($response, 400);
        }
    }

    /**
     * Check permissions for accessing certain endpoints
     */
    public static function check_permissions() {
        return current_user_can('manage_options');
    }
} 