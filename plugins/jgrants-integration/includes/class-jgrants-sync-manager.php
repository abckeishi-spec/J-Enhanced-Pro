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
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = new API_Client();
        $this->ai_generator = new AI_Generator();
    }
    
    /**
     * Sync grants from API with detailed options
     */
    public function sync_grants($params = []) {
        global $wpdb;
        
        // Get sync settings
        $settings = $this->get_sync_settings();
        $params = wp_parse_args($params, $settings);
        
        // Start sync log
        $log_id = $this->start_sync_log();
        
        try {
            // Search subsidies with parameters
            $subsidies = $this->api_client->search_subsidies($params);
            
            if (is_wp_error($subsidies)) {
                throw new \Exception($subsidies->get_error_message());
            }
            
            // Limit processing based on settings (since API doesn't have limit parameter)
            $max_import = intval($params['max_import_count'] ?? 100);
            if (count($subsidies) > $max_import) {
                $subsidies = array_slice($subsidies, 0, $max_import);
            }
            
            $stats = [
                'fetched' => count($subsidies),
                'created' => 0,
                'updated' => 0,
                'errors' => 0,
                'ai_generated' => 0
            ];
            
            // Process subsidies
            $batch_size = intval($params['batch_size'] ?? 10);
            $chunks = array_chunk($subsidies, $batch_size);
            
            foreach ($chunks as $chunk_index => $chunk) {
                foreach ($chunk as $subsidy_data) {
                    $result = $this->process_single_subsidy($subsidy_data, $params);
                    
                    if ($result['status'] === 'created') {
                        $stats['created']++;
                        if ($result['ai_generated']) {
                            $stats['ai_generated']++;
                        }
                    } elseif ($result['status'] === 'updated') {
                        $stats['updated']++;
                        if ($result['ai_generated']) {
                            $stats['ai_generated']++;
                        }
                    } else {
                        $stats['errors']++;
                    }
                }
                
                // Delay between batches
                $batch_delay = intval($params['batch_delay'] ?? 5);
                if ($chunk_index < count($chunks) - 1 && $batch_delay > 0) {
                    sleep($batch_delay);
                }
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
     * Manual import single subsidy by ID
     */
    public function import_subsidy_by_id($subsidy_id, $options = []) {
        try {
            // Get subsidy details
            $subsidy = $this->api_client->get_subsidy_by_id($subsidy_id);
            
            if (is_wp_error($subsidy)) {
                throw new \Exception($subsidy->get_error_message());
            }
            
            if (!$subsidy) {
                throw new \Exception('補助金が見つかりませんでした');
            }
            
            // Process the subsidy
            $result = $this->process_single_subsidy($subsidy, $options);
            
            return [
                'success' => $result['status'] !== 'error',
                'post_id' => $result['post_id'] ?? null,
                'status' => $result['status'],
                'message' => $result['message'] ?? ''
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'インポートエラー: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Manual import multiple subsidies
     */
    public function manual_import($search_params = []) {
        // Default search parameters for manual import
        $default_params = [
            'keyword' => $search_params['keyword'] ?? '補助金',
            'sort' => $search_params['sort'] ?? 'created_date',
            'order' => $search_params['order'] ?? 'DESC',
            'acceptance' => $search_params['acceptance'] ?? '0', // Get all by default
            'max_import_count' => intval($search_params['count'] ?? 10),
            'generate_ai_content' => ($search_params['generate_ai'] ?? true) === true || ($search_params['generate_ai'] ?? 'true') === 'true',
            'auto_publish' => ($search_params['auto_publish'] ?? false) === true || ($search_params['auto_publish'] ?? 'false') === 'true',
        ];
        
        // Add optional filters
        foreach (['use_purpose', 'industry', 'target_area_search', 'target_number_of_employees'] as $filter) {
            if (!empty($search_params[$filter])) {
                $default_params[$filter] = $search_params[$filter];
            }
        }
        
        return $this->sync_grants($default_params);
    }
    
    /**
     * Process single subsidy
     */
    private function process_single_subsidy($subsidy_data, $options = []) {
        try {
            // Check if subsidy already exists
            $existing_post = $this->find_existing_grant($subsidy_data['subsidy_id']);
            
            $post_id = null;
            $status = 'error';
            $ai_generated = false;
            
            if ($existing_post) {
                // Update existing grant
                if ($options['update_existing'] ?? true) {
                    $post_id = $this->update_grant($existing_post->ID, $subsidy_data, $options);
                    $status = 'updated';
                } else {
                    $post_id = $existing_post->ID;
                    $status = 'skipped';
                }
            } else {
                // Create new grant
                $post_id = $this->create_grant($subsidy_data, $options);
                $status = 'created';
            }
            
            // Generate AI content if enabled and post was created/updated
            if ($post_id && ($options['generate_ai_content'] ?? get_option('ai_content_generation', true))) {
                if ($status !== 'skipped') {
                    // Check if we should generate AI content
                    $should_generate = ($options['generate_ai_content'] ?? false) === true || 
                                     ($options['generate_ai_content'] ?? 'false') === 'true' ||
                                     get_option('sync_generate_ai', true);
                    
                    if ($should_generate) {
                        $ai_result = $this->ai_generator->generate_content_for_post($post_id);
                        $ai_generated = $ai_result === true;
                    }
                }
            }
            
            return [
                'status' => $status,
                'post_id' => $post_id,
                'ai_generated' => $ai_generated
            ];
            
        } catch (\Exception $e) {
            error_log('Error processing subsidy ' . $subsidy_data['subsidy_id'] . ': ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Find existing grant by subsidy_id
     */
    private function find_existing_grant($subsidy_id) {
        if (empty($subsidy_id)) {
            return null;
        }
        
        $args = [
            'post_type' => 'grant',
            'meta_query' => [
                [
                    'key' => '_subsidy_id',
                    'value' => $subsidy_id,
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
    private function create_grant($subsidy_data, $options = []) {
        // Determine post status
        $post_status = 'draft';
        $auto_publish = ($options['auto_publish'] ?? false) === true || 
                       ($options['auto_publish'] ?? 'false') === 'true' ||
                       get_option('auto_publish_grants', false);
        
        if ($auto_publish) {
            $post_status = 'publish';
        }
        
        // Prepare post data
        $post_data = [
            'post_type' => 'grant',
            'post_status' => $post_status,
            'post_title' => $subsidy_data['title'],
            'post_content' => $subsidy_data['description'],
            'meta_input' => $this->prepare_meta_data($subsidy_data)
        ];
        
        // Insert post
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            throw new \Exception($post_id->get_error_message());
        }
        
        // Set taxonomies
        $this->set_grant_taxonomies($post_id, $subsidy_data);
        
        return $post_id;
    }
    
    /**
     * Update existing grant post
     */
    private function update_grant($post_id, $subsidy_data, $options = []) {
        // Prepare post data
        $post_data = [
            'ID' => $post_id,
            'post_title' => $subsidy_data['title'],
            'meta_input' => $this->prepare_meta_data($subsidy_data)
        ];
        
        // Update content based on settings
        if ($options['update_content'] ?? get_option('force_content_update', false)) {
            $post_data['post_content'] = $subsidy_data['description'];
        }
        
        // Update post
        $result = wp_update_post($post_data);
        
        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }
        
        // Update taxonomies
        $this->set_grant_taxonomies($post_id, $subsidy_data);
        
        // Update status based on deadline
        $this->update_grant_status($post_id, $subsidy_data);
        
        return $post_id;
    }
    
    /**
     * Prepare meta data for grant
     */
    private function prepare_meta_data($subsidy_data) {
        return [
            '_subsidy_id' => $subsidy_data['subsidy_id'] ?? '',
            '_subsidy_code' => $subsidy_data['subsidy_code'] ?? '',
            '_organization' => $subsidy_data['organization'] ?? '',
            '_max_amount' => $subsidy_data['max_amount'] ?? 0,
            '_min_amount' => $subsidy_data['min_amount'] ?? 0,
            '_subsidy_rate' => $subsidy_data['subsidy_rate'] ?? '',
            '_target' => $subsidy_data['target'] ?? '',
            '_purpose' => $subsidy_data['purpose'] ?? '',
            '_deadline' => $subsidy_data['deadline'] ?? '',
            '_application_start' => $subsidy_data['application_start'] ?? '',
            '_official_url' => $subsidy_data['official_url'] ?? '',
            '_grant_status' => $subsidy_data['status'] ?? '',
            '_industry' => $subsidy_data['industry'] ?? '',
            '_target_area' => $subsidy_data['target_area'] ?? '',
            '_target_employees' => $subsidy_data['target_number_of_employees'] ?? '',
            '_support_organization' => $subsidy_data['support_organization'] ?? '',
            '_created_date' => $subsidy_data['created_date'] ?? '',
            '_last_synced' => current_time('mysql'),
        ];
    }
    
    /**
     * Set grant taxonomies - Dynamic category creation
     */
    private function set_grant_taxonomies($post_id, $subsidy_data) {
        // Set or create category
        if (!empty($subsidy_data['category'])) {
            $term = term_exists($subsidy_data['category'], 'grant_category');
            if (!$term) {
                // Create new category dynamically
                $term = wp_insert_term($subsidy_data['category'], 'grant_category', [
                    'slug' => sanitize_title($subsidy_data['category'])
                ]);
            }
            if (!is_wp_error($term)) {
                $term_id = is_array($term) ? $term['term_id'] : $term;
                wp_set_object_terms($post_id, $term_id, 'grant_category');
            }
        }
        
        // Set prefecture
        if (!empty($subsidy_data['prefecture'])) {
            $prefecture_terms = [];
            $prefectures = is_array($subsidy_data['prefecture']) ? $subsidy_data['prefecture'] : [$subsidy_data['prefecture']];
            
            foreach ($prefectures as $prefecture) {
                $term = term_exists($prefecture, 'prefecture');
                if (!$term) {
                    $term = wp_insert_term($prefecture, 'prefecture');
                }
                if (!is_wp_error($term)) {
                    $prefecture_terms[] = is_array($term) ? $term['term_id'] : $term;
                }
            }
            
            if (!empty($prefecture_terms)) {
                wp_set_object_terms($post_id, $prefecture_terms, 'prefecture');
            }
        }
        
        // Set target (business type) based on industry
        if (!empty($subsidy_data['industry'])) {
            $industries = explode('、', $subsidy_data['industry']);
            $target_terms = [];
            
            foreach ($industries as $industry) {
                $industry = trim($industry);
                if (!empty($industry)) {
                    $term = term_exists($industry, 'grant_target');
                    if (!$term) {
                        $term = wp_insert_term($industry, 'grant_target', [
                            'slug' => sanitize_title($industry)
                        ]);
                    }
                    if (!is_wp_error($term)) {
                        $target_terms[] = is_array($term) ? $term['term_id'] : $term;
                    }
                }
            }
            
            if (!empty($target_terms)) {
                wp_set_object_terms($post_id, $target_terms, 'grant_target');
            }
        }
        
        // Set amount range
        if (!empty($subsidy_data['max_amount']) && $subsidy_data['max_amount'] > 0) {
            $amount_range = $this->determine_amount_range($subsidy_data['max_amount']);
            if ($amount_range) {
                $term = term_exists($amount_range, 'amount_range');
                if ($term && !is_wp_error($term)) {
                    $term_id = is_array($term) ? $term['term_id'] : $term;
                    wp_set_object_terms($post_id, $term_id, 'amount_range');
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
    private function update_grant_status($post_id, $subsidy_data) {
        $status = $subsidy_data['status'] ?? 'active';
        
        // Update post status based on grant status
        if ($status === 'closed' || $status === 'expired') {
            wp_update_post([
                'ID' => $post_id,
                'post_status' => 'expired'
            ]);
        } elseif ($status === 'upcoming') {
            // Keep as draft or scheduled
            $current_status = get_post_status($post_id);
            if ($current_status === 'publish') {
                // Don't change if already published
            } else {
                wp_update_post([
                    'ID' => $post_id,
                    'post_status' => 'future',
                    'post_date' => $subsidy_data['application_start'] ?? current_time('mysql')
                ]);
            }
        }
        
        update_post_meta($post_id, '_grant_status', $status);
    }
    
    /**
     * Get sync settings
     */
    private function get_sync_settings() {
        return [
            'keyword' => get_option('sync_default_keyword', '補助金'),
            'sort' => get_option('sync_sort_field', 'created_date'),
            'order' => get_option('sync_sort_order', 'DESC'),
            'acceptance' => get_option('sync_acceptance_filter', '0'),
            'max_import_count' => intval(get_option('sync_max_import', 50)),
            'batch_size' => intval(get_option('sync_batch_size', 10)),
            'batch_delay' => intval(get_option('sync_batch_delay', 5)),
            'generate_ai_content' => get_option('sync_generate_ai', true),
            'update_existing' => get_option('sync_update_existing', true),
            'auto_publish' => get_option('auto_publish_grants', false),
        ];
    }
    
    /**
     * Start sync log
     */
    private function start_sync_log() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jgrants_sync_log';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // Create table if it doesn't exist
            $this->create_sync_log_table();
        }
        
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
     * Create sync log table
     */
    private function create_sync_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'jgrants_sync_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sync_date datetime DEFAULT CURRENT_TIMESTAMP,
            grants_fetched int(11) DEFAULT 0,
            grants_created int(11) DEFAULT 0,
            grants_updated int(11) DEFAULT 0,
            ai_generated int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            error_message text,
            PRIMARY KEY (id),
            INDEX idx_sync_date (sync_date),
            INDEX idx_status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Complete sync log
     */
    private function complete_sync_log($log_id, $stats, $status, $error_message = '') {
        if (!$log_id) {
            return;
        }
        
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
            $update_data['ai_generated'] = $stats['ai_generated'] ?? 0;
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
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return [];
        }
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY sync_date DESC LIMIT %d",
                $limit
            )
        );
        
        return $results ?: [];
    }
    
    /**
     * AJAX handler for manual sync
     */
    public function ajax_sync_now() {
        check_ajax_referer('jgrants_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Get parameters from AJAX request
        $params = [
            'keyword' => sanitize_text_field($_POST['keyword'] ?? '補助金'),
            'count' => intval($_POST['count'] ?? 10),
            'generate_ai' => $_POST['generate_ai'] ?? 'true',
            'auto_publish' => $_POST['auto_publish'] ?? 'false',
        ];
        
        // Add optional filters
        foreach (['use_purpose', 'industry', 'target_area_search', 'target_number_of_employees'] as $filter) {
            if (!empty($_POST[$filter])) {
                $params[$filter] = sanitize_text_field($_POST[$filter]);
            }
        }
        
        // Run sync
        $result = $this->manual_import($params);
        
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
        } else {
            wp_send_json_success([
                'message' => sprintf(
                    '同期完了: %d件取得, %d件作成, %d件更新, %d件AI生成',
                    $result['fetched'],
                    $result['created'],
                    $result['updated'],
                    $result['ai_generated'] ?? 0
                ),
                'stats' => $result
            ]);
        }
    }
    
    /**
     * AJAX handler for importing single subsidy
     */
    public function ajax_import_single() {
        check_ajax_referer('jgrants_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $subsidy_id = sanitize_text_field($_POST['subsidy_id'] ?? '');
        
        if (empty($subsidy_id)) {
            wp_send_json_error(['message' => '補助金IDが指定されていません']);
        }
        
        $options = [
            'generate_ai_content' => ($_POST['generate_ai'] ?? 'true') === 'true',
            'auto_publish' => ($_POST['auto_publish'] ?? 'false') === 'true',
        ];
        
        $result = $this->import_subsidy_by_id($subsidy_id, $options);
        
        if ($result['success']) {
            $edit_link = '';
            if ($result['post_id']) {
                $edit_link = get_edit_post_link($result['post_id'], '');
            }
            
            wp_send_json_success([
                'message' => '補助金情報をインポートしました',
                'post_id' => $result['post_id'],
                'edit_link' => $edit_link
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message']
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
    
    /**
     * Get sync statistics
     */
    public function get_statistics() {
        global $wpdb;
        
        $total_grants = wp_count_posts('grant');
        $active_grants = get_posts([
            'post_type' => 'grant',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_grant_status',
                    'value' => 'active',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        
        $today = date('Y-m-d');
        $table_name = $wpdb->prefix . 'jgrants_sync_log';
        
        $today_syncs = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $today_syncs = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE DATE(sync_date) = %s",
                    $today
                )
            );
        }
        
        return [
            'total_grants' => intval($total_grants->publish + $total_grants->draft),
            'active_grants' => count($active_grants),
            'today_syncs' => $today_syncs,
            'last_sync' => $this->get_sync_history(1),
        ];
    }
}