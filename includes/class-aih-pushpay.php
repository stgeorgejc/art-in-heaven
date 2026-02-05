<?php
/**
 * Pushpay API Integration
 * 
 * Handles OAuth authentication and API requests to Pushpay
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Pushpay_API {
    
    private static $instance = null;
    
    // API endpoints
    const AUTH_URL = 'https://auth.pushpay.com/pushpay-sandbox/oauth/token'; // Sandbox
    const AUTH_URL_PROD = 'https://auth.pushpay.com/pushpay/oauth/token'; // Production
    const API_URL = 'https://sandbox-api.pushpay.io/v1'; // Sandbox
    const API_URL_PROD = 'https://api.pushpay.com/v1'; // Production
    
    private $access_token = null;
    private $token_expiry = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get API settings
     */
    public function get_settings() {
        $is_sandbox = get_option('aih_pushpay_sandbox', 0);
        
        if ($is_sandbox) {
            // Sandbox credentials
            return array(
                'client_id' => get_option('aih_pushpay_sandbox_client_id', ''),
                'client_secret' => get_option('aih_pushpay_sandbox_client_secret', ''),
                'organization_key' => get_option('aih_pushpay_sandbox_organization_key', ''),
                'merchant_key' => get_option('aih_pushpay_sandbox_merchant_key', ''),
                'merchant_handle' => get_option('aih_pushpay_sandbox_merchant_handle', ''),
                'fund' => get_option('aih_pushpay_fund', ''),
                'sandbox_mode' => true,
            );
        } else {
            // Production credentials
            return array(
                'client_id' => get_option('aih_pushpay_client_id', ''),
                'client_secret' => get_option('aih_pushpay_client_secret', ''),
                'organization_key' => get_option('aih_pushpay_organization_key', ''),
                'merchant_key' => get_option('aih_pushpay_merchant_key', ''),
                'merchant_handle' => get_option('aih_pushpay_merchant_handle', ''),
                'fund' => get_option('aih_pushpay_fund', ''),
                'sandbox_mode' => false,
            );
        }
    }
    
    /**
     * Check if API is configured
     */
    public function is_configured() {
        $settings = $this->get_settings();
        return !empty($settings['client_id']) && !empty($settings['client_secret']) && !empty($settings['organization_key']);
    }
    
    /**
     * Get API base URL based on mode
     */
    private function get_api_url() {
        $settings = $this->get_settings();
        return $settings['sandbox_mode'] ? self::API_URL : self::API_URL_PROD;
    }
    
    /**
     * Get Auth URL based on mode
     */
    private function get_auth_url() {
        $settings = $this->get_settings();
        return $settings['sandbox_mode'] ? self::AUTH_URL : self::AUTH_URL_PROD;
    }
    
    /**
     * Get OAuth access token
     */
    public function get_access_token($force_refresh = false) {
        // Check cached token
        if (!$force_refresh) {
            $cached = get_transient('aih_pushpay_token');
            if ($cached) {
                return $cached;
            }
        }
        
        $settings = $this->get_settings();
        
        if (empty($settings['client_id']) || empty($settings['client_secret'])) {
            return new WP_Error('missing_credentials', 'Pushpay API credentials not configured.');
        }
        
        $response = wp_remote_post($this->get_auth_url(), array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode($settings['client_id'] . ':' . $settings['client_secret'])
            ),
            'body' => array(
                'grant_type' => 'client_credentials',
                'scope' => 'read merchant:view_payments'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Pushpay OAuth Error: ' . $response->get_error_message());
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code !== 200) {
            $error_msg = isset($body['error_description']) ? $body['error_description'] : 'Authentication failed';
            error_log('Pushpay OAuth Error (' . $code . '): ' . $error_msg);
            return new WP_Error('auth_failed', $error_msg);
        }
        
        if (isset($body['access_token'])) {
            $expires_in = isset($body['expires_in']) ? intval($body['expires_in']) - 60 : 3540; // Default 59 mins
            set_transient('aih_pushpay_token', $body['access_token'], $expires_in);
            return $body['access_token'];
        }
        
        return new WP_Error('no_token', 'No access token received.');
    }
    
    /**
     * Make API request
     */
    public function api_request($endpoint, $method = 'GET', $data = null) {
        $token = $this->get_access_token();
        
        if (is_wp_error($token)) {
            return $token;
        }
        
        $url = $this->get_api_url() . $endpoint;
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'timeout' => 30,
            'method' => $method
        );
        
        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }
        
        error_log('Pushpay API Request: ' . $method . ' ' . $url);
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('Pushpay API Error: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);
        error_log('Pushpay API Response (' . $code . '): ' . substr($raw_body, 0, 500));
        
        if ($code === 401) {
            // Token expired, try refreshing once
            static $auth_retried = false;
            if (!$auth_retried) {
                $auth_retried = true;
                delete_transient('aih_pushpay_token');
                $token = $this->get_access_token(true);
                if (!is_wp_error($token)) {
                    $result = $this->api_request($endpoint, $method, $data);
                    $auth_retried = false;
                    return $result;
                }
            }
            $auth_retried = false;
            return new WP_Error('auth_expired', 'Authentication failed (401) — ' . $method . ' ' . $url);
        }
        
        if ($code === 429) {
            error_log('Pushpay API rate limited (429) on ' . $url);
            return new WP_Error('rate_limited', 'Rate limited (429) on ' . $method . ' ' . $url, array('code' => 429));
        }

        if ($code >= 400) {
            $error_msg = isset($body['message']) ? $body['message'] : 'API request failed';
            error_log('Pushpay API Error (' . $code . ') on ' . $url . ': ' . $error_msg . ' | Response: ' . $raw_body);
            return new WP_Error('api_error', $error_msg . ' (HTTP ' . $code . ') — ' . $method . ' ' . $url, array('code' => $code, 'body' => $body));
        }
        
        return $body;
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'message' => 'Pushpay API credentials not configured.'
            );
        }
        
        $settings = $this->get_settings();
        
        // Try to get organization info
        $result = $this->api_request('/organization/' . $settings['organization_key']);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message()
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Connected to Pushpay successfully.',
            'organization' => isset($result['name']) ? $result['name'] : 'Unknown'
        );
    }
    
    /**
     * Get payments/transactions from Pushpay
     */
    public function get_payments($params = array()) {
        $settings = $this->get_settings();

        if (empty($settings['merchant_key'])) {
            return new WP_Error('no_merchant', 'Merchant key not configured.');
        }

        $defaults = array(
            'page' => 0,
            'pageSize' => 100,
            'from' => null, // ISO 8601 date
            'to' => null,
            'status' => null, // Success, Failed, Processing, etc.
            'fund' => null
        );

        $params = wp_parse_args($params, $defaults);

        // Build query string
        $query = array(
            'page' => $params['page'],
            'pageSize' => $params['pageSize']
        );

        if ($params['from']) {
            $query['from'] = $params['from'];
        }
        if ($params['to']) {
            $query['to'] = $params['to'];
        }
        if ($params['status']) {
            $query['status'] = $params['status'];
        }

        $endpoint = '/merchant/' . $settings['merchant_key'] . '/payments?' . http_build_query($query);

        return $this->api_request($endpoint);
    }
    
    /**
     * Get a specific payment by ID
     */
    public function get_payment($payment_id) {
        $settings = $this->get_settings();

        if (empty($settings['merchant_key'])) {
            return new WP_Error('no_merchant', 'Merchant key not configured.');
        }

        return $this->api_request('/merchant/' . $settings['merchant_key'] . '/payment/' . $payment_id);
    }
    
    /**
     * Search payments by reference (order number)
     */
    public function search_by_reference($reference) {
        $settings = $this->get_settings();

        if (empty($settings['merchant_key'])) {
            return new WP_Error('no_merchant', 'Merchant key not configured.');
        }

        $endpoint = '/merchant/' . $settings['merchant_key'] . '/payments?' . http_build_query(array(
            'pageSize' => 10,
            'q' => $reference
        ));
        
        return $this->api_request($endpoint);
    }
    
    /**
     * Get fund/listing information
     */
    public function get_funds() {
        $settings = $this->get_settings();
        
        if (empty($settings['organization_key'])) {
            return new WP_Error('no_org', 'Organization key not configured.');
        }
        
        return $this->api_request('/organization/' . $settings['organization_key'] . '/funds');
    }
    
    /**
     * Sync payments from Pushpay to local database
     */
    public function sync_payments($days_back = 30) {
        global $wpdb;

        $settings = $this->get_settings();
        $orders_table = AIH_Database::get_table('orders');
        $transactions_table = AIH_Database::get_table('pushpay_transactions');
        $fund_name = get_option('aih_pushpay_fund', 'art-in-heaven');

        // Use event start date if set, otherwise fetch all transactions
        $event_date = get_option('aih_event_date', '');
        $from = !empty($event_date) ? date('c', strtotime($event_date)) : null;
        $to = date('c');

        // Clean up orphaned order references (orders that were deleted)
        $wpdb->query(
            "UPDATE {$transactions_table} t
             LEFT JOIN {$orders_table} o ON t.order_id = o.id
             SET t.order_id = NULL
             WHERE t.order_id IS NOT NULL AND o.id IS NULL"
        );

        $page = 0;
        $total_synced = 0;
        $total_matched = 0;
        $has_more = true;

        while ($has_more) {
            $payment_params = array(
                'page' => $page,
                'pageSize' => 100,
                'status' => 'Success'
            );
            if ($from) {
                $payment_params['from'] = $from;
            }
            $payment_params['to'] = $to;

            $result = $this->get_payments($payment_params);
            
            if (is_wp_error($result)) {
                return $result;
            }
            
            $payments = isset($result['items']) ? $result['items'] : array();

            if (empty($payments)) {
                break;
            }

            // Log fund names from API for debugging
            $before_count = count($payments);
            $fund_names_found = array_map(function($p) {
                return isset($p['fund']['name']) ? $p['fund']['name'] : '(no fund)';
            }, $payments);
            error_log('Pushpay sync: ' . $before_count . ' payments before fund filter. Fund names in response: ' . implode(', ', array_unique($fund_names_found)) . ' | Filtering for: "' . $fund_name . '"');

            // Filter by fund/category (API doesn't support fund filtering)
            if (!empty($fund_name)) {
                $payments = array_filter($payments, function($payment) use ($fund_name) {
                    $payment_fund = isset($payment['fund']['name']) ? $payment['fund']['name'] : '';
                    return strcasecmp($payment_fund, $fund_name) === 0;
                });
                error_log('Pushpay sync: ' . count($payments) . ' payments after fund filter.');
            }

            foreach ($payments as $payment) {
                // Store transaction
                $transaction_data = array(
                    'pushpay_id' => $payment['paymentToken'],
                    'amount' => floatval($payment['amount']['amount']),
                    'currency' => $payment['amount']['currency'],
                    'status' => $payment['status'],
                    'payer_name' => isset($payment['payer']['fullName']) ? $payment['payer']['fullName'] : (isset($payment['payer']['firstName']) ? trim($payment['payer']['firstName'] . ' ' . ($payment['payer']['lastName'] ?? '')) : ''),
                    'payer_email' => isset($payment['payer']['emailAddress']) ? $payment['payer']['emailAddress'] : '',
                    'fund' => isset($payment['fund']['name']) ? $payment['fund']['name'] : '',
                    'reference' => isset($payment['paymentMethodDetails']['reference']) ? $payment['paymentMethodDetails']['reference'] : '',
                    'notes' => isset($payment['payerNote']) ? $payment['payerNote'] : '',
                    'payment_date' => wp_date('Y-m-d H:i:s', strtotime($payment['createdOn'])),
                    'raw_data' => json_encode($payment),
                    'synced_at' => current_time('mysql')
                );
                
                // Check if transaction already exists
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$transactions_table} WHERE pushpay_id = %s",
                    $payment['paymentToken']
                ));
                
                if ($existing) {
                    $wpdb->update($transactions_table, $transaction_data, array('id' => $existing));

                    // Backfill payment_reference for already-linked orders missing it
                    $linked_order_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT order_id FROM {$transactions_table} WHERE id = %d AND order_id IS NOT NULL",
                        $existing
                    ));
                    if ($linked_order_id) {
                        $linked_order = $wpdb->get_row($wpdb->prepare(
                            "SELECT id, payment_reference FROM {$orders_table} WHERE id = %d",
                            $linked_order_id
                        ));
                        if ($linked_order && empty($linked_order->payment_reference)) {
                            $wpdb->update(
                                $orders_table,
                                array(
                                    'payment_reference' => isset($payment['transactionId']) ? $payment['transactionId'] : $payment['paymentToken'],
                                    'updated_at' => current_time('mysql')
                                ),
                                array('id' => $linked_order->id)
                            );
                        }
                    }
                } else {
                    $wpdb->insert($transactions_table, $transaction_data);
                    $total_synced++;
                }

                // Try to match to an order by order number found anywhere in the payment data
                $order_number = null;
                $raw_json = json_encode($payment);

                // Search the entire payment JSON for the order number pattern
                // This catches it regardless of which field Pushpay returns it in
                // (payerNote from 'nt' param, source reference from 'sr' param, etc.)
                if (preg_match('/AIH-[A-Z0-9]+/i', $raw_json, $matches)) {
                    $order_number = strtoupper($matches[0]);
                }
                
                // Update order if matched
                if ($order_number) {
                    $order = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$orders_table} WHERE order_number = %s",
                        $order_number
                    ));
                    
                    if ($order && $order->payment_status !== 'paid') {
                        $wpdb->update(
                            $orders_table,
                            array(
                                'payment_status' => 'paid',
                                'payment_method' => 'pushpay',
                                'payment_reference' => isset($payment['transactionId']) ? $payment['transactionId'] : $payment['paymentToken'],
                                'pushpay_payment_id' => $payment['paymentToken'],
                                'pushpay_status' => $payment['status'],
                                'payment_date' => wp_date('Y-m-d H:i:s', strtotime($payment['createdOn'])),
                                'updated_at' => current_time('mysql')
                            ),
                            array('id' => $order->id)
                        );

                        // Link transaction to order
                        $wpdb->update(
                            $transactions_table,
                            array('order_id' => $order->id),
                            array('pushpay_id' => $payment['paymentToken'])
                        );

                        $total_matched++;
                    } elseif ($order && empty($order->payment_reference)) {
                        // Backfill payment_reference for already-paid orders
                        $wpdb->update(
                            $orders_table,
                            array(
                                'payment_reference' => isset($payment['transactionId']) ? $payment['transactionId'] : $payment['paymentToken'],
                                'pushpay_payment_id' => $payment['paymentToken'],
                                'updated_at' => current_time('mysql')
                            ),
                            array('id' => $order->id)
                        );
                        
                        // Link transaction to order
                        $wpdb->update(
                            $transactions_table,
                            array('order_id' => $order->id),
                            array('pushpay_id' => $payment['paymentToken'])
                        );
                        
                        $total_matched++;
                    }
                }
            }
            
            // Check for more pages
            $has_more = isset($result['page']) && isset($result['totalPages']) && $result['page'] < $result['totalPages'] - 1;
            $page++;

            // Throttle requests to avoid hitting Pushpay rate limits
            if ($has_more) {
                sleep(1);
            }
            
            // Safety limit
            if ($page > 50) break;
        }
        
        // Store sync timestamp
        update_option('aih_pushpay_last_sync', current_time('mysql'));
        update_option('aih_pushpay_last_sync_count', $total_synced);
        
        return array(
            'success' => true,
            'synced' => $total_synced,
            'matched' => $total_matched,
            'message' => sprintf('Synced %d transactions, matched %d to orders.', $total_synced, $total_matched)
        );
    }
    
    /**
     * Get all synced transactions
     */
    public function get_synced_transactions($args = array()) {
        global $wpdb;
        
        $transactions_table = AIH_Database::get_table('pushpay_transactions');
        $orders_table = AIH_Database::get_table('orders');
        
        $defaults = array(
            'status' => '',
            'matched' => '', // 'yes', 'no', or ''
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'payment_date',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = "1=1";
        if ($args['status']) {
            $where .= $wpdb->prepare(" AND t.status = %s", $args['status']);
        }
        if ($args['matched'] === 'yes') {
            $where .= " AND t.order_id IS NOT NULL";
        } elseif ($args['matched'] === 'no') {
            $where .= " AND t.order_id IS NULL";
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, o.order_number, o.bidder_id
             FROM {$transactions_table} t
             LEFT JOIN {$orders_table} o ON t.order_id = o.id
             WHERE {$where}
             ORDER BY {$args['orderby']} {$args['order']}
             LIMIT %d OFFSET %d",
            $args['limit'],
            $args['offset']
        ));
    }
    
    /**
     * Manually match a transaction to an order
     */
    public function match_transaction_to_order($transaction_id, $order_id) {
        global $wpdb;
        
        $transactions_table = AIH_Database::get_table('pushpay_transactions');
        $orders_table = AIH_Database::get_table('orders');
        
        // Get transaction
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$transactions_table} WHERE id = %d",
            $transaction_id
        ));
        
        if (!$transaction) {
            return new WP_Error('not_found', 'Transaction not found.');
        }
        
        // Get order
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$orders_table} WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            return new WP_Error('not_found', 'Order not found.');
        }
        
        // Update transaction
        $wpdb->update(
            $transactions_table,
            array('order_id' => $order_id),
            array('id' => $transaction_id)
        );
        
        // Extract transactionId from raw data if available
        $raw = json_decode($transaction->raw_data, true);
        $reference = isset($raw['transactionId']) ? $raw['transactionId'] : $transaction->pushpay_id;

        // Update order
        $wpdb->update(
            $orders_table,
            array(
                'payment_status' => 'paid',
                'payment_method' => 'pushpay',
                'payment_reference' => $reference,
                'pushpay_payment_id' => $transaction->pushpay_id,
                'pushpay_status' => $transaction->status,
                'payment_date' => $transaction->payment_date,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $order_id)
        );
        
        return array(
            'success' => true,
            'message' => 'Transaction matched to order.'
        );
    }
    
    /**
     * Discover organization and merchant keys using client credentials.
     * Authenticates with the API and fetches available organizations and merchants.
     */
    public function discover_keys() {
        $token = $this->get_access_token(true);

        if (is_wp_error($token)) {
            return array(
                'success' => false,
                'message' => 'Authentication failed: ' . $token->get_error_message()
            );
        }

        // Fetch organizations in scope for current credentials
        $orgs_result = $this->api_request('/organizations/in-scope');

        if (is_wp_error($orgs_result)) {
            return array(
                'success' => false,
                'message' => 'Failed to fetch organizations: ' . $orgs_result->get_error_message()
            );
        }

        $organizations = array();
        $items = isset($orgs_result['items']) ? $orgs_result['items'] : (is_array($orgs_result) && isset($orgs_result[0]) ? $orgs_result : array());

        // Handle single org response (not wrapped in items)
        if (empty($items) && isset($orgs_result['key'])) {
            $items = array($orgs_result);
        }

        foreach ($items as $org) {
            $org_key = isset($org['key']) ? $org['key'] : '';
            $org_name = isset($org['name']) ? $org['name'] : 'Unknown';

            if (empty($org_key)) continue;

            $org_data = array(
                'key' => $org_key,
                'name' => $org_name,
                'merchants' => array()
            );

            // Fetch merchant listings for this organization
            $merchants_result = $this->api_request('/organization/' . $org_key . '/merchantlistings');

            if (!is_wp_error($merchants_result)) {
                $merchant_items = isset($merchants_result['items']) ? $merchants_result['items'] : (is_array($merchants_result) && isset($merchants_result[0]) ? $merchants_result : array());

                if (empty($merchant_items) && isset($merchants_result['key'])) {
                    $merchant_items = array($merchants_result);
                }

                foreach ($merchant_items as $merchant) {
                    if (isset($merchant['key'])) {
                        $org_data['merchants'][] = array(
                            'key' => $merchant['key'],
                            'name' => isset($merchant['name']) ? $merchant['name'] : 'Unknown',
                            'handle' => isset($merchant['handle']) ? $merchant['handle'] : ''
                        );
                    }
                }
            }

            $organizations[] = $org_data;
        }

        if (empty($organizations)) {
            return array(
                'success' => false,
                'message' => 'No organizations found. Your API credentials may not have the required permissions.'
            );
        }

        return array(
            'success' => true,
            'organizations' => $organizations,
            'message' => sprintf('Found %d organization(s).', count($organizations))
        );
    }

    /**
     * Generate payment URL with pre-filled data
     */
    public function get_payment_url($order) {
        $settings = $this->get_settings();

        if (empty($settings['merchant_handle'])) {
            return '';
        }

        $auth = AIH_Auth::get_instance();
        $bidder = $auth->get_bidder_by_email($order->bidder_id);

        // Build return URL - redirect back to the site after payment
        $return_url = get_option('aih_pushpay_return_url', '');
        if (empty($return_url)) {
            // Default to gallery page or home
            $gallery_page = get_option('aih_gallery_page', '');
            $return_url = $gallery_page ?: home_url('/');
        }

        $params = array(
            'a' => number_format($order->total, 2, '.', ''),
            'al' => 'true',          // Lock the amount
            'rcv' => 'false',        // Not recurring
            'fnd' => $settings['fund'],
            'fndv' => 'lock',        // Lock the fund selection
            'sr' => $order->order_number, // Source reference for tracking
            'nt' => $order->order_number, // Note field with order number
            'r' => $return_url,      // Return URL after payment
        );

        if ($bidder) {
            if (!empty($bidder->email_primary)) $params['ue'] = $bidder->email_primary;
            if (!empty($bidder->name_first)) $params['ufn'] = $bidder->name_first;
            if (!empty($bidder->name_last)) $params['uln'] = $bidder->name_last;
            if (!empty($bidder->phone_mobile)) $params['up'] = $bidder->phone_mobile;
        }

        $base_url = $settings['sandbox_mode'] ? 'https://sandbox.pushpay.io/g/' : 'https://pushpay.com/g/';
        return $base_url . $settings['merchant_handle'] . '?' . http_build_query($params);
    }
}
