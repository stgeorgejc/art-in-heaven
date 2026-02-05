<?php
/**
 * Art Piece Cron Scheduler
 *
 * Schedules precise cron events for art pieces to ensure they are
 * activated and ended at their exact scheduled times.
 *
 * Instead of relying solely on the 5-minute polling cron, this schedules
 * one-time events for each art piece's auction_start and auction_end times.
 *
 * @package ArtInHeaven
 * @since 0.9.156
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Cron_Scheduler {

    /** @var AIH_Cron_Scheduler|null */
    private static $instance = null;

    // Hook names for scheduled events
    const HOOK_ACTIVATE = 'aih_scheduled_activate_piece';
    const HOOK_END = 'aih_scheduled_end_piece';

    /**
     * Get single instance
     * @return AIH_Cron_Scheduler
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Listen for art piece changes
        add_action('aih_art_created', array($this, 'on_art_created'), 10, 2);
        add_action('aih_art_updated', array($this, 'on_art_updated'), 10, 2);
        add_action('aih_art_deleted', array($this, 'on_art_deleted'), 10, 1);

        // Register the callback handlers for scheduled events
        add_action(self::HOOK_ACTIVATE, array($this, 'handle_scheduled_activation'), 10, 1);
        add_action(self::HOOK_END, array($this, 'handle_scheduled_end'), 10, 1);
    }

    /**
     * Handle art piece creation - schedule events for start/end times
     *
     * @param int $art_id The database ID of the created art piece
     * @param array $data The art piece data
     */
    public function on_art_created($art_id, $data) {
        $this->schedule_events_for_piece($art_id, $data);
    }

    /**
     * Handle art piece update - reschedule events if times changed
     *
     * @param int $art_id The database ID of the art piece
     * @param array $data The updated data
     */
    public function on_art_updated($art_id, $data) {
        // Clear existing scheduled events for this piece
        $this->unschedule_events_for_piece($art_id);

        // Fetch fresh data from database to get all current values
        $art_piece = new AIH_Art_Piece();
        $piece = $art_piece->get($art_id);

        if ($piece) {
            $this->schedule_events_for_piece($art_id, array(
                'auction_start' => $piece->auction_start,
                'auction_end' => $piece->auction_end,
                'status' => $piece->status
            ));
        }
    }

    /**
     * Handle art piece deletion - clear any scheduled events
     *
     * @param int $art_id The database ID of the deleted art piece
     */
    public function on_art_deleted($art_id) {
        $this->unschedule_events_for_piece($art_id);
    }

    /**
     * Schedule activation and end events for an art piece
     *
     * @param int $art_id The database ID
     * @param array $data The art piece data containing auction_start, auction_end, status
     */
    public function schedule_events_for_piece($art_id, $data) {
        $wp_timezone = wp_timezone();
        $now = time(); // Real UTC Unix timestamp

        // Schedule activation event if piece is in draft and has a future start time
        if (isset($data['auction_start']) && !empty($data['auction_start'])) {
            // Convert local datetime to real UTC Unix timestamp
            $start_dt = new DateTime($data['auction_start'], $wp_timezone);
            $start_timestamp = $start_dt->getTimestamp();

            // Only schedule if the start time is in the future
            if ($start_timestamp && $start_timestamp > $now) {
                $status = isset($data['status']) ? $data['status'] : 'draft';

                // Schedule activation for draft pieces
                if ($status === 'draft') {
                    $this->schedule_activation($art_id, $start_timestamp);
                }
            }
        }

        // Schedule end event if piece has a future end time
        if (isset($data['auction_end']) && !empty($data['auction_end'])) {
            // Convert local datetime to real UTC Unix timestamp
            $end_dt = new DateTime($data['auction_end'], $wp_timezone);
            $end_timestamp = $end_dt->getTimestamp();

            // Only schedule if the end time is in the future
            if ($end_timestamp && $end_timestamp > $now) {
                $status = isset($data['status']) ? $data['status'] : 'active';

                // Schedule ending for active or draft pieces (draft will become active first)
                if (in_array($status, array('active', 'draft'))) {
                    $this->schedule_end($art_id, $end_timestamp);
                }
            }
        }
    }

    /**
     * Schedule a single activation event for an art piece
     *
     * @param int $art_id The database ID
     * @param int $timestamp Unix timestamp when to activate
     */
    public function schedule_activation($art_id, $timestamp) {
        // Clear any existing activation event for this piece
        $this->unschedule_event(self::HOOK_ACTIVATE, $art_id);

        // Schedule the new event
        $result = wp_schedule_single_event($timestamp, self::HOOK_ACTIVATE, array($art_id));

        if ($result) {
            error_log(sprintf(
                'AIH Cron: Scheduled activation for piece #%d at %s UTC (local: %s)',
                $art_id,
                gmdate('Y-m-d H:i:s', $timestamp),
                wp_date('Y-m-d H:i:s', $timestamp)
            ));
        }

        return $result;
    }

    /**
     * Schedule a single end event for an art piece
     *
     * @param int $art_id The database ID
     * @param int $timestamp Unix timestamp when to end
     */
    public function schedule_end($art_id, $timestamp) {
        // Clear any existing end event for this piece
        $this->unschedule_event(self::HOOK_END, $art_id);

        // Schedule the new event
        $result = wp_schedule_single_event($timestamp, self::HOOK_END, array($art_id));

        if ($result) {
            error_log(sprintf(
                'AIH Cron: Scheduled end for piece #%d at %s UTC (local: %s)',
                $art_id,
                gmdate('Y-m-d H:i:s', $timestamp),
                wp_date('Y-m-d H:i:s', $timestamp)
            ));
        }

        return $result;
    }

    /**
     * Unschedule a specific event for an art piece
     *
     * @param string $hook The hook name
     * @param int $art_id The database ID
     */
    private function unschedule_event($hook, $art_id) {
        $timestamp = wp_next_scheduled($hook, array($art_id));

        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook, array($art_id));
        }
    }

    /**
     * Unschedule all events for an art piece
     *
     * @param int $art_id The database ID
     */
    public function unschedule_events_for_piece($art_id) {
        $this->unschedule_event(self::HOOK_ACTIVATE, $art_id);
        $this->unschedule_event(self::HOOK_END, $art_id);
    }

    /**
     * Handle scheduled activation - change piece from draft to active
     *
     * @param int $art_id The database ID of the piece to activate
     */
    public function handle_scheduled_activation($art_id) {
        global $wpdb;

        try {
            if (!class_exists('AIH_Database') || !AIH_Database::tables_exist()) {
                return;
            }

            $table = AIH_Database::get_table('art_pieces');
            if (!$table) return;

            $now = current_time('mysql');

            // Activate piece if it's still in draft and hasn't ended
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE $table
                 SET status = 'active'
                 WHERE id = %d
                 AND status = 'draft'
                 AND (auction_end IS NULL OR auction_end > %s)",
                $art_id,
                $now
            ));

            if ($result > 0) {
                error_log(sprintf('AIH Cron: Activated piece #%d via scheduled event', $art_id));

                // Clear cache
                if (class_exists('AIH_Cache')) {
                    AIH_Cache::flush_all();
                }

                // Fire action for any listeners
                do_action('aih_piece_activated', $art_id);
            }
        } catch (Exception $e) {
            error_log('AIH Cron: Error in scheduled activation - ' . $e->getMessage());
        }
    }

    /**
     * Handle scheduled end - change piece from active to ended
     *
     * @param int $art_id The database ID of the piece to end
     */
    public function handle_scheduled_end($art_id) {
        global $wpdb;

        try {
            if (!class_exists('AIH_Database') || !AIH_Database::tables_exist()) {
                return;
            }

            $table = AIH_Database::get_table('art_pieces');
            if (!$table) return;

            // End the piece if it's currently active
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE $table
                 SET status = 'ended'
                 WHERE id = %d
                 AND status = 'active'",
                $art_id
            ));

            if ($result > 0) {
                error_log(sprintf('AIH Cron: Ended piece #%d via scheduled event', $art_id));

                // Clear cache
                if (class_exists('AIH_Cache')) {
                    AIH_Cache::flush_all();
                }

                // Fire action for any listeners
                do_action('aih_piece_ended', $art_id);
            }
        } catch (Exception $e) {
            error_log('AIH Cron: Error in scheduled end - ' . $e->getMessage());
        }
    }

    /**
     * Schedule events for all existing art pieces
     * Useful for initial setup or after plugin update
     */
    public static function schedule_all_existing_pieces() {
        global $wpdb;

        if (!class_exists('AIH_Database') || !AIH_Database::tables_exist()) {
            return;
        }

        $table = AIH_Database::get_table('art_pieces');
        if (!$table) return;

        $now = current_time('mysql');

        // Get all pieces that need scheduling
        $pieces = $wpdb->get_results($wpdb->prepare(
            "SELECT id, auction_start, auction_end, status
             FROM $table
             WHERE (
                 (status = 'draft' AND auction_start IS NOT NULL AND auction_start > %s)
                 OR
                 (status IN ('active', 'draft') AND auction_end IS NOT NULL AND auction_end > %s)
             )",
            $now,
            $now
        ));

        $scheduler = self::get_instance();
        $count = 0;

        foreach ($pieces as $piece) {
            $scheduler->schedule_events_for_piece($piece->id, array(
                'auction_start' => $piece->auction_start,
                'auction_end' => $piece->auction_end,
                'status' => $piece->status
            ));
            $count++;
        }

        error_log(sprintf('AIH Cron: Scheduled events for %d existing art pieces', $count));

        return $count;
    }

    /**
     * Clear all scheduled piece events
     * Useful for plugin deactivation
     */
    public static function clear_all_scheduled_events() {
        // WordPress doesn't have a built-in way to clear all events for a hook,
        // so we need to use the cron array directly
        $crons = _get_cron_array();

        if (empty($crons)) {
            return;
        }

        $cleared = 0;

        foreach ($crons as $timestamp => $cron) {
            // Clear activation events
            if (isset($cron[self::HOOK_ACTIVATE])) {
                foreach ($cron[self::HOOK_ACTIVATE] as $key => $event) {
                    wp_unschedule_event($timestamp, self::HOOK_ACTIVATE, $event['args']);
                    $cleared++;
                }
            }

            // Clear end events
            if (isset($cron[self::HOOK_END])) {
                foreach ($cron[self::HOOK_END] as $key => $event) {
                    wp_unschedule_event($timestamp, self::HOOK_END, $event['args']);
                    $cleared++;
                }
            }
        }

        if ($cleared > 0) {
            error_log(sprintf('AIH Cron: Cleared %d scheduled piece events', $cleared));
        }

        return $cleared;
    }

    /**
     * Get all scheduled events for debugging/admin display
     *
     * @return array List of scheduled events
     */
    public static function get_scheduled_events() {
        $crons = _get_cron_array();
        $events = array();

        if (empty($crons)) {
            return $events;
        }

        foreach ($crons as $timestamp => $cron) {
            // Activation events
            if (isset($cron[self::HOOK_ACTIVATE])) {
                foreach ($cron[self::HOOK_ACTIVATE] as $key => $event) {
                    $events[] = array(
                        'type' => 'activate',
                        'art_id' => isset($event['args'][0]) ? $event['args'][0] : null,
                        'timestamp' => $timestamp,
                        'datetime' => date('Y-m-d H:i:s', $timestamp)
                    );
                }
            }

            // End events
            if (isset($cron[self::HOOK_END])) {
                foreach ($cron[self::HOOK_END] as $key => $event) {
                    $events[] = array(
                        'type' => 'end',
                        'art_id' => isset($event['args'][0]) ? $event['args'][0] : null,
                        'timestamp' => $timestamp,
                        'datetime' => date('Y-m-d H:i:s', $timestamp)
                    );
                }
            }
        }

        // Sort by timestamp
        usort($events, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });

        return $events;
    }
}
