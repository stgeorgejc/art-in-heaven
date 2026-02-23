<?php
/**
 * REST API Class
 * 
 * Provides RESTful API endpoints for the auction system.
 * All endpoints include proper authentication and validation.
 * 
 * @package ArtInHeaven
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_REST_API {
    
    /**
     * API namespace
     */
    const NAMESPACE = 'art-in-heaven/v1';
    
    /**
     * Register REST routes
     */
    public function register_routes() {
        // Art pieces
        register_rest_route(self::NAMESPACE, '/art', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_art_pieces'),
                'permission_callback' => '__return_true',
                'args' => $this->get_art_collection_args(),
            ),
        ));
        
        register_rest_route(self::NAMESPACE, '/art/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_art_piece'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'id' => array(
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0;
                        },
                    ),
                ),
            ),
        ));
        
        // Bids
        register_rest_route(self::NAMESPACE, '/bids', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_bid'),
                'permission_callback' => array($this, 'check_bidder_permission'),
                'args' => array(
                    'art_piece_id' => array(
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ),
                    'amount' => array(
                        'required' => true,
                        'sanitize_callback' => 'floatval',
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0;
                        },
                    ),
                ),
            ),
        ));
        
        register_rest_route(self::NAMESPACE, '/art/(?P<id>\d+)/bids', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_art_bids'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'id' => array(
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0;
                        },
                    ),
                ),
            ),
        ));
        
        // Favorites
        register_rest_route(self::NAMESPACE, '/favorites', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_favorites'),
                'permission_callback' => array($this, 'check_bidder_permission'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'toggle_favorite'),
                'permission_callback' => array($this, 'check_bidder_permission'),
                'args' => array(
                    'art_piece_id' => array(
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            ),
        ));
        
        // Auth
        register_rest_route(self::NAMESPACE, '/auth/verify', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'verify_code'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'code' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ),
        ));
        
        register_rest_route(self::NAMESPACE, '/auth/status', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_auth_status'),
                'permission_callback' => '__return_true',
            ),
        ));
        
        register_rest_route(self::NAMESPACE, '/auth/logout', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'logout'),
                'permission_callback' => '__return_true',
            ),
        ));
        
        // Checkout
        register_rest_route(self::NAMESPACE, '/checkout/won-items', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_won_items'),
                'permission_callback' => array($this, 'check_bidder_permission'),
            ),
        ));
        
        register_rest_route(self::NAMESPACE, '/checkout/create-order', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_order'),
                'permission_callback' => array($this, 'check_bidder_permission'),
                'args' => array(
                    'item_ids' => array(
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_array($param) && !empty($param);
                        },
                    ),
                ),
            ),
        ));
        
        // Stats (admin only)
        register_rest_route(self::NAMESPACE, '/stats', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_stats'),
                'permission_callback' => array($this, 'check_admin_permission'),
            ),
        ));
    }
    
    /**
     * Get art collection arguments
     */
    private function get_art_collection_args() {
        return array(
            'status' => array(
                'default' => 'active',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function($param) {
                    return in_array($param, array('active', 'ended', 'draft', 'all'));
                },
            ),
            'search' => array(
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'orderby' => array(
                'default' => 'auction_end',
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => function($param) {
                    return in_array($param, array('id', 'title', 'artist', 'current_bid', 'auction_end', 'created_at'));
                },
            ),
            'order' => array(
                'default' => 'ASC',
                'sanitize_callback' => function($param) {
                    return strtoupper($param) === 'DESC' ? 'DESC' : 'ASC';
                },
            ),
            'per_page' => array(
                'default' => 50,
                'sanitize_callback' => 'absint',
                'validate_callback' => function($param) {
                    return $param >= 1 && $param <= 100;
                },
            ),
            'page' => array(
                'default' => 1,
                'sanitize_callback' => 'absint',
            ),
        );
    }
    
    /**
     * Check if bidder is logged in
     */
    public function check_bidder_permission() {
        $auth = AIH_Auth::get_instance();
        return $auth->is_logged_in();
    }
    
    /**
     * Check if user has admin permissions
     */
    public function check_admin_permission() {
        return current_user_can('manage_options') || AIH_Roles::can_manage_auction();
    }
    
    /**
     * GET /art - Get art pieces
     */
    public function get_art_pieces($request) {
        $status = $request->get_param('status');

        // Non-admins cannot access draft or all statuses
        if (in_array($status, array('draft', 'all'), true) && !$this->check_admin_permission()) {
            $status = 'active';
        }

        $args = array(
            'status' => $status,
            'search' => $request->get_param('search'),
            'orderby' => $request->get_param('orderby'),
            'order' => $request->get_param('order'),
            'limit' => $request->get_param('per_page'),
            'offset' => ($request->get_param('page') - 1) * $request->get_param('per_page'),
        );

        if ($args['status'] === 'all') {
            unset($args['status']);
        }
        
        $auth = AIH_Auth::get_instance();
        $args['bidder_id'] = $auth->get_current_bidder_id();
        
        $art_model = new AIH_Art_Piece();
        $pieces = $art_model->get_all($args);
        $total = $art_model->get_count($args);
        
        // Batch-fetch winning IDs to avoid N+1 queries
        $batch_data = null;
        if ($args['bidder_id'] && !empty($pieces)) {
            $piece_ids = array_map(function($p) { return intval($p->id); }, $pieces);
            $bid_model = new AIH_Bid();
            $batch_data = array('winning_ids' => $bid_model->get_winning_ids_batch($piece_ids, $args['bidder_id']));
        }

        $data = array();
        foreach ($pieces as $piece) {
            $data[] = $this->format_art_piece($piece, $args['bidder_id'], false, $batch_data);
        }
        
        $response = new WP_REST_Response($data);
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', ceil($total / $request->get_param('per_page')));
        
        return $response;
    }
    
    /**
     * GET /art/{id} - Get single art piece
     */
    public function get_art_piece($request) {
        $id = $request->get_param('id');
        
        $art_model = new AIH_Art_Piece();
        $piece = $art_model->get($id);
        
        if (!$piece) {
            return new WP_Error('not_found', __('Art piece not found.', 'art-in-heaven'), array('status' => 404));
        }
        
        $auth = AIH_Auth::get_instance();
        $bidder_id = $auth->get_current_bidder_id();
        
        $data = $this->format_art_piece($piece, $bidder_id, true);
        
        // Add bid history if logged in
        if ($bidder_id) {
            $bid_model = new AIH_Bid();
            $bids = $bid_model->get_bidder_bids_for_art_piece($id, $bidder_id);
            $data['user_bids'] = array();
            foreach ($bids as $bid) {
                $data['user_bids'][] = array(
                    'amount' => floatval($bid->bid_amount),
                    'time' => $bid->bid_time,
                    'is_winning' => (bool) $bid->is_winning,
                );
            }
            
            $favorites = new AIH_Favorites();
            $data['is_favorite'] = $favorites->is_favorite($bidder_id, $id);
        }
        
        return rest_ensure_response($data);
    }
    
    /**
     * POST /bids - Create a bid
     */
    public function create_bid($request) {
        $art_piece_id = $request->get_param('art_piece_id');
        $amount = $request->get_param('amount');
        
        $auth = AIH_Auth::get_instance();
        $bidder_id = $auth->get_current_bidder_id();
        
        // Rate limiting
        if (!AIH_Security::check_rate_limit('bid_' . $bidder_id, 10, 60)) {
            return new WP_Error('rate_limited', __('Too many bid attempts. Please wait.', 'art-in-heaven'), array('status' => 429));
        }
        
        $bid_model = new AIH_Bid();
        $result = $bid_model->place_bid($art_piece_id, $bidder_id, $amount);
        
        if (!$result['success']) {
            return new WP_Error('bid_failed', $result['message'], array('status' => 400));
        }
        
        // Get updated art piece
        $art_model = new AIH_Art_Piece();
        $piece = $art_model->get($art_piece_id);
        
        // Get highest bid for this art piece
        $bid_model_check = new AIH_Bid();
        $highest = $bid_model_check->get_highest_bid_amount($art_piece_id);

        return rest_ensure_response(array(
            'success' => true,
            'message' => $result['message'],
            'bid_id' => $result['bid_id'],
            'current_bid' => floatval($highest ?: 0),
        ));
    }
    
    /**
     * GET /art/{id}/bids - Get bids for an art piece
     */
    public function get_art_bids($request) {
        $id = $request->get_param('id');
        
        $bid_model = new AIH_Bid();
        $bids = $bid_model->get_bids_for_art_piece($id, 20);
        
        $data = array();
        foreach ($bids as $bid) {
            $data[] = array(
                'amount' => floatval($bid->bid_amount),
                'time' => $bid->bid_time,
                'is_winning' => (bool) $bid->is_winning,
                // Mask bidder for privacy
                'bidder' => substr($bid->bidder_id, 0, 3) . '***',
            );
        }
        
        return rest_ensure_response($data);
    }
    
    /**
     * GET /favorites - Get user's favorites
     */
    public function get_favorites($request) {
        $auth = AIH_Auth::get_instance();
        $bidder_id = $auth->get_current_bidder_id();
        
        $favorites = new AIH_Favorites();
        $items = $favorites->get_bidder_favorites($bidder_id);
        
        $data = array();
        foreach ($items as $item) {
            $data[] = $this->format_art_piece($item, $bidder_id);
        }
        
        return rest_ensure_response($data);
    }
    
    /**
     * POST /favorites - Toggle favorite
     */
    public function toggle_favorite($request) {
        $art_piece_id = $request->get_param('art_piece_id');
        
        $auth = AIH_Auth::get_instance();
        $bidder_id = $auth->get_current_bidder_id();
        
        $favorites = new AIH_Favorites();
        $result = $favorites->toggle($bidder_id, $art_piece_id);
        
        return rest_ensure_response($result);
    }
    
    /**
     * POST /auth/verify - Verify confirmation code
     */
    public function verify_code($request) {
        $code = $request->get_param('code');
        
        // Rate limiting
        $ip = AIH_Security::get_client_ip();
        if (!AIH_Security::check_rate_limit('auth_' . $ip, 5, 60)) {
            return new WP_Error('rate_limited', __('Too many attempts. Please wait.', 'art-in-heaven'), array('status' => 429));
        }
        
        $auth = AIH_Auth::get_instance();
        $result = $auth->verify_confirmation_code($code);
        
        if (!$result['success']) {
            return new WP_Error('invalid_code', $result['message'], array('status' => 401));
        }
        
        // Log in the bidder
        $auth->login_bidder($result['bidder']['confirmation_code']);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => sprintf(__('Welcome, %s!', 'art-in-heaven'), $result['bidder']['first_name']),
            'bidder' => array(
                'email' => $result['bidder']['email'],
                'first_name' => $result['bidder']['first_name'],
                'last_name' => $result['bidder']['last_name'],
            ),
        ));
    }
    
    /**
     * GET /auth/status - Get auth status
     */
    public function get_auth_status($request) {
        $auth = AIH_Auth::get_instance();
        $bidder = $auth->get_current_bidder();
        
        return rest_ensure_response(array(
            'logged_in' => $auth->is_logged_in(),
            'bidder' => $bidder ? array(
                'email' => $bidder->email_primary,
                'first_name' => $bidder->name_first,
                'last_name' => $bidder->name_last,
            ) : null,
        ));
    }
    
    /**
     * POST /auth/logout - Logout
     */
    public function logout($request) {
        $auth = AIH_Auth::get_instance();
        $auth->logout_bidder();
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Logged out.', 'art-in-heaven'),
        ));
    }
    
    /**
     * GET /checkout/won-items - Get won items
     */
    public function get_won_items($request) {
        $auth = AIH_Auth::get_instance();
        $bidder_id = $auth->get_current_bidder_id();
        
        $checkout = AIH_Checkout::get_instance();
        $items = $checkout->get_won_items($bidder_id);
        
        return rest_ensure_response($items);
    }
    
    /**
     * POST /checkout/create-order - Create order
     */
    public function create_order($request) {
        $item_ids = $request->get_param('item_ids');
        
        $auth = AIH_Auth::get_instance();
        $bidder_id = $auth->get_current_bidder_id();
        
        $checkout = AIH_Checkout::get_instance();
        $result = $checkout->create_order($bidder_id, $item_ids);
        
        if (isset($result['error'])) {
            return new WP_Error('order_failed', $result['error'], array('status' => 400));
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * GET /stats - Get auction stats (admin)
     */
    public function get_stats($request) {
        return rest_ensure_response(AIH_Export::get_auction_stats());
    }
    
    /**
     * Format art piece for API response
     * Uses consolidated AIH_Template_Helper::format_art_piece()
     */
    private function format_art_piece($piece, $bidder_id = null, $full = false, $batch_data = null) {
        return AIH_Template_Helper::format_art_piece($piece, $bidder_id, $full, false, $batch_data);
    }
}
