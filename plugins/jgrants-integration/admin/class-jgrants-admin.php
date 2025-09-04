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
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->sync_manager = new \JGrants\Sync_Manager();
        $this->api_client = new \JGrants\API_Client();
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
            __('設定', 'jgrants-integration'),
            __('設定', 'jgrants-integration'),
            'manage_options',
            'jgrants-settings',
            [$this, 'render_settings_page']
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
        
        // Logs submenu
        add_submenu_page(
            'jgrants-settings',
            __('ログ', 'jgrants-integration'),
            __('ログ', 'jgrants-integration'),
            'manage_options',
            'jgrants-logs',
            [$this, 'render_logs_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // API Settings
        register_setting('jgrants_api_settings', 'jgrants_api_key', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('jgrants_api_settings', 'jgrants_api_url', [
            'sanitize_callback' => 'esc_url_raw'
        ]);
        
        // OpenAI Settings
        register_setting('jgrants_ai_settings', 'openai_api_key', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('jgrants_ai_settings', 'openai_model', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        
        // Sync Settings
        register_setting('jgrants_sync_settings', 'auto_sync_enabled', [
            'sanitize_callback' => 'absint'
        ]);
        register_setting('jgrants_sync_settings', 'sync_interval', [
            'sanitize_callback' => 'absint'
        ]);
        register_setting('jgrants_sync_settings', 'auto_publish_grants', [
            'sanitize_callback' => 'absint'
        ]);
        
        // AI Content Settings
        register_setting('jgrants_ai_settings', 'ai_content_generation', [
            'sanitize_callback' => 'absint'
        ]);
        register_setting('jgrants_ai_settings', 'ai_categorization', [
            'sanitize_callback' => 'absint'
        ]);
        register_setting('jgrants_ai_settings', 'ai_prefecture_extraction', [
            'sanitize_callback' => 'absint'
        ]);
        register_setting('jgrants_ai_settings', 'ai_title_prompt', [
            'sanitize_callback' => 'sanitize_textarea_field'
        ]);
        register_setting('jgrants_ai_settings', 'ai_excerpt_prompt', [
            'sanitize_callback' => 'sanitize_textarea_field'
        ]);
        register_setting('jgrants_ai_settings', 'ai_content_prompt', [
            'sanitize_callback' => 'sanitize_textarea_field'
        ]);
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
                'syncing' => __('同期中...', 'jgrants-integration'),
                'sync_complete' => __('同期が完了しました', 'jgrants-integration'),
                'sync_error' => __('同期エラー', 'jgrants-integration'),
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
            
            <form method="post" action="options.php">
                <?php settings_fields('jgrants_api_settings'); ?>
                
                <h2><?php _e('API設定', 'jgrants-integration'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="jgrants_api_key"><?php _e('JGrants APIキー', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="jgrants_api_key" name="jgrants_api_key" 
                                   value="<?php echo esc_attr(get_option('jgrants_api_key')); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('JグランツAPIのアクセスキーを入力してください。', 'jgrants-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="jgrants_api_url"><?php _e('API URL', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="jgrants_api_url" name="jgrants_api_url" 
                                   value="<?php echo esc_attr(get_option('jgrants_api_url', 'https://api.jgrants.go.jp/v2/')); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('JグランツAPIのエンドポイントURL', 'jgrants-integration'); ?>
                            </p>
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
            
            <form method="post" action="options.php">
                <?php settings_fields('jgrants_sync_settings'); ?>
                
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
                                $intervals = [6 => '6時間', 12 => '12時間', 24 => '24時間'];
                                foreach ($intervals as $value => $label) {
                                    echo '<option value="' . $value . '"' . selected($current, $value, false) . '>' . $label . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('自動公開', 'jgrants-integration'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_publish_grants" value="1" 
                                       <?php checked(get_option('auto_publish_grants', false)); ?> />
                                <?php _e('新規補助金を自動的に公開する', 'jgrants-integration'); ?>
                            </label>
                            <p class="description">
                                <?php _e('チェックを外すと、新規補助金は下書きとして保存されます。', 'jgrants-integration'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <h2><?php _e('同期履歴', 'jgrants-integration'); ?></h2>
            <?php $this->render_sync_history(); ?>
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
                
                <h2><?php _e('OpenAI設定', 'jgrants-integration'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="openai_api_key"><?php _e('OpenAI APIキー', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="openai_api_key" name="openai_api_key" 
                                   value="<?php echo esc_attr(get_option('openai_api_key')); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('OpenAI APIのアクセスキーを入力してください。', 'jgrants-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="openai_model"><?php _e('使用モデル', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <select id="openai_model" name="openai_model">
                                <?php
                                $current = get_option('openai_model', 'gpt-4-turbo-preview');
                                $models = [
                                    'gpt-4-turbo-preview' => 'GPT-4 Turbo',
                                    'gpt-4' => 'GPT-4',
                                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo'
                                ];
                                foreach ($models as $value => $label) {
                                    echo '<option value="' . $value . '"' . selected($current, $value, false) . '>' . $label . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('AI機能設定', 'jgrants-integration'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('コンテンツ生成', 'jgrants-integration'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ai_content_generation" value="1" 
                                       <?php checked(get_option('ai_content_generation', true)); ?> />
                                <?php _e('AIによる自動コンテンツ生成を有効にする', 'jgrants-integration'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('自動カテゴリ分類', 'jgrants-integration'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ai_categorization" value="1" 
                                       <?php checked(get_option('ai_categorization', true)); ?> />
                                <?php _e('AIによる自動カテゴリ分類を有効にする', 'jgrants-integration'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('都道府県抽出', 'jgrants-integration'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ai_prefecture_extraction" value="1" 
                                       <?php checked(get_option('ai_prefecture_extraction', true)); ?> />
                                <?php _e('AIによる都道府県自動抽出を有効にする', 'jgrants-integration'); ?>
                            </label>
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
'以下の補助金情報から、SEOに最適化された魅力的なタイトルを生成してください。補助金名: {grant_name}, 実施機関: {organization}, 最大支援額: {max_amount}, 対象: {target}, 締切: {deadline}')); ?></textarea>
                            <p class="description">
                                <?php _e('利用可能な変数: {grant_name}, {organization}, {max_amount}, {target}, {deadline}', 'jgrants-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ai_excerpt_prompt"><?php _e('抜粋生成プロンプト', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <textarea id="ai_excerpt_prompt" name="ai_excerpt_prompt" rows="4" class="large-text">
<?php echo esc_textarea(get_option('ai_excerpt_prompt', 
'以下の補助金情報の重要なポイントを150文字以内で簡潔にまとめてください。')); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ai_content_prompt"><?php _e('コンテンツ生成プロンプト', 'jgrants-integration'); ?></label>
                        </th>
                        <td>
                            <textarea id="ai_content_prompt" name="ai_content_prompt" rows="6" class="large-text">
<?php echo esc_textarea(get_option('ai_content_prompt', 
'以下の補助金情報から、事業者に役立つ詳細な解説記事を生成してください。見出しは以下の構成で作成してください: 1.概要, 2.対象者・条件, 3.支援内容, 4.申請のポイント, 5.注意事項, 6.まとめ')); ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render logs page
     */
    public function render_logs_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('同期ログ', 'jgrants-integration'); ?></h1>
            <?php $this->render_sync_history(50); ?>
        </div>
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
        // Get statistics
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
        
        $last_sync = $this->sync_manager->get_sync_history(1);
        ?>
        <div class="jgrants-dashboard-widget">
            <p>
                <strong><?php _e('総補助金数:', 'jgrants-integration'); ?></strong> 
                <?php echo intval($total_grants->publish + $total_grants->draft); ?>
            </p>
            <p>
                <strong><?php _e('募集中:', 'jgrants-integration'); ?></strong> 
                <?php echo count($active_grants); ?>
            </p>
            <?php if (!empty($last_sync)): ?>
            <p>
                <strong><?php _e('最終同期:', 'jgrants-integration'); ?></strong> 
                <?php echo esc_html($last_sync[0]->sync_date); ?>
            </p>
            <?php endif; ?>
            <p>
                <a href="<?php echo admin_url('admin.php?page=jgrants-sync'); ?>" class="button">
                    <?php _e('同期管理へ', 'jgrants-integration'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        // Check if API key is set
        if (empty(get_option('jgrants_api_key'))) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php 
                    printf(
                        __('JGrants Integration: APIキーが設定されていません。<a href="%s">設定ページ</a>で設定してください。', 'jgrants-integration'),
                        admin_url('admin.php?page=jgrants-settings')
                    );
                    ?>
                </p>
            </div>
            <?php
        }
        
        // Check if OpenAI key is set
        if (get_option('ai_content_generation', true) && empty(get_option('openai_api_key'))) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <?php 
                    printf(
                        __('JGrants Integration: AI機能を使用するにはOpenAI APIキーが必要です。<a href="%s">AI設定ページ</a>で設定してください。', 'jgrants-integration'),
                        admin_url('admin.php?page=jgrants-ai')
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }
}