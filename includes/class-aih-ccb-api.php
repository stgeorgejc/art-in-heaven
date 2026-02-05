<?php
/**
 * CCB API Client
 * 
 * A clean, flexible API client for Church Community Builder.
 * Handles connection, authentication, and data parsing.
 * 
 * Usage:
 *   $api = AIH_CCB_API::get_instance();
 *   $registrants = $api->get_form_responses();
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_CCB_API {
    
    private static $instance = null;
    
    // API Configuration
    private $base_url = '';
    private $username = '';
    private $password = '';
    private $form_id = '';
    private $timeout = 60;
    
    // Field mapping: CCB field name => local database column
    // Customize this to map any CCB fields to your database
    private $field_map = array(
        // Profile fields (from profile_info elements)
        'email_primary'    => 'email_primary',
        'name_first'       => 'name_first',
        'name_last'        => 'name_last',
        'phone_mobile'     => 'phone_mobile',
        'phone_home'       => 'phone_home',
        'phone_work'       => 'phone_work',
        'birthday'         => 'birthday',
        'gender'           => 'gender',
        'marital_status'   => 'marital_status',
        'mailing_street'   => 'mailing_street',
        'mailing_city'     => 'mailing_city',
        'mailing_state'    => 'mailing_state',
        'mailing_zip'      => 'mailing_zip',
    );
    
    // Direct fields (not in profile_info, directly in form_response)
    private $direct_fields = array(
        'confirmation_code',
        'individual_id',
        'individual_name',
    );
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_settings();
    }
    
    /**
     * Load API settings from WordPress options
     */
    private function load_settings() {
        $this->base_url = get_option('aih_api_base_url', '');
        $this->form_id  = get_option('aih_api_form_id', '');
        $this->username = get_option('aih_api_username', '');
        $this->password = get_option('aih_api_password', '');
    }
    
    /**
     * Reload settings (useful after saving new options)
     */
    public function reload_settings() {
        $this->load_settings();
        return $this;
    }
    
    /**
     * Check if API is configured
     */
    public function is_configured() {
        return !empty($this->base_url) && !empty($this->form_id);
    }
    
    /**
     * Get current configuration status
     */
    public function get_config_status() {
        return array(
            'base_url'    => !empty($this->base_url),
            'form_id'     => !empty($this->form_id),
            'username'    => !empty($this->username),
            'password'    => !empty($this->password),
            'configured'  => $this->is_configured(),
        );
    }
    
    /**
     * Add custom field mapping
     * 
     * @param string $ccb_field   The field name in CCB XML
     * @param string $local_field The column name in your database
     */
    public function add_field_mapping($ccb_field, $local_field) {
        $this->field_map[$ccb_field] = $local_field;
        return $this;
    }
    
    /**
     * Set multiple field mappings at once
     * 
     * @param array $mappings Array of ccb_field => local_field
     */
    public function set_field_mappings($mappings) {
        $this->field_map = array_merge($this->field_map, $mappings);
        return $this;
    }
    
    /**
     * Get all field mappings
     */
    public function get_field_mappings() {
        return $this->field_map;
    }
    
    /**
     * Make an API request
     * 
     * @param string $service The CCB service name (e.g., 'form_responses')
     * @param array  $params  Additional query parameters
     * @return array ['success' => bool, 'data' => string|null, 'error' => string|null]
     */
    public function request($service, $params = array()) {
        if (empty($this->base_url)) {
            return $this->error('API Base URL not configured.');
        }
        
        // Build URL
        $params['srv'] = $service;
        $url = rtrim($this->base_url, '/') . '?' . http_build_query($params);
        
        // Build request args
        $args = array(
            'timeout' => $this->timeout,
            'headers' => array(
                'Accept' => 'application/xml',
                'User-Agent' => 'Art-In-Heaven/' . AIH_VERSION,
            ),
        );
        
        // Add authentication if configured
        if (!empty($this->username) && !empty($this->password)) {
            $args['headers']['Authorization'] = 'Basic ' . base64_encode($this->username . ':' . $this->password);
        }
        
        // Make request
        $response = wp_remote_get($url, $args);
        
        // Handle errors
        if (is_wp_error($response)) {
            return $this->error('Connection failed: ' . $response->get_error_message());
        }
        
        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status !== 200) {
            return $this->error("API returned HTTP {$status}");
        }
        
        if (empty($body)) {
            return $this->error('API returned empty response.');
        }
        
        return $this->success($body);
    }
    
    /**
     * Get form responses (registrants)
     * 
     * @param string|null $form_id Optional form ID (uses configured one if not provided)
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null, 'count' => int]
     */
    public function get_form_responses($form_id = null) {
        $form_id = $form_id ?: $this->form_id;
        
        if (empty($form_id)) {
            return $this->error('Form ID not configured.');
        }
        
        $response = $this->request('form_responses', array('form_id' => $form_id));
        
        if (!$response['success']) {
            return $response;
        }
        
        // Parse the XML response
        $registrants = $this->parse_form_responses($response['data']);
        
        return array(
            'success' => true,
            'data' => $registrants,
            'count' => count($registrants),
            'raw' => $response['data'], // Include raw XML for debugging
        );
    }
    
    /**
     * Test API connection
     * 
     * @return array ['success' => bool, 'message' => string, 'count' => int]
     */
    public function test_connection() {
        $result = $this->get_form_responses();
        
        if (!$result['success']) {
            return array(
                'success' => false,
                'message' => $result['error'],
                'count' => 0,
            );
        }
        
        return array(
            'success' => true,
            'message' => sprintf('Connected! Found %d registrants.', $result['count']),
            'count' => $result['count'],
        );
    }
    
    /**
     * Parse form_responses XML into array of registrants
     * 
     * @param string $xml Raw XML response
     * @return array Array of parsed registrant data
     */
    private function parse_form_responses($xml) {
        $registrants = array();
        
        // Find all form_response blocks
        if (!preg_match_all('/<form_response[^>]*>(.*?)<\/form_response>/s', $xml, $matches)) {
            return $registrants;
        }
        
        foreach ($matches[1] as $block) {
            $registrant = $this->parse_single_response($block);
            
            // Only include if we have a confirmation code
            if (!empty($registrant['confirmation_code'])) {
                $registrants[] = $registrant;
            }
        }
        
        return $registrants;
    }
    
    /**
     * Parse a single form_response block
     * 
     * @param string $xml Single form_response XML block
     * @return array Parsed registrant data
     */
    private function parse_single_response($xml) {
        $data = array();
        
        // Initialize all mapped fields with empty values
        foreach ($this->field_map as $ccb_field => $local_field) {
            $data[$local_field] = '';
        }
        foreach ($this->direct_fields as $field) {
            $data[$field] = '';
        }
        
        // Extract direct fields
        
        // confirmation_code
        if (preg_match('/<confirmation_code>([^<]*)<\/confirmation_code>/', $xml, $m)) {
            $data['confirmation_code'] = trim($m[1]);
        }
        
        // individual element: <individual id="2413">Larry Boy</individual>
        if (preg_match('/<individual\s+id="([^"]*)"[^>]*>([^<]*)<\/individual>/', $xml, $m)) {
            $data['individual_id'] = trim($m[1]);
            $data['individual_name'] = trim($m[2]);
        }
        
        // Extract profile_info fields
        // Pattern: <profile_info id="909" name="email_primary">value</profile_info>
        if (preg_match_all('/<profile_info[^>]+name="([^"]+)"[^>]*>([^<]*)<\/profile_info>/', $xml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $ccb_field = trim($match[1]);
                $value = trim($match[2]);
                
                // Map to local field name if mapping exists
                if (isset($this->field_map[$ccb_field])) {
                    $local_field = $this->field_map[$ccb_field];
                    $data[$local_field] = $value;
                }
            }
        }
        
        // Store raw XML for debugging (truncated)
        $data['api_data'] = strlen($xml) > 5000 ? substr($xml, 0, 5000) . '...' : $xml;
        
        return $data;
    }
    
    /**
     * Helper: Create success response
     */
    private function success($data) {
        return array('success' => true, 'data' => $data, 'error' => null);
    }
    
    /**
     * Helper: Create error response
     */
    private function error($message) {
        return array('success' => false, 'data' => null, 'error' => $message);
    }
}
