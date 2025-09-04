<?php
namespace JGrants;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core plugin class
 */
class Core {
    
    private static $instance = null;
    private $api_client;
    private $post_type;
    private $taxonomies;
    private $ai_generator;
    private $sync_manager;
    private $admin;
    private $cron;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Constructor is private for singleton pattern
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Initialize components
        $this->api_client = new API_Client();
        $this->post_type = new Post_Type();
        $this->taxonomies = new Taxonomies();
        $this->ai_generator = new AI_Generator();
        $this->sync_manager = new Sync_Manager();
        $this->cron = new Cron();
        
        // Initialize admin if in admin area
        if (is_admin()) {
            $this->admin = new \JGrants\Admin\Admin();
            $this->admin->init();
        }
        
        // Register hooks
        $this->register_hooks();
    }
    
    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Register post type and taxonomies
        add_action('init', [$this->post_type, 'register']);
        add_action('init', [$this->taxonomies, 'register']);
        
        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Register AJAX handlers
        add_action('wp_ajax_jgrants_sync_now', [$this->sync_manager, 'ajax_sync_now']);
        add_action('wp_ajax_jgrants_test_connection', [$this->api_client, 'ajax_test_connection']);
        add_action('wp_ajax_jgrants_regenerate_content', [$this->ai_generator, 'ajax_regenerate_content']);
        
        // Add custom columns to grant post list
        add_filter('manage_grant_posts_columns', [$this, 'add_grant_columns']);
        add_action('manage_grant_posts_custom_column', [$this, 'render_grant_columns'], 10, 2);
        
        // Add meta boxes
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_grant', [$this, 'save_grant_meta'], 10, 2);
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('jgrants/v1', '/sync', [
            'methods' => 'POST',
            'callback' => [$this->sync_manager, 'rest_sync_grants'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
        
        register_rest_route('jgrants/v1', '/grants', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_grants'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('jgrants/v1', '/regenerate/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this->ai_generator, 'rest_regenerate_content'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ]);
    }
    
    /**
     * Add custom columns to grant post list
     */
    public function add_grant_columns($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['grant_status'] = __('ステータス', 'jgrants-integration');
                $new_columns['max_amount'] = __('最大支援額', 'jgrants-integration');
                $new_columns['deadline'] = __('締切', 'jgrants-integration');
                $new_columns['prefecture'] = __('対象地域', 'jgrants-integration');
            }
        }
        return $new_columns;
    }
    
    /**
     * Render custom columns for grant posts
     */
    public function render_grant_columns($column, $post_id) {
        switch ($column) {
            case 'grant_status':
                $status = get_post_meta($post_id, '_grant_status', true);
                $status_class = $status === 'active' ? 'success' : 'warning';
                echo '<span class="grant-status grant-status-' . esc_attr($status_class) . '">' . 
                     esc_html($status === 'active' ? '募集中' : '終了') . '</span>';
                break;
                
            case 'max_amount':
                $amount = get_post_meta($post_id, '_max_amount', true);
                if ($amount) {
                    echo '¥' . number_format((int)$amount);
                } else {
                    echo '—';
                }
                break;
                
            case 'deadline':
                $deadline = get_post_meta($post_id, '_deadline', true);
                if ($deadline) {
                    echo date('Y年m月d日', strtotime($deadline));
                } else {
                    echo '—';
                }
                break;
                
            case 'prefecture':
                $terms = get_the_terms($post_id, 'prefecture');
                if ($terms && !is_wp_error($terms)) {
                    $prefecture_names = wp_list_pluck($terms, 'name');
                    echo esc_html(implode(', ', $prefecture_names));
                } else {
                    echo '全国';
                }
                break;
        }
    }
    
    /**
     * Add meta boxes for grant posts
     */
    public function add_meta_boxes() {
        add_meta_box(
            'grant_details',
            __('補助金詳細情報', 'jgrants-integration'),
            [$this, 'render_grant_details_meta_box'],
            'grant',
            'normal',
            'high'
        );
        
        add_meta_box(
            'grant_ai_settings',
            __('AI生成設定', 'jgrants-integration'),
            [$this, 'render_grant_ai_meta_box'],
            'grant',
            'side',
            'default'
        );
    }
    
    /**
     * Render grant details meta box
     */
    public function render_grant_details_meta_box($post) {
        wp_nonce_field('grant_meta_box', 'grant_meta_box_nonce');
        
        $fields = [
            'grant_id' => ['label' => '補助金ID', 'type' => 'text'],
            'organization' => ['label' => '実施機関', 'type' => 'text'],
            'max_amount' => ['label' => '最大支援額', 'type' => 'number'],
            'min_amount' => ['label' => '最小支援額', 'type' => 'number'],
            'subsidy_rate' => ['label' => '補助率', 'type' => 'text'],
            'target' => ['label' => '対象者', 'type' => 'textarea'],
            'purpose' => ['label' => '目的', 'type' => 'textarea'],
            'deadline' => ['label' => '締切日', 'type' => 'date'],
            'application_start' => ['label' => '申請開始日', 'type' => 'date'],
            'official_url' => ['label' => '公式URL', 'type' => 'url'],
            'grant_status' => ['label' => 'ステータス', 'type' => 'select', 'options' => ['active' => '募集中', 'closed' => '終了']],
        ];
        
        foreach ($fields as $key => $field) {
            $value = get_post_meta($post->ID, '_' . $key, true);
            ?>
            <p>
                <label for="<?php echo esc_attr($key); ?>">
                    <strong><?php echo esc_html($field['label']); ?>:</strong>
                </label><br>
                <?php
                switch ($field['type']) {
                    case 'textarea':
                        echo '<textarea id="' . esc_attr($key) . '" name="' . esc_attr($key) . 
                             '" rows="4" style="width:100%;">' . esc_textarea($value) . '</textarea>';
                        break;
                    case 'select':
                        echo '<select id="' . esc_attr($key) . '" name="' . esc_attr($key) . '">';
                        foreach ($field['options'] as $option_key => $option_label) {
                            echo '<option value="' . esc_attr($option_key) . '"' . 
                                 selected($value, $option_key, false) . '>' . 
                                 esc_html($option_label) . '</option>';
                        }
                        echo '</select>';
                        break;
                    default:
                        echo '<input type="' . esc_attr($field['type']) . '" id="' . esc_attr($key) . 
                             '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . 
                             '" style="width:100%;" />';
                        break;
                }
                ?>
            </p>
            <?php
        }
    }
    
    /**
     * Render AI settings meta box
     */
    public function render_grant_ai_meta_box($post) {
        $ai_generated = get_post_meta($post->ID, '_ai_generated', true);
        $ai_regenerate = get_post_meta($post->ID, '_ai_regenerate', true);
        ?>
        <p>
            <label>
                <input type="checkbox" name="ai_regenerate" value="1" <?php checked($ai_regenerate, '1'); ?> />
                <?php _e('AIコンテンツを再生成する', 'jgrants-integration'); ?>
            </label>
        </p>
        <?php if ($ai_generated): ?>
        <p>
            <small><?php _e('最終AI生成日時:', 'jgrants-integration'); ?> 
            <?php echo date('Y-m-d H:i', strtotime($ai_generated)); ?></small>
        </p>
        <?php endif; ?>
        <p>
            <button type="button" class="button" id="regenerate-ai-content" data-post-id="<?php echo $post->ID; ?>">
                <?php _e('今すぐAIコンテンツを生成', 'jgrants-integration'); ?>
            </button>
        </p>
        <?php
    }
    
    /**
     * Save grant meta data
     */
    public function save_grant_meta($post_id, $post) {
        // Check nonce
        if (!isset($_POST['grant_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['grant_meta_box_nonce'], 'grant_meta_box')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save meta fields
        $fields = [
            'grant_id', 'organization', 'max_amount', 'min_amount', 
            'subsidy_rate', 'target', 'purpose', 'deadline',
            'application_start', 'official_url', 'grant_status'
        ];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Handle AI regeneration request
        if (isset($_POST['ai_regenerate']) && $_POST['ai_regenerate'] === '1') {
            $this->ai_generator->generate_content_for_post($post_id);
            delete_post_meta($post_id, '_ai_regenerate');
        }
    }
    
    /**
     * Plugin activation
     */
    public static function activate() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'jgrants_sync_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sync_date datetime DEFAULT CURRENT_TIMESTAMP,
            grants_fetched int(11) DEFAULT 0,
            grants_created int(11) DEFAULT 0,
            grants_updated int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            error_message text,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * REST API callback to get grants
     */
    public function rest_get_grants($request) {
        $args = [
            'post_type' => 'grant',
            'posts_per_page' => $request->get_param('per_page') ?: 10,
            'paged' => $request->get_param('page') ?: 1,
            'post_status' => 'publish',
        ];
        
        // Add filters
        if ($category = $request->get_param('category')) {
            $args['tax_query'][] = [
                'taxonomy' => 'grant_category',
                'field' => 'slug',
                'terms' => $category,
            ];
        }
        
        if ($prefecture = $request->get_param('prefecture')) {
            $args['tax_query'][] = [
                'taxonomy' => 'prefecture',
                'field' => 'slug',
                'terms' => $prefecture,
            ];
        }
        
        $query = new \WP_Query($args);
        $grants = [];
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $grants[] = $this->format_grant_for_api(get_post());
            }
        }
        
        wp_reset_postdata();
        
        return new \WP_REST_Response([
            'grants' => $grants,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
        ], 200);
    }
    
    /**
     * Format grant post for API response
     */
    private function format_grant_for_api($post) {
        $categories = get_the_terms($post->ID, 'grant_category');
        $prefectures = get_the_terms($post->ID, 'prefecture');
        
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'excerpt' => $post->post_excerpt,
            'content' => $post->post_content,
            'link' => get_permalink($post->ID),
            'grant_id' => get_post_meta($post->ID, '_grant_id', true),
            'organization' => get_post_meta($post->ID, '_organization', true),
            'max_amount' => get_post_meta($post->ID, '_max_amount', true),
            'deadline' => get_post_meta($post->ID, '_deadline', true),
            'status' => get_post_meta($post->ID, '_grant_status', true),
            'categories' => $categories ? wp_list_pluck($categories, 'name') : [],
            'prefectures' => $prefectures ? wp_list_pluck($prefectures, 'name') : [],
        ];
    }
}