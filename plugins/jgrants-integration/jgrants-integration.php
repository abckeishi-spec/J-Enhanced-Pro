<?php
/**
 * Plugin Name: JGrants Integration Pro
 * Plugin URI: https://example.com/jgrants-integration
 * Description: JグランツPublic APIから補助金情報を取得し、Gemini AIによる自動コンテンツ生成機能を提供する高機能プラグイン
 * Version: 2.0.0
 * Author: Your Company
 * Author URI: https://example.com
 * Text Domain: jgrants-integration
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('JGRANTS_VERSION', '2.0.0');
define('JGRANTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JGRANTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('JGRANTS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'JGrants\\';
    $base_dir = JGRANTS_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load required files
require_once JGRANTS_PLUGIN_DIR . 'includes/class-jgrants-core.php';
require_once JGRANTS_PLUGIN_DIR . 'includes/class-jgrants-api-client.php';
require_once JGRANTS_PLUGIN_DIR . 'includes/class-jgrants-post-type.php';
require_once JGRANTS_PLUGIN_DIR . 'includes/class-jgrants-taxonomies.php';
require_once JGRANTS_PLUGIN_DIR . 'includes/class-jgrants-ai-generator.php';
require_once JGRANTS_PLUGIN_DIR . 'includes/class-jgrants-sync-manager.php';
require_once JGRANTS_PLUGIN_DIR . 'includes/class-jgrants-cron.php';
require_once JGRANTS_PLUGIN_DIR . 'admin/class-jgrants-admin.php';

// Initialize plugin
function jgrants_integration_init() {
    // Load text domain
    load_plugin_textdomain('jgrants-integration', false, dirname(JGRANTS_PLUGIN_BASENAME) . '/languages');
    
    // Initialize core
    $plugin = JGrants\Core::get_instance();
    $plugin->init();
}
add_action('plugins_loaded', 'jgrants_integration_init');

// Activation hook
register_activation_hook(__FILE__, 'jgrants_integration_activate');
function jgrants_integration_activate() {
    // Create database tables
    jgrants_create_database_tables();
    
    // Set default options
    $default_options = [
        // API Settings (No API key needed for public API)
        'sync_default_keyword' => '補助金',
        'sync_sort_field' => 'created_date',
        'sync_sort_order' => 'DESC',
        'sync_acceptance_filter' => '1',
        'sync_max_import' => 50,
        'sync_batch_size' => 10,
        'sync_batch_delay' => 5,
        'sync_generate_ai' => true,
        'sync_update_existing' => true,
        
        // Gemini AI Settings
        'gemini_api_key' => '',
        'gemini_model' => 'gemini-pro',
        'gemini_max_tokens' => 2048,
        
        // AI Generation Settings
        'ai_content_generation' => true,
        'ai_generate_title' => true,
        'ai_generate_excerpt' => true,
        'ai_generate_content' => true,
        'ai_categorization' => true,
        'ai_prefecture_extraction' => true,
        
        // AI Rate Limiting
        'ai_rate_limit_minutes' => 3,
        'ai_rate_limit_requests' => 2,
        'ai_batch_size' => 5,
        'ai_batch_delay' => 10,
        'ai_regenerate_hours' => 24,
        
        // AI Prompts
        'ai_title_prompt' => '以下の補助金情報から、SEOに最適化された魅力的なタイトルを生成してください。タイトルは60文字以内で、キーワードを含め、クリック率が高くなるようにしてください。補助金名: {grant_name}, 実施機関: {organization}, 最大支援額: {max_amount}, 対象: {target}, 締切: {deadline}',
        'ai_excerpt_prompt' => '以下の補助金情報の重要なポイントを150文字以内で簡潔にまとめてください。',
        'ai_content_prompt' => '以下の補助金情報から、事業者に役立つ詳細な解説記事を生成してください。見出しは以下の構成で作成してください: 1.概要, 2.対象者・条件, 3.支援内容, 4.申請のポイント, 5.注意事項, 6.まとめ',
        
        // Auto Sync Settings
        'auto_sync_enabled' => true,
        'sync_interval' => 24,
        'auto_publish_grants' => false,
    ];
    
    foreach ($default_options as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }
    
    // Schedule cron jobs
    if (!wp_next_scheduled('jgrants_sync_grants')) {
        wp_schedule_event(time(), 'daily', 'jgrants_sync_grants');
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'jgrants_integration_deactivate');
function jgrants_integration_deactivate() {
    // Clear scheduled cron jobs
    wp_clear_scheduled_hook('jgrants_sync_grants');
    wp_clear_scheduled_hook('jgrants_cleanup_old_grants');
    wp_clear_scheduled_hook('jgrants_check_deadlines');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Uninstall hook
register_uninstall_hook(__FILE__, 'jgrants_integration_uninstall');
function jgrants_integration_uninstall() {
    // Remove options
    $options_to_delete = [
        'sync_default_keyword',
        'sync_sort_field',
        'sync_sort_order',
        'sync_acceptance_filter',
        'sync_max_import',
        'sync_batch_size',
        'sync_batch_delay',
        'sync_generate_ai',
        'sync_update_existing',
        'gemini_api_key',
        'gemini_model',
        'gemini_max_tokens',
        'ai_content_generation',
        'ai_generate_title',
        'ai_generate_excerpt',
        'ai_generate_content',
        'ai_categorization',
        'ai_prefecture_extraction',
        'ai_rate_limit_minutes',
        'ai_rate_limit_requests',
        'ai_batch_size',
        'ai_batch_delay',
        'ai_regenerate_hours',
        'ai_title_prompt',
        'ai_excerpt_prompt',
        'ai_content_prompt',
        'auto_sync_enabled',
        'sync_interval',
        'auto_publish_grants',
        'ai_generation_count',
    ];
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
    
    // Remove daily generation count options
    global $wpdb;
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'ai_generation_count_%'");
    
    // Remove custom tables
    $table_name = $wpdb->prefix . 'jgrants_sync_log';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

/**
 * Create database tables
 */
