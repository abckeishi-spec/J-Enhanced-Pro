<?php
/**
 * Plugin Name: JGrants Integration Pro
 * Plugin URI: https://example.com/jgrants-integration
 * Description: JグランツAPIから補助金情報を取得し、AIによる自動コンテンツ生成機能を提供する高機能プラグイン
 * Version: 1.0.0
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
define('JGRANTS_VERSION', '1.0.0');
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
    // Create database tables if needed
    JGrants\Core::activate();
    
    // Set default options
    $default_options = [
        'jgrants_api_key' => '',
        'jgrants_api_url' => 'https://api.jgrants.go.jp/v2/',
        'openai_api_key' => '',
        'openai_model' => 'gpt-4-turbo-preview',
        'auto_sync_enabled' => true,
        'sync_interval' => 24,
        'ai_content_generation' => true,
        'ai_title_prompt' => '以下の補助金情報から、SEOに最適化された魅力的なタイトルを生成してください。補助金名: {grant_name}, 実施機関: {organization}, 最大支援額: {max_amount}, 対象: {target}, 締切: {deadline}',
        'ai_excerpt_prompt' => '以下の補助金情報の重要なポイントを150文字以内で簡潔にまとめてください。',
        'ai_content_prompt' => '以下の補助金情報から、事業者に役立つ詳細な解説記事を生成してください。見出しは以下の構成で作成してください: 1.概要, 2.対象者・条件, 3.支援内容, 4.申請のポイント, 5.注意事項, 6.まとめ',
        'ai_categorization' => true,
        'ai_prefecture_extraction' => true,
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
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Uninstall hook
register_uninstall_hook(__FILE__, 'jgrants_integration_uninstall');
function jgrants_integration_uninstall() {
    // Remove options
    $options_to_delete = [
        'jgrants_api_key',
        'jgrants_api_url',
        'openai_api_key',
        'openai_model',
        'auto_sync_enabled',
        'sync_interval',
        'ai_content_generation',
        'ai_title_prompt',
        'ai_excerpt_prompt',
        'ai_content_prompt',
        'ai_categorization',
        'ai_prefecture_extraction',
    ];
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
    
    // Remove custom tables if exists
    global $wpdb;
    $table_name = $wpdb->prefix . 'jgrants_sync_log';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}