<?php
namespace JGrants;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sync Manager Class - Handles synchronization between JGrants API and WordPress
 */
class Sync_Manager {
    
    private $api_client;
    private $ai_generator;
    private $batch_size = 20;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = new API_Client();
        $this->ai_generator = new AI_Generator();
    }
    
    /**
     * Sync grants from API
     */
    public function sync_grants($params = []) {
        global $wpdb;
        
        // Start sync log
        $log_id = $this->start_sync_log();
        
        try {
            // Fetch grants from API
            $grants = $this->api_client->fetch_grants($params);
            
            if (is_wp_error($grants)) {
                throw new \Exception($grants->get_error_message());
            }
            
            $stats = [
                'fetched' => count($grants),
                'created' => 0,
                'updated' => 0,
                'errors' => 0
            ];
            
            // Process grants in batches
            $chunks = array_chunk($grants, $this->batch_size);
            
            foreach ($chunks as $chunk) {
                foreach ($chunk as $grant_data) {
                    $result = $this->process_single_grant($grant_data);
                    
                    if ($result === 'created') {
                        $stats['created']++;
                    } elseif ($result === 'updated') {
                        $stats['updated']++;
                    } else {
                        $stats['errors']++;
                    }
                }
                
                // Small delay between batches
                usleep(100000); // 100ms
            }
            
            // Update sync log
            $this->complete_sync_log($log_id, $stats, 'success');
            
            return $stats;
            
        } catch (\Exception $e) {
            error_log('JGrants Sync Error: ' . $e->getMessage());
            $this->complete_sync_log($log_id, [], 'error', $e->getMessage());
            return new \WP_Error('sync_error', $e->getMessage());
        }
    }
    
    /**
     * Process single grant
     */
    private function process_single_grant($grant_data) {
        try {
            // Check if grant already exists
            $existing_post = $this->find_existing_grant($grant_data['grant_id']);
            
            if ($existing_post) {
                // Update existing grant
                $post_id = $this->update_grant($existing_post->ID, $grant_data);
                return 'updated';
            } else {
                // Create new grant
                $post_id = $this->create_grant($grant_data);
                return 'created';
            }
            
        } catch (\Exception $e) {
            error_log('Error processing grant ' . $grant_data['grant_id'] . ': ' . $e->getMessage());
            return 'error';
        }
    }
    
    /**
     * Find existing grant by grant_id
     */
    private function find_existing_grant($grant_id) {
        $args = [
            'post_type' => 'grant',
            'meta_query' => [
                [
                    'key' => '_grant_id',
                    'value' => $grant_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'post_status' => 'any'
        ];
        
        $query = new \WP_Query($args);
        
        if ($query->have_posts()) {
            return $query->posts[0];
        }
        
        return null;
    }
    
    /**
     * Create new grant post
     */
    private function create_grant($grant_data) {
        // Prepare post data
        $post_data = [
            'post_type' => 'grant',
            'post_status' => 'draft', // Start as draft for review
            'post_title' => $grant_data['title'],
            'post_content' => $grant_data['description'],
            'meta_input' => $this->prepare_meta_data($grant_data)
        ];
        
        // Insert post
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            throw new \Exception($post_id->get_error_message());
        }
        
        // Set taxonomies
        $this->set_grant_taxonomies($post_id, $grant_data);
        
        // Generate AI content if enabled
        if (get_option('ai_content_generation', true)) {
            $this->ai_generator->generate_content_for_post($post_id);
        }
        
        // Publish if auto-publish is enabled
        if (get_option('auto_publish_grants', false)) {
            wp_update_post([
                'ID' => $post_id,
                'post_status' => 'publish'
            ]);
        }
        
        return $post_id;
    }
    
    /**
     * Update existing grant post
     */
    private function update_grant($post_id, $grant_data) {
        // Prepare post data
        $post_data = [
            'ID' => $post_id,
            'post_title' => $grant_data['title'],
            'meta_input' => $this->prepare_meta_data($grant_data)
        ];
        
        // Only update content if it's empty or if force update is enabled
        $existing_content = get_post_field('post_content', $post_id);
        if (empty($existing_content) || get_option('force_content_update', false)) {
            $post_data['post_content'] = $grant_data['description'];
        }
        
        // Update post
        $result = wp_update_post($post_data);
        
        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }
        
        // Update taxonomies
        $this->set_grant_taxonomies($post_id, $grant_data);
        
        // Update status based on deadline
        $this->update_grant_status($post_id, $grant_data);
        
        // Regenerate AI content if requested
        if (get_post_meta($post_id, '_ai_regenerate', true) === '1') {
            $this->ai_generator->generate_content_for_post($post_id);
            delete_post_meta($post_id, '_ai_regenerate');
        }
        
        return $post_id;
    }
    
    /**
     * Prepare meta data for grant
     */
    private function prepare_meta_data($grant_data) {
        return [
            '_grant_id' => $grant_data['grant_id'],
            '_organization' => $grant_data['organization'],
            '_max_amount' => $grant_data['max_amount'],
            '_min_amount' => $grant_data['min_amount'],
            '_subsidy_rate' => $grant_data['subsidy_rate'],
            '_target' => $grant_data['target'],
            '_purpose' => $grant_data['purpose'],
            '_deadline' => $grant_data['deadline'],
            '_application_start' => $grant_data['application_start'],
            '_official_url' => $grant_data['official_url'],
            '_grant_status' => $grant_data['status'],
            '_last_synced' => current_time('mysql'),
        ];
    }
    
    /**
     * Set grant taxonomies
     */
    private function set_grant_taxonomies($post_id, $grant_data) {
        // Set category if provided
        if (!empty($grant_data['category'])) {
            $term = term_exists($grant_data['category'], 'grant_category');
            if ($term) {
                wp_set_object_terms($post_id, $term['term_id'], 'grant_category');
            }
        }
        
        // Set prefecture if provided
        if (!empty($grant_data['prefecture'])) {
            $term = term_exists($grant_data['prefecture'], 'prefecture');
            if ($term) {
                wp_set_object_terms($post_id, $term['term_id'], 'prefecture');
            }
        }
        
        // Set amount range based on max amount
        if (!empty($grant_data['max_amount'])) {
            $amount_range = $this->determine_amount_range($grant_data['max_amount']);
            if ($amount_range) {
                $term = term_exists($amount_range, 'amount_range');
                if ($term) {
                    wp_set_object_terms($post_id, $term['term_id'], 'amount_range');
                }
            }
        }
    }
    
    /**
     * Determine amount range category
     */
    private function determine_amount_range($max_amount) {
        $amount = intval($max_amount);
        
        if ($amount < 1000000) {
            return '〜100万円';
        } elseif ($amount < 5000000) {
            return '100万円〜500万円';
        } elseif ($amount < 10000000) {
            return '500万円〜1000万円';
        } elseif ($amount < 30000000) {
            return '1000万円〜3000万円';
        } elseif ($amount < 50000000) {
            return '3000万円〜5000万円';
        } elseif ($amount < 100000000) {
            return '5000万円〜1億円';
        } else {
            return '1億円以上';
        }
    }
    
    /**
     * Update grant status based on deadline
     */
    private function update_grant_status($post_id, $grant_data) {
        $status = $grant_data['status'];
        
        // Check if deadline has passed
        if (!empty($grant_data['deadline'])) {
            $deadline = strtotime($grant_data['deadline']);
            if ($deadline && $deadline < time()) {
                $status = 'closed';
                
                // Update post status to expired
                wp_update_post([
                    'ID' => $post_id,
                    'post_status' => 'expired'
                ]);
            }
        }
        
        update_post_meta($post_id, '_grant_status', $status);
    }
    
    /**
     * Start sync log
     */
    private function start_sync_log() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jgrants_sync_log';
        
        $wpdb->insert(
            $table_name,
            [
                'sync_date' => current_time('mysql'),
                'status' => 'in_progress'
            ]
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Complete sync log
     */
    private function complete_sync_log($log_id, $stats, $status, $error_message = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jgrants_sync_log';
        
        $update_data = [
            'status' => $status,
            'error_message' => $error_message
        ];
        
        if (!empty($stats)) {
            $update_data['grants_fetched'] = $stats['fetched'] ?? 0;
            $update_data['grants_created'] = $stats['created'] ?? 0;
            $update_data['grants_updated'] = $stats['updated'] ?? 0;
        }
        
        $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $log_id]
        );
    }
    
    /**
     * Get sync history
     */
    public function get_sync_history($limit = 10) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jgrants_sync_log';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY sync_date DESC LIMIT %d",
                $limit
            )
        );
        
        return $results;
    }
    
    /**
     * AJAX handler for manual sync
     */
    public function ajax_sync_now() {
        check_ajax_referer('jgrants_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Run sync
        $result = $this->sync_grants();
        
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
        } else {
            wp_send_json_success([
                'message' => sprintf(
                    '同期完了: %d件取得, %d件作成, %d件更新',
                    $result['fetched'],
                    $result['created'],
                    $result['updated']
                ),
                'stats' => $result
            ]);
        }
    }
    
    /**
     * REST API handler for sync
     */
    public function rest_sync_grants($request) {
        $params = $request->get_params();
        
        // Run sync
        $result = $this->sync_grants($params);
        
        if (is_wp_error($result)) {
            return new \WP_Error(
                'sync_failed',
                $result->get_error_message(),
                ['status' => 500]
            );
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'stats' => $result,
            'message' => sprintf(
                '同期完了: %d件取得, %d件作成, %d件更新',
                $result['fetched'],
                $result['created'],
                $result['updated']
            )
        ], 200);
    }
    
    /**
     * Clean up old grants
     */
    public function cleanup_old_grants($days = 90) {
        $args = [
            'post_type' => 'grant',
            'post_status' => 'expired',
            'date_query' => [
                [
                    'column' => 'post_modified',
                    'before' => $days . ' days ago',
                ]
            ],
            'posts_per_page' => -1,
            'fields' => 'ids'
        ];
        
        $query = new \WP_Query($args);
        $deleted = 0;
        
        if ($query->have_posts()) {
            foreach ($query->posts as $post_id) {
                wp_delete_post($post_id, true);
                $deleted++;
            }
        }
        
        return $deleted;
    }
}