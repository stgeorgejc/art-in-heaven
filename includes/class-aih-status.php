<?php
/**
 * Status Helper Class
 * 
 * Centralized, robust status computation for art pieces.
 * Handles timezone issues, null values, data inconsistencies, and extensibility.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Status {
    
    /**
     * Valid status values
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_DRAFT = 'draft';
    const STATUS_ENDED = 'ended';
    const STATUS_PAUSED = 'paused';
    const STATUS_CANCELED = 'canceled';
    
    /**
     * All valid statuses for validation
     */
    private static $valid_statuses = array(
        self::STATUS_ACTIVE,
        self::STATUS_DRAFT,
        self::STATUS_ENDED,
        self::STATUS_PAUSED,
        self::STATUS_CANCELED,
    );
    
    /**
     * Statuses that prevent bidding regardless of time
     */
    private static $closed_statuses = array(
        self::STATUS_ENDED,
        self::STATUS_PAUSED,
        self::STATUS_CANCELED,
    );
    
    /**
     * Get the current WordPress time as a DateTime object
     * Uses WordPress's configured timezone from Settings > General
     * 
     * @return DateTime
     */
    public static function get_now() {
        // Use current_time to get WordPress's local time, then create DateTime
        // current_time('mysql') returns time in WordPress's configured timezone
        $wp_timezone = wp_timezone();
        $local_time = current_time('mysql');
        return new DateTime($local_time, $wp_timezone);
    }
    
    /**
     * Get the current WordPress time as a formatted string
     * 
     * @param string $format PHP date format
     * @return string
     */
    public static function get_now_string($format = 'Y-m-d H:i:s') {
        return current_time($format);
    }
    
    /**
     * Parse a date string into a DateTime object using WordPress timezone
     * 
     * @param mixed $date_value The date value (string, DateTime, or null)
     * @return DateTime|null Returns DateTime or null if invalid/empty
     */
    public static function parse_date($date_value) {
        if (empty($date_value)) {
            return null;
        }
        
        if ($date_value instanceof DateTime) {
            return $date_value;
        }
        
        if ($date_value instanceof DateTimeImmutable) {
            return DateTime::createFromImmutable($date_value);
        }
        
        if (!is_string($date_value)) {
            return null;
        }
        
        try {
            $wp_timezone = wp_timezone();
            $dt = new DateTime($date_value, $wp_timezone);
            return $dt;
        } catch (Exception $e) {
            error_log('AIH_Status::parse_date failed: ' . $e->getMessage() . ' for value: ' . print_r($date_value, true));
            return null;
        }
    }
    
    /**
     * Format a DateTime for display
     * 
     * @param DateTime|null $dt
     * @param string $format
     * @return string
     */
    public static function format_date($dt, $format = 'M j, Y g:i A') {
        if (!$dt instanceof DateTime) {
            return '—';
        }
        return $dt->format($format);
    }
    
    /**
     * Check if a status value is valid
     * 
     * @param string $status
     * @return bool
     */
    public static function is_valid_status($status) {
        return in_array($status, self::$valid_statuses, true);
    }
    
    /**
     * Check if a status prevents bidding
     * 
     * @param string $status
     * @return bool
     */
    public static function is_closed_status($status) {
        return in_array($status, self::$closed_statuses, true);
    }
    
    /**
     * Validate an art piece object has required properties
     * 
     * @param object|null $art_piece
     * @return array Array with 'valid' bool and 'errors' array
     */
    public static function validate_art_piece($art_piece) {
        $errors = array();
        
        if (empty($art_piece)) {
            return array('valid' => false, 'errors' => array('Art piece is null or empty'));
        }
        
        if (!is_object($art_piece)) {
            return array('valid' => false, 'errors' => array('Art piece is not an object'));
        }
        
        $required_props = array('id', 'status', 'auction_start', 'auction_end');
        foreach ($required_props as $prop) {
            if (!property_exists($art_piece, $prop)) {
                $errors[] = "Missing property: {$prop}";
            }
        }
        
        if (!empty($art_piece->status) && !self::is_valid_status($art_piece->status)) {
            $errors[] = "Invalid status value: {$art_piece->status}";
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Check for data inconsistencies in an art piece
     * 
     * @param object $art_piece
     * @return array Array of warning messages
     */
    public static function check_data_inconsistencies($art_piece) {
        $warnings = array();
        
        $validation = self::validate_art_piece($art_piece);
        if (!$validation['valid']) {
            return $validation['errors'];
        }
        
        $start = self::parse_date($art_piece->auction_start);
        $end = self::parse_date($art_piece->auction_end);
        $now = self::get_now();
        
        // Check for missing dates
        if ($start === null) {
            $warnings[] = 'Auction start date is missing or invalid';
        }
        
        if ($end === null) {
            $warnings[] = 'Auction end date is missing or invalid';
        }
        
        // Check for start after end
        if ($start !== null && $end !== null && $start > $end) {
            $warnings[] = 'Auction start is after auction end (invalid date range)';
        }
        
        // Check for status/time mismatches
        if ($art_piece->status === self::STATUS_ENDED && $end !== null && $end > $now) {
            $warnings[] = 'Status is "ended" but end time is in the future';
        }
        
        if ($art_piece->status === self::STATUS_ACTIVE) {
            if ($start !== null && $start > $now) {
                $warnings[] = 'Status is "active" but start time is in the future';
            }
            if ($end !== null && $end <= $now) {
                $warnings[] = 'Status is "active" but end time has passed';
            }
        }
        
        if ($art_piece->status === self::STATUS_DRAFT && $start !== null && $end !== null) {
            if ($start <= $now && $end > $now) {
                $warnings[] = 'Status is "draft" but auction window is currently open';
            }
        }
        
        return $warnings;
    }
    
    /**
     * Compute the effective status of an art piece
     * 
     * This returns what the status SHOULD be based on database status and times.
     * 
     * @param object $art_piece
     * @return array {
     *     @type string $status The computed status
     *     @type string $display_status Human-readable status with context
     *     @type string $reason Why this status was computed
     *     @type bool $can_bid Whether bidding is allowed
     *     @type array $warnings Any data inconsistency warnings
     * }
     */
    public static function compute_status($art_piece) {
        $result = array(
            'status' => self::STATUS_DRAFT,
            'display_status' => 'Unknown',
            'reason' => '',
            'can_bid' => false,
            'warnings' => array(),
            'time_info' => array(),
        );
        
        // Validate art piece
        $validation = self::validate_art_piece($art_piece);
        if (!$validation['valid']) {
            $result['status'] = 'invalid';
            $result['display_status'] = 'Invalid Data';
            $result['reason'] = implode('; ', $validation['errors']);
            $result['warnings'] = $validation['errors'];
            return $result;
        }
        
        // Check for inconsistencies
        $result['warnings'] = self::check_data_inconsistencies($art_piece);
        
        // Parse dates
        $start = self::parse_date($art_piece->auction_start);
        $end = self::parse_date($art_piece->auction_end);
        $now = self::get_now();
        
        // Store time info for debugging
        $result['time_info'] = array(
            'now' => self::format_date($now),
            'start' => self::format_date($start),
            'end' => self::format_date($end),
            'start_valid' => $start !== null,
            'end_valid' => $end !== null,
        );
        
        $db_status = $art_piece->status ?? '';
        
        // Handle closed statuses first - these override time-based logic
        if (self::is_closed_status($db_status)) {
            $result['status'] = $db_status;
            $result['can_bid'] = false;
            
            switch ($db_status) {
                case self::STATUS_ENDED:
                    $result['display_status'] = 'Ended';
                    $result['reason'] = 'Database status is ended';
                    break;
                case self::STATUS_PAUSED:
                    $result['display_status'] = 'Paused';
                    $result['reason'] = 'Auction is paused by admin';
                    break;
                case self::STATUS_CANCELED:
                    $result['display_status'] = 'Canceled';
                    $result['reason'] = 'Auction was canceled';
                    break;
                default:
                    $result['display_status'] = ucfirst($db_status);
                    $result['reason'] = "Database status is {$db_status}";
            }
            
            return $result;
        }
        
        // Handle draft status
        if ($db_status === self::STATUS_DRAFT) {
            $result['status'] = self::STATUS_DRAFT;
            $result['display_status'] = 'Draft';
            $result['reason'] = 'Item is in draft mode (hidden from public)';
            $result['can_bid'] = false;
            return $result;
        }
        
        // For active status, check times
        if ($db_status === self::STATUS_ACTIVE) {
            // Handle missing dates
            if ($start === null && $end === null) {
                $result['status'] = self::STATUS_ACTIVE;
                $result['display_status'] = 'Active (no dates set)';
                $result['reason'] = 'Status is active but dates are missing';
                $result['can_bid'] = true; // Allow bidding if explicitly active
                return $result;
            }
            
            if ($end === null) {
                $result['status'] = self::STATUS_ACTIVE;
                $result['display_status'] = 'Active (no end date)';
                $result['reason'] = 'Status is active but end date is missing';
                $result['can_bid'] = ($start === null || $start <= $now);
                return $result;
            }
            
            if ($start === null) {
                // No start date - check end only
                if ($end <= $now) {
                    $result['status'] = self::STATUS_ENDED;
                    $result['display_status'] = 'Ended (time expired)';
                    $result['reason'] = 'Auction end time has passed';
                    $result['can_bid'] = false;
                } else {
                    $result['status'] = self::STATUS_ACTIVE;
                    $result['display_status'] = 'Active';
                    $result['reason'] = 'Auction is ongoing (no start date)';
                    $result['can_bid'] = true;
                }
                return $result;
            }
            
            // Both dates are valid - do full time check
            if ($start > $end) {
                // Invalid date range
                $result['status'] = 'invalid';
                $result['display_status'] = 'Invalid (start > end)';
                $result['reason'] = 'Auction start is after auction end';
                $result['can_bid'] = false;
                return $result;
            }
            
            if ($end <= $now) {
                $result['status'] = self::STATUS_ENDED;
                $result['display_status'] = 'Ended (time expired)';
                $result['reason'] = 'Auction end time has passed';
                $result['can_bid'] = false;
            } elseif ($start > $now) {
                $result['status'] = self::STATUS_DRAFT;
                $result['display_status'] = 'Upcoming (not started)';
                $result['reason'] = 'Auction start time has not arrived';
                $result['can_bid'] = false;
            } else {
                $result['status'] = self::STATUS_ACTIVE;
                $result['display_status'] = 'Active';
                $result['reason'] = 'Auction is currently running';
                $result['can_bid'] = true;
            }
            
            return $result;
        }
        
        // Unknown status
        $result['status'] = $db_status ?: 'unknown';
        $result['display_status'] = 'Unknown (' . ($db_status ?: 'empty') . ')';
        $result['reason'] = 'Unrecognized status value';
        $result['can_bid'] = false;
        
        return $result;
    }
    
    /**
     * Get a simple computed status string (for backward compatibility)
     * 
     * @param object $art_piece
     * @return string
     */
    public static function get_computed_status($art_piece) {
        $result = self::compute_status($art_piece);
        return $result['status'];
    }
    
    /**
     * Get display-friendly status string
     * 
     * @param object $art_piece
     * @return string
     */
    public static function get_display_status($art_piece) {
        $result = self::compute_status($art_piece);
        return $result['display_status'];
    }
    
    /**
     * Check if bidding is currently allowed on an art piece
     * 
     * @param object $art_piece
     * @return bool
     */
    public static function can_bid($art_piece) {
        $result = self::compute_status($art_piece);
        return $result['can_bid'];
    }
    
    /**
     * Calculate what status should be set based on times
     * Used when saving an art piece without force_status
     * 
     * @param string $auction_start MySQL datetime string
     * @param string $auction_end MySQL datetime string
     * @param string $requested_status The status the admin requested
     * @param bool $times_changed Whether auction times were modified
     * @return string The status that should be saved
     */
    public static function calculate_auto_status($auction_start, $auction_end, $requested_status = self::STATUS_ACTIVE, $times_changed = false) {
        $start = self::parse_date($auction_start);
        $end = self::parse_date($auction_end);
        $now = self::get_now();

        // Debug logging to trace status calculation
        error_log(sprintf(
            'AIH_Status::calculate_auto_status - start_raw: %s, end_raw: %s, requested: %s, times_changed: %s, now: %s, start_parsed: %s, end_parsed: %s, end<=now: %s, start>now: %s',
            var_export($auction_start, true),
            var_export($auction_end, true),
            $requested_status,
            $times_changed ? 'true' : 'false',
            $now->format('Y-m-d H:i:s T'),
            $start ? $start->format('Y-m-d H:i:s T') : 'null',
            $end ? $end->format('Y-m-d H:i:s T') : 'null',
            ($end !== null && $end <= $now) ? 'TRUE' : 'FALSE',
            ($start !== null && $start > $now) ? 'TRUE' : 'FALSE'
        ));

        // If requesting draft, respect it
        if ($requested_status === self::STATUS_DRAFT) {
            error_log('AIH_Status: Returning DRAFT (requested)');
            return self::STATUS_DRAFT;
        }

        // If requesting a closed status AND times didn't change, respect it
        // But if times changed, recalculate based on new times
        if (self::is_closed_status($requested_status) && !$times_changed) {
            error_log('AIH_Status: Returning ' . $requested_status . ' (closed, times unchanged)');
            return $requested_status;
        }

        // Calculate based on times
        if ($end !== null && $end <= $now) {
            error_log('AIH_Status: Returning ENDED (end <= now)');
            return self::STATUS_ENDED;
        }

        if ($start !== null && $start > $now) {
            error_log('AIH_Status: Returning DRAFT (start > now)');
            return self::STATUS_DRAFT;
        }

        error_log('AIH_Status: Returning ACTIVE (default)');
        return self::STATUS_ACTIVE;
    }
    
    /**
     * Get all valid status options for a select dropdown
     * 
     * @return array
     */
    public static function get_status_options() {
        return array(
            self::STATUS_ACTIVE => __('Active', 'art-in-heaven'),
            self::STATUS_DRAFT => __('Draft (hidden from public)', 'art-in-heaven'),
            self::STATUS_ENDED => __('Ended (closed for bidding)', 'art-in-heaven'),
            self::STATUS_PAUSED => __('Paused (temporarily closed)', 'art-in-heaven'),
            self::STATUS_CANCELED => __('Canceled', 'art-in-heaven'),
        );
    }
}
