<?php
namespace JGrants;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cron Job Management Class
 */
class Cron {
    
    private $sync_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->sync_manager = new Sync_Manager();
        
        // Register cron hooks
        add_action('jgrants_sync_grants', [$this, 'run_sync']);
        add_action('jgrants_cleanup_old_grants', [$this, 'cleanup_old_grants']);
        add_action('jgrants_check_deadlines', [$this, 'check_deadlines']);
        
        // Add custom cron schedules
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['every_six_hours'] = [
            'interval' => 21600,
            'display' => __('Every 6 hours', 'jgrants-integration')
        ];
        
        $schedules['every_twelve_hours'] = [
            'interval' => 43200,
            'display' => __('Every 12 hours', 'jgrants-integration')
        ];
        
        return $schedules;
    }
    
    /**
     * Run grant synchronization
     */
    public function run_sync() {
        // Check if auto sync is enabled
        if (!get_option('auto_sync_enabled', true)) {
            return;
        }
        
        // Run sync
        $result = $this->sync_manager->sync_grants();
        
        // Log result
        if (is_wp_error($result)) {
            error_log('JGrants Cron Sync Error: ' . $result->get_error_message());
        } else {
            error_log(sprintf(
                'JGrants Cron Sync Success: %d fetched, %d created, %d updated',
                $result['fetched'],
                $result['created'],
                $result['updated']
            ));
        }
    }
    
    /**
     * Clean up old expired grants
     */
    public function cleanup_old_grants() {
        $days = get_option('cleanup_days', 90);
        $deleted = $this->sync_manager->cleanup_old_grants($days);
        
        error_log('JGrants Cleanup: ' . $deleted . ' old grants deleted');
    }
    
    /**
     * Check and update grant deadlines
     */
    public function check_deadlines() {
        $args = [
            'post_type' => 'grant',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_grant_status',
                    'value' => 'active',
                    'compare' => '='
                ],
                [
                    'key' => '_deadline',
                    'value' => date('Y-m-d'),
                    'compare' => '<',
                    'type' => 'DATE'
                ]
            ],
            'posts_per_page' => -1,
            'fields' => 'ids'
        ];
        
        $query = new \WP_Query($args);
        $expired_count = 0;
        
        if ($query->have_posts()) {
            foreach ($query->posts as $post_id) {
                // Update status to closed
                update_post_meta($post_id, '_grant_status', 'closed');
                
                // Update post status to expired
                wp_update_post([
                    'ID' => $post_id,
                    'post_status' => 'expired'
                ]);
                
                $expired_count++;
            }
        }
        
        if ($expired_count > 0) {
            error_log('JGrants Deadline Check: ' . $expired_count . ' grants expired');
        }
    }
    
    /**
     * Schedule all cron jobs
     */
    public static function schedule_events() {
        // Schedule main sync
        $sync_interval = get_option('sync_interval', 24);
        $schedule = 'daily';
        
        if ($sync_interval <= 6) {
            $schedule = 'every_six_hours';
        } elseif ($sync_interval <= 12) {
            $schedule = 'every_twelve_hours';
        }
        
        if (!wp_next_scheduled('jgrants_sync_grants')) {
            wp_schedule_event(time(), $schedule, 'jgrants_sync_grants');
        }
        
        // Schedule cleanup
        if (!wp_next_scheduled('jgrants_cleanup_old_grants')) {
            wp_schedule_event(time(), 'weekly', 'jgrants_cleanup_old_grants');
        }
        
        // Schedule deadline check
        if (!wp_next_scheduled('jgrants_check_deadlines')) {
            wp_schedule_event(time(), 'daily', 'jgrants_check_deadlines');
        }
    }
    
    /**
     * Unschedule all cron jobs
     */
    public static function unschedule_events() {
        wp_clear_scheduled_hook('jgrants_sync_grants');
        wp_clear_scheduled_hook('jgrants_cleanup_old_grants');
        wp_clear_scheduled_hook('jgrants_check_deadlines');
    }
}