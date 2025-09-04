<?php
namespace JGrants\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Interface Class
 */
class Admin {
    
    private $sync_manager;
    private $api_client;
    private $ai_generator;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->sync_manager = new \JGrants\Sync_Manager();
        $this->api_client = new \JGrants\API_Client();
        $this->ai_generator = new \JGrants\AI_Generator();
    }
    
    /**
     * Initialize admin interface
     */
    public function init() {
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Register settings
        add_action('admin_init', [$this, 'register_settings']);
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Add dashboard widget
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
        
        // Add admin notices
        add_action('admin_notices', [$this, 'display_admin_notices']);
        
        // Register AJAX handlers for new features
        add_action('wp_ajax_jgrants_import_single', [$this->sync_manager, 'ajax_import_single']);
        add_action('wp_ajax_jgrants_batch_ai_generate', [$this, 'ajax_batch_ai_generate']);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('JGrants設定', 'jgrants-integration'),
            __('JGrants連携', 'jgrants-integration'),
            'manage_options',
            'jgrants-settings',
            [$this, 'render_settings_page'],
            'dashicons-money-alt',
            30
        );
        
        // Settings submenu
        add_submenu_page(
            'jgrants-settings',
            __('基本設定', 'jgrants-integration'),
            __('基本設定', 'jgrants-integration'),
            'manage_options',
            'jgrants-settings',
            [$this, 'render_settings_page']
        );
        
        // Manual import submenu
        add_submenu_page(
            'jgrants-settings',
            __('手動インポート', 'jgrants-integration'),
            __('手動インポート', 'jgrants-integration'),
            'manage_options',
            'jgrants-manual-import',
            [$this, 'render_manual_import_page']
        );
        
        // Sync management submenu
        add_submenu_page(
            'jgrants-settings',
            __('同期管理', 'jgrants-integration'),
            __('同期管理', 'jgrants-integration'),
            'manage_options',
            'jgrants-sync',
            [$this, 'render_sync_page']
        );
        
        // AI settings submenu
        add_submenu_page(
            'jgrants-settings',
            __('AI設定', 'jgrants-integration'),
            __('AI設定', 'jgrants-integration'),
            'manage_options',
            'jgrants-ai',
            [$this, 'render_ai_settings_page']
        );
        
        // Statistics submenu
        add_submenu_page(
            'jgrants-settings',
            __('統計・ログ', 'jgrants-integration'),
            __('統計・ログ', 'jgrants-integration'),
            'manage_options',
            'jgrants-stats',
            [$this, 'render_stats_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Sync Settings
        register_setting('jgrants_sync_settings', 'sync_default_keyword');
        register_setting('jgrants_sync_settings', 'sync_sort_field');
        register_setting('jgrants_sync_settings', 'sync_sort_order');
        register_setting('jgrants_sync_settings', 'sync_acceptance_filter');
        register_setting('jgrants_sync_settings', 'sync_max_import');
        register_setting('jgrants_sync_settings', 'sync_batch_size');
        register_setting('jgrants_sync_settings', 'sync_batch_delay');
        register_setting('jgrants_sync_settings', 'sync_generate_ai');
        register_setting('jgrants_sync_settings', 'sync_update_existing');
        register_setting('jgrants_sync_settings', 'auto_sync_enabled');
        register_setting('jgrants_sync_settings', 'sync_interval');
        register_setting('jgrants_sync_settings', 'auto_publish_grants');
        
        // Gemini AI Settings
        register_setting('jgrants_ai_settings', 'gemini_api_key');
        register_setting('jgrants_ai_settings', 'gemini_model');
        register_setting('jgrants_ai_settings', 'gemini_max_tokens');
        register_setting('jgrants_ai_settings', 'ai_content_generation');
        register_setting('jgrants_ai_settings', 'ai_generate_title');
        register_setting('jgrants_ai_settings', 'ai_generate_excerpt');
        register_setting('jgrants_ai_settings', 'ai_generate_content');
        register_setting('jgrants_ai_settings', 'ai_categorization');
        register_setting('jgrants_ai_settings', 'ai_prefecture_extraction');
        register_setting('jgrants_ai_settings', 'ai_title_prompt');
        register_setting('jgrants_ai_settings', 'ai_excerpt_prompt');
        register_setting('jgrants_ai_settings', 'ai_content_prompt');
        
        // AI Rate Limiting
        register_setting('jgrants_ai_settings', 'ai_rate_limit_minutes');
        register_setting('jgrants_ai_settings', 'ai_rate_limit_requests');
        register_setting('jgrants_ai_settings', 'ai_batch_size');
        register_setting('jgrants_ai_settings', 'ai_batch_delay');
        register_setting('jgrants_ai_settings', 'ai_regenerate_hours');
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (!str_contains($hook, 'jgrants')) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'jgrants-admin',
            JGRANTS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            JGRANTS_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'jgrants-admin',
            JGRANTS_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            JGRANTS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('jgrants-admin', 'jgrants_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('jgrants_ajax_nonce'),
            'strings' => [
                'confirm_sync' => __('同期を開始しますか？', 'jgrants-integration'),
                'confirm_import' => __('インポートを開始しますか？', 'jgrants-integration'),
                'syncing' => __('同期中...', 'jgrants-integration'),
                'importing' => __('インポート中...', 'jgrants-integration'),
                'sync_complete' => __('同期が完了しました', 'jgrants-integration'),
                'import_complete' => __('インポートが完了しました', 'jgrants-integration'),
                'sync_error' => __('同期エラー', 'jgrants-integration'),
                'import_error' => __('インポートエラー', 'jgrants-integration'),
            ]
        ]);
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="notice notice-info">
                <p><?php _e('JGrants公開APIを使用しています。APIキーは不要です。', 'jgrants-integration'); ?></p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('jgrants_sync_settings'); ?>
                
                <h2><?php _e('同期設定', 'jgrants-integration'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sync_default_keyword"><?php _e('デフォルト検索キーワード', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="sync_default_keyword" name="sync_default_keyword" 
                                   value="<?php echo esc_attr(get_option('sync_default_keyword', '補助金')); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('同期時のデフォルト検索キーワード（最小2文字）', 'jgrants-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sync_max_import"><?php _e('最大インポート件数', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="sync_max_import" name="sync_max_import" 
                                   value="<?php echo esc_attr(get_option('sync_max_import', 50)); ?>" 
                                   min="1" max="500" />
                            <p class="description">
                                <?php _e('1回の同期でインポートする最大件数', 'jgrants-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sync_batch_size"><?php _e('バッチサイズ', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="sync_batch_size" name="sync_batch_size" 
                                   value="<?php echo esc_attr(get_option('sync_batch_size', 10)); ?>" 
                                   min="1" max="50" />
                            <p class="description">
                                <?php _e('一度に処理する件数', 'jgrants-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sync_batch_delay"><?php _e('バッチ間遅延', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="sync_batch_delay" name="sync_batch_delay" 
                                   value="<?php echo esc_attr(get_option('sync_batch_delay', 5)); ?>" 
                                   min="0" max="60" />
                            <span><?php _e('秒', 'jgrants-integration'); ?></span>
                            <p class="description">
                                <?php _e('バッチ処理間の待機時間', 'jgrants-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('AI生成', 'jgrants-integration'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="sync_generate_ai" value="1" 
                                       <?php checked(get_option('sync_generate_ai', true)); ?> />
                                <?php _e('同期時にAIコンテンツを生成する', 'jgrants-integration'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('既存記事の更新', 'jgrants-integration'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="sync_update_existing" value="1" 
                                       <?php checked(get_option('sync_update_existing', true)); ?> />
                                <?php _e('既存の補助金情報を更新する', 'jgrants-integration'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('自動同期設定', 'jgrants-integration'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php _e('自動同期', 'jgrants-integration'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_sync_enabled" value="1" 
                                       <?php checked(get_option('auto_sync_enabled', true)); ?> />
                                <?php _e('自動同期を有効にする', 'jgrants-integration'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sync_interval"><?php _e('同期間隔', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <select id="sync_interval" name="sync_interval">
                                <?php
                                $current = get_option('sync_interval', 24);
                                $intervals = [6 => '6時間', 12 => '12時間', 24 => '24時間', 48 => '48時間'];
                                foreach ($intervals as $value => $label) {
                                    echo '<option value="' . $value . '"' . selected($current, $value, false) . '>' . $label . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <button type="button" id="test-api-connection" class="button">
                        <?php _e('API接続テスト', 'jgrants-integration'); ?>
                    </button>
                    <span id="test-result"></span>
                </p>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render manual import page
     */
    public function render_manual_import_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('手動インポート', 'jgrants-integration'); ?></h1>
            
            <div class="jgrants-import-section">
                <h2><?php _e('検索条件を指定してインポート', 'jgrants-integration'); ?></h2>
                
                <div class="import-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="import_keyword"><?php _e('検索キーワード', 'jgrants-integration'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="import_keyword" value="補助金" class="regular-text" />
                                <p class="description"><?php _e('最小2文字以上', 'jgrants-integration'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="import_count"><?php _e('インポート件数', 'jgrants-integration'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="import_count" value="10" min="1" max="100" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="import_use_purpose"><?php _e('利用目的', 'jgrants-integration'); ?></label>
                            </th>
                            <td>
                                <select id="import_use_purpose">
                                    <option value=""><?php _e('すべて', 'jgrants-integration'); ?></option>
                                    <?php
                                    foreach ($this->api_client->get_use_purpose_options() as $option) {
                                        echo '<option value="' . esc_attr($option) . '">' . esc_html($option) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="import_industry"><?php _e('業種', 'jgrants-integration'); ?></label>
                            </th>
                            <td>
                                <select id="import_industry">
                                    <option value=""><?php _e('すべて', 'jgrants-integration'); ?></option>
                                    <?php
                                    foreach ($this->api_client->get_industry_options() as $option) {
                                        echo '<option value="' . esc_attr($option) . '">' . esc_html($option) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="import_target_area"><?php _e('対象地域', 'jgrants-integration'); ?></label>
                            </th>
                            <td>
                                <select id="import_target_area">
                                    <option value=""><?php _e('すべて', 'jgrants-integration'); ?></option>
                                    <?php
                                    foreach ($this->api_client->get_target_area_options() as $option) {
                                        echo '<option value="' . esc_attr($option) . '">' . esc_html($option) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php _e('オプション', 'jgrants-integration'); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="import_generate_ai" checked />
                                    <?php _e('AIコンテンツを生成', 'jgrants-integration'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" id="import_auto_publish" />
                                    <?php _e('自動公開', 'jgrants-integration'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <p>
                        <button id="start-import" class="button button-primary">
                            <?php _e('インポート開始', 'jgrants-integration'); ?>
                        </button>
                        <span id="import-status"></span>
                    </p>
                </div>
                
                <hr>
                
                <h2><?php _e('補助金IDを指定してインポート', 'jgrants-integration'); ?></h2>
                
                <div class="single-import-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="single_subsidy_id"><?php _e('補助金ID', 'jgrants-integration'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="single_subsidy_id" class="regular-text" placeholder="例: 12345" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php _e('オプション', 'jgrants-integration'); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="single_generate_ai" checked />
                                    <?php _e('AIコンテンツを生成', 'jgrants-integration'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" id="single_auto_publish" />
                                    <?php _e('自動公開', 'jgrants-integration'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <p>
                        <button id="import-single" class="button">
                            <?php _e('個別インポート', 'jgrants-integration'); ?>
                        </button>
                        <span id="single-import-status"></span>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render sync management page
     */
    public function render_sync_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('同期管理', 'jgrants-integration'); ?></h1>
            
            <div class="jgrants-sync-controls">
                <button id="sync-now" class="button button-primary">
                    <?php _e('今すぐ同期', 'jgrants-integration'); ?>
                </button>
                <span id="sync-status"></span>
            </div>
            
            <h2><?php _e('同期履歴', 'jgrants-integration'); ?></h2>
            <?php $this->render_sync_history(20); ?>
        </div>
        <?php
    }
    
    /**
     * Render AI settings page
     */
    public function render_ai_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('AI設定', 'jgrants-integration'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('jgrants_ai_settings'); ?>
                
                <h2><?php _e('Gemini AI設定', 'jgrants-integration'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="gemini_api_key"><?php _e('Gemini APIキー', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="gemini_api_key" name="gemini_api_key" 
                                   value="<?php echo esc_attr(get_option('gemini_api_key')); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Google AI StudioからAPIキーを取得してください。', 'jgrants-integration'); ?>
                                <a href="https://makersuite.google.com/app/apikey" target="_blank">APIキーを取得</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="gemini_model"><?php _e('使用モデル', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <select id="gemini_model" name="gemini_model">
                                <?php
                                $current = get_option('gemini_model', 'gemini-pro');
                                $models = [
                                    'gemini-pro' => 'Gemini Pro',
                                    'gemini-1.5-pro' => 'Gemini 1.5 Pro',
                                    'gemini-1.5-flash' => 'Gemini 1.5 Flash'
                                ];
                                foreach ($models as $value => $label) {
                                    echo '<option value="' . $value . '"' . selected($current, $value, false) . '>' . $label . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="gemini_max_tokens"><?php _e('最大トークン数', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="gemini_max_tokens" name="gemini_max_tokens" 
                                   value="<?php echo esc_attr(get_option('gemini_max_tokens', 2048)); ?>" 
                                   min="256" max="8192" />
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('AI生成設定', 'jgrants-integration'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('生成対象', 'jgrants-integration'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ai_generate_title" value="1" 
                                       <?php checked(get_option('ai_generate_title', true)); ?> />
                                <?php _e('タイトル生成', 'jgrants-integration'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="ai_generate_excerpt" value="1" 
                                       <?php checked(get_option('ai_generate_excerpt', true)); ?> />
                                <?php _e('抜粋生成', 'jgrants-integration'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="ai_generate_content" value="1" 
                                       <?php checked(get_option('ai_generate_content', true)); ?> />
                                <?php _e('本文生成', 'jgrants-integration'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="ai_categorization" value="1" 
                                       <?php checked(get_option('ai_categorization', true)); ?> />
                                <?php _e('自動カテゴリ分類（新規カテゴリも自動作成）', 'jgrants-integration'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="ai_prefecture_extraction" value="1" 
                                       <?php checked(get_option('ai_prefecture_extraction', true)); ?> />
                                <?php _e('都道府県自動抽出', 'jgrants-integration'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('レート制限設定', 'jgrants-integration'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php _e('API制限', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="ai_rate_limit_minutes" 
                                   value="<?php echo esc_attr(get_option('ai_rate_limit_minutes', 3)); ?>" 
                                   min="1" max="60" style="width: 60px;" />
                            <span><?php _e('分間に', 'jgrants-integration'); ?></span>
                            <input type="number" name="ai_rate_limit_requests" 
                                   value="<?php echo esc_attr(get_option('ai_rate_limit_requests', 2)); ?>" 
                                   min="1" max="100" style="width: 60px;" />
                            <span><?php _e('回まで', 'jgrants-integration'); ?></span>
                            <p class="description">
                                <?php _e('Gemini APIのレート制限を防ぐための設定です。', 'jgrants-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php _e('バッチ処理', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <?php _e('一度に', 'jgrants-integration'); ?>
                            <input type="number" name="ai_batch_size" 
                                   value="<?php echo esc_attr(get_option('ai_batch_size', 5)); ?>" 
                                   min="1" max="20" style="width: 60px;" />
                            <span><?php _e('件処理、', 'jgrants-integration'); ?></span>
                            <input type="number" name="ai_batch_delay" 
                                   value="<?php echo esc_attr(get_option('ai_batch_delay', 10)); ?>" 
                                   min="0" max="60" style="width: 60px;" />
                            <span><?php _e('秒間隔', 'jgrants-integration'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ai_regenerate_hours"><?php _e('再生成防止期間', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="ai_regenerate_hours" name="ai_regenerate_hours" 
                                   value="<?php echo esc_attr(get_option('ai_regenerate_hours', 24)); ?>" 
                                   min="1" max="168" style="width: 80px;" />
                            <span><?php _e('時間以内は再生成しない', 'jgrants-integration'); ?></span>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('プロンプト設定', 'jgrants-integration'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ai_title_prompt"><?php _e('タイトル生成プロンプト', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <textarea id="ai_title_prompt" name="ai_title_prompt" rows="4" class="large-text">
<?php echo esc_textarea(get_option('ai_title_prompt', 
'以下の補助金情報から、SEOに最適化された魅力的なタイトルを生成してください。タイトルは60文字以内で、キーワードを含め、クリック率が高くなるようにしてください。補助金名: {grant_name}, 実施機関: {organization}, 最大支援額: {max_amount}, 対象: {target}, 締切: {deadline}')); ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr>
            
            <h2><?php _e('AI生成テスト', 'jgrants-integration'); ?></h2>
            <div class="ai-test-section">
                <p>
                    <button id="test-ai-generation" class="button">
                        <?php _e('AI生成テスト実行', 'jgrants-integration'); ?>
                    </button>
                    <span id="ai-test-result"></span>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render statistics page
     */
    public function render_stats_page() {
        $sync_stats = $this->sync_manager->get_statistics();
        $ai_stats = $this->ai_generator->get_statistics();
        ?>
        <div class="wrap">
            <h1><?php _e('統計・ログ', 'jgrants-integration'); ?></h1>
            
            <div class="stats-cards">
                <div class="stat-card">
                    <h3><?php _e('補助金統計', 'jgrants-integration'); ?></h3>
                    <p><strong><?php _e('総補助金数:', 'jgrants-integration'); ?></strong> <?php echo $sync_stats['total_grants']; ?></p>
                    <p><strong><?php _e('募集中:', 'jgrants-integration'); ?></strong> <?php echo $sync_stats['active_grants']; ?></p>
                    <p><strong><?php _e('本日の同期回数:', 'jgrants-integration'); ?></strong> <?php echo $sync_stats['today_syncs']; ?></p>
                </div>
                
                <div class="stat-card">
                    <h3><?php _e('AI生成統計', 'jgrants-integration'); ?></h3>
                    <p><strong><?php _e('総生成回数:', 'jgrants-integration'); ?></strong> <?php echo $ai_stats['total_generations']; ?></p>
                    <p><strong><?php _e('本日の生成回数:', 'jgrants-integration'); ?></strong> <?php echo $ai_stats['today_generations']; ?></p>
                    <p><strong><?php _e('使用モデル:', 'jgrants-integration'); ?></strong> <?php echo $ai_stats['model']; ?></p>
                </div>
                
                <div class="stat-card">
                    <h3><?php _e('設定状況', 'jgrants-integration'); ?></h3>
                    <p><strong><?php _e('レート制限:', 'jgrants-integration'); ?></strong> 
                        <?php echo sprintf('%d分間に%d回', $ai_stats['rate_limit']['minutes'], $ai_stats['rate_limit']['requests']); ?>
                    </p>
                    <p><strong><?php _e('バッチサイズ:', 'jgrants-integration'); ?></strong> 
                        <?php echo sprintf('%d件/%d秒', $ai_stats['batch_settings']['size'], $ai_stats['batch_settings']['delay']); ?>
                    </p>
                </div>
            </div>
            
            <hr>
            
            <h2><?php _e('同期ログ', 'jgrants-integration'); ?></h2>
            <?php $this->render_sync_history(50); ?>
        </div>
        
        <style>
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            margin-top: 0;
            color: #333;
        }
        </style>
        <?php
    }
    
    /**
     * Render sync history table
     */
    private function render_sync_history($limit = 10) {
        $logs = $this->sync_manager->get_sync_history($limit);
        
        if (empty($logs)) {
            echo '<p>' . __('同期履歴がありません。', 'jgrants-integration') . '</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('日時', 'jgrants-integration'); ?></th>
                    <th><?php _e('ステータス', 'jgrants-integration'); ?></th>
                    <th><?php _e('取得件数', 'jgrants-integration'); ?></th>
                    <th><?php _e('作成件数', 'jgrants-integration'); ?></th>
                    <th><?php _e('更新件数', 'jgrants-integration'); ?></th>
                    <th><?php _e('AI生成', 'jgrants-integration'); ?></th>
                    <th><?php _e('エラー', 'jgrants-integration'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo esc_html($log->sync_date); ?></td>
                    <td>
                        <?php 
                        $status_labels = [
                            'success' => '<span class="dashicons dashicons-yes" style="color: green;"></span> ' . __('成功', 'jgrants-integration'),
                            'error' => '<span class="dashicons dashicons-no" style="color: red;"></span> ' . __('エラー', 'jgrants-integration'),
                            'in_progress' => '<span class="dashicons dashicons-update" style="color: orange;"></span> ' . __('実行中', 'jgrants-integration'),
                        ];
                        echo $status_labels[$log->status] ?? $log->status;
                        ?>
                    </td>
                    <td><?php echo esc_html($log->grants_fetched); ?></td>
                    <td><?php echo esc_html($log->grants_created); ?></td>
                    <td><?php echo esc_html($log->grants_updated); ?></td>
                    <td><?php echo esc_html($log->ai_generated ?? 0); ?></td>
                    <td><?php echo esc_html($log->error_message ?: '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'jgrants_dashboard_widget',
            __('JGrants補助金情報', 'jgrants-integration'),
            [$this, 'render_dashboard_widget']
        );
    }
    
    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        $stats = $this->sync_manager->get_statistics();
        ?>
        <div class="jgrants-dashboard-widget">
            <p>
                <strong><?php _e('総補助金数:', 'jgrants-integration'); ?></strong> 
                <?php echo $stats['total_grants']; ?>
            </p>
            <p>
                <strong><?php _e('募集中:', 'jgrants-integration'); ?></strong> 
                <?php echo $stats['active_grants']; ?>
            </p>
            <?php if (!empty($stats['last_sync'])): ?>
            <p>
                <strong><?php _e('最終同期:', 'jgrants-integration'); ?></strong> 
                <?php echo esc_html($stats['last_sync'][0]->sync_date); ?>
            </p>
            <?php endif; ?>
            <p style="margin-top: 15px;">
                <a href="<?php echo admin_url('admin.php?page=jgrants-manual-import'); ?>" class="button">
                    <?php _e('手動インポート', 'jgrants-integration'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=jgrants-stats'); ?>" class="button">
                    <?php _e('統計を見る', 'jgrants-integration'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        // Check if Gemini API key is set
        if (get_option('ai_content_generation', true) && empty(get_option('gemini_api_key'))) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php 
                    printf(
                        __('JGrants Integration: AI機能を使用するにはGemini APIキーが必要です。<a href="%s">AI設定ページ</a>で設定してください。', 'jgrants-integration'),
                        admin_url('admin.php?page=jgrants-ai')
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * AJAX handler for batch AI generation
     */
    public function ajax_batch_ai_generate() {
        check_ajax_referer('jgrants_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $post_ids = array_map('intval', $_POST['post_ids'] ?? []);
        $options = [
            'batch_size' => intval($_POST['batch_size'] ?? 5),
            'delay' => intval($_POST['delay'] ?? 10),
        ];
        
        if (empty($post_ids)) {
            wp_send_json_error(['message' => '投稿が選択されていません']);
        }
        
        $results = $this->ai_generator->batch_generate($post_ids, $options);
        
        wp_send_json_success([
            'message' => sprintf(
                'AI生成完了: %d件成功, %d件失敗, %d件スキップ',
                $results['success'],
                $results['failed'],
                $results['skipped']
            ),
            'results' => $results
        ]);
    }
}