function jgrants_create_database_tables() {
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
 * Add custom cron schedules
 */
add_filter('cron_schedules', 'jgrants_add_cron_schedules');
function jgrants_add_cron_schedules($schedules) {
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
 * Plugin update routine
 */
add_action('plugins_loaded', 'jgrants_check_version');
function jgrants_check_version() {
    $current_version = get_option('jgrants_plugin_version', '1.0.0');
    
    if (version_compare($current_version, JGRANTS_VERSION, '<')) {
        // Run update routines
        jgrants_update_plugin($current_version);
        
        // Update version
        update_option('jgrants_plugin_version', JGRANTS_VERSION);
    }
}

/**
 * Plugin update routine
 */
function jgrants_update_plugin($from_version) {
    // Version 2.0.0 updates
    if (version_compare($from_version, '2.0.0', '<')) {
        // Create/update database tables
        jgrants_create_database_tables();
        
        // Migrate from OpenAI to Gemini settings
        $openai_key = get_option('openai_api_key');
        if ($openai_key && !get_option('gemini_api_key')) {
            // Note: User needs to get a new Gemini API key
            add_option('jgrants_show_gemini_migration_notice', true);
        }
        
        // Remove old OpenAI settings
        delete_option('openai_api_key');
        delete_option('openai_model');
        
        // Remove old JGrants API key (no longer needed)
        delete_option('jgrants_api_key');
        delete_option('jgrants_api_url');
    }
}

/**
 * Show migration notice
 */
add_action('admin_notices', 'jgrants_show_migration_notice');
function jgrants_show_migration_notice() {
    if (get_option('jgrants_show_gemini_migration_notice')) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php _e('JGrants Integration更新通知', 'jgrants-integration'); ?></strong><br>
                <?php _e('バージョン2.0.0からGemini AIを使用するように変更されました。', 'jgrants-integration'); ?>
                <?php 
                printf(
                    __('<a href="%s">AI設定ページ</a>でGemini APIキーを設定してください。', 'jgrants-integration'),
                    admin_url('admin.php?page=jgrants-ai')
                );
                ?>
            </p>
        </div>
        <?php
        
        // Remove notice after displaying
        if (isset($_GET['jgrants_dismiss_migration'])) {
            delete_option('jgrants_show_gemini_migration_notice');
        }
    }
}