<?php
namespace JGrants;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Taxonomies Registration Class
 */
class Taxonomies {
    
    /**
     * Register custom taxonomies
     */
    public function register() {
        $this->register_category_taxonomy();
        $this->register_prefecture_taxonomy();
        $this->register_target_taxonomy();
        $this->register_amount_range_taxonomy();
    }
    
    /**
     * Register grant category taxonomy
     */
    private function register_category_taxonomy() {
        $labels = [
            'name'              => _x('補助金カテゴリー', 'taxonomy general name', 'jgrants-integration'),
            'singular_name'     => _x('補助金カテゴリー', 'taxonomy singular name', 'jgrants-integration'),
            'search_items'      => __('カテゴリーを検索', 'jgrants-integration'),
            'all_items'         => __('すべてのカテゴリー', 'jgrants-integration'),
            'parent_item'       => __('親カテゴリー', 'jgrants-integration'),
            'parent_item_colon' => __('親カテゴリー:', 'jgrants-integration'),
            'edit_item'         => __('カテゴリーを編集', 'jgrants-integration'),
            'update_item'       => __('カテゴリーを更新', 'jgrants-integration'),
            'add_new_item'      => __('新規カテゴリーを追加', 'jgrants-integration'),
            'new_item_name'     => __('新規カテゴリー名', 'jgrants-integration'),
            'menu_name'         => __('カテゴリー', 'jgrants-integration'),
        ];
        
        $args = [
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'grant-category', 'with_front' => false],
            'show_in_rest'      => true,
            'rest_base'         => 'grant-categories',
        ];
        
        register_taxonomy('grant_category', ['grant'], $args);
        
        // Insert default categories
        $this->insert_default_categories();
    }
    
    /**
     * Register prefecture taxonomy
     */
    private function register_prefecture_taxonomy() {
        $labels = [
            'name'              => _x('対象地域', 'taxonomy general name', 'jgrants-integration'),
            'singular_name'     => _x('対象地域', 'taxonomy singular name', 'jgrants-integration'),
            'search_items'      => __('地域を検索', 'jgrants-integration'),
            'all_items'         => __('すべての地域', 'jgrants-integration'),
            'parent_item'       => __('親地域', 'jgrants-integration'),
            'parent_item_colon' => __('親地域:', 'jgrants-integration'),
            'edit_item'         => __('地域を編集', 'jgrants-integration'),
            'update_item'       => __('地域を更新', 'jgrants-integration'),
            'add_new_item'      => __('新規地域を追加', 'jgrants-integration'),
            'new_item_name'     => __('新規地域名', 'jgrants-integration'),
            'menu_name'         => __('対象地域', 'jgrants-integration'),
        ];
        
        $args = [
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'prefecture', 'with_front' => false],
            'show_in_rest'      => true,
            'rest_base'         => 'prefectures',
        ];
        
        register_taxonomy('prefecture', ['grant'], $args);
        
        // Insert default prefectures
        $this->insert_default_prefectures();
    }
    
    /**
     * Register target taxonomy (対象事業者)
     */
    private function register_target_taxonomy() {
        $labels = [
            'name'              => _x('対象事業者', 'taxonomy general name', 'jgrants-integration'),
            'singular_name'     => _x('対象事業者', 'taxonomy singular name', 'jgrants-integration'),
            'search_items'      => __('対象事業者を検索', 'jgrants-integration'),
            'all_items'         => __('すべての対象事業者', 'jgrants-integration'),
            'edit_item'         => __('対象事業者を編集', 'jgrants-integration'),
            'update_item'       => __('対象事業者を更新', 'jgrants-integration'),
            'add_new_item'      => __('新規対象事業者を追加', 'jgrants-integration'),
            'new_item_name'     => __('新規対象事業者名', 'jgrants-integration'),
            'menu_name'         => __('対象事業者', 'jgrants-integration'),
        ];
        
        $args = [
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'target', 'with_front' => false],
            'show_in_rest'      => true,
            'rest_base'         => 'targets',
        ];
        
        register_taxonomy('grant_target', ['grant'], $args);
        
        // Insert default targets
        $this->insert_default_targets();
    }
    
    /**
     * Register amount range taxonomy
     */
    private function register_amount_range_taxonomy() {
        $labels = [
            'name'              => _x('支援額範囲', 'taxonomy general name', 'jgrants-integration'),
            'singular_name'     => _x('支援額範囲', 'taxonomy singular name', 'jgrants-integration'),
            'search_items'      => __('支援額範囲を検索', 'jgrants-integration'),
            'all_items'         => __('すべての支援額範囲', 'jgrants-integration'),
            'edit_item'         => __('支援額範囲を編集', 'jgrants-integration'),
            'update_item'       => __('支援額範囲を更新', 'jgrants-integration'),
            'add_new_item'      => __('新規支援額範囲を追加', 'jgrants-integration'),
            'new_item_name'     => __('新規支援額範囲名', 'jgrants-integration'),
            'menu_name'         => __('支援額範囲', 'jgrants-integration'),
        ];
        
        $args = [
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'amount-range', 'with_front' => false],
            'show_in_rest'      => true,
            'rest_base'         => 'amount-ranges',
        ];
        
        register_taxonomy('amount_range', ['grant'], $args);
        
        // Insert default amount ranges
        $this->insert_default_amount_ranges();
    }
    
    /**
     * Insert default categories
     */
    private function insert_default_categories() {
        $categories = [
            'IT・デジタル化' => 'it-digital',
            '設備投資・機械導入' => 'equipment',
            '研究開発・技術開発' => 'rd',
            '人材育成・雇用' => 'hr',
            '創業・起業' => 'startup',
            '海外展開・輸出' => 'overseas',
            '環境・エネルギー' => 'environment',
            '地域振興・観光' => 'regional',
            '農林水産業' => 'agriculture',
            '医療・福祉・介護' => 'medical',
            '災害対策・BCP' => 'disaster',
            'その他' => 'other',
        ];
        
        foreach ($categories as $name => $slug) {
            if (!term_exists($name, 'grant_category')) {
                wp_insert_term($name, 'grant_category', ['slug' => $slug]);
            }
        }
    }
    
    /**
     * Insert default prefectures
     */
    private function insert_default_prefectures() {
        $regions = [
            '全国' => [
                '全国' => 'nationwide'
            ],
            '北海道・東北' => [
                '北海道' => 'hokkaido',
                '青森県' => 'aomori',
                '岩手県' => 'iwate',
                '宮城県' => 'miyagi',
                '秋田県' => 'akita',
                '山形県' => 'yamagata',
                '福島県' => 'fukushima',
            ],
            '関東' => [
                '茨城県' => 'ibaraki',
                '栃木県' => 'tochigi',
                '群馬県' => 'gunma',
                '埼玉県' => 'saitama',
                '千葉県' => 'chiba',
                '東京都' => 'tokyo',
                '神奈川県' => 'kanagawa',
            ],
            '中部' => [
                '新潟県' => 'niigata',
                '富山県' => 'toyama',
                '石川県' => 'ishikawa',
                '福井県' => 'fukui',
                '山梨県' => 'yamanashi',
                '長野県' => 'nagano',
                '岐阜県' => 'gifu',
                '静岡県' => 'shizuoka',
                '愛知県' => 'aichi',
            ],
            '近畿' => [
                '三重県' => 'mie',
                '滋賀県' => 'shiga',
                '京都府' => 'kyoto',
                '大阪府' => 'osaka',
                '兵庫県' => 'hyogo',
                '奈良県' => 'nara',
                '和歌山県' => 'wakayama',
            ],
            '中国' => [
                '鳥取県' => 'tottori',
                '島根県' => 'shimane',
                '岡山県' => 'okayama',
                '広島県' => 'hiroshima',
                '山口県' => 'yamaguchi',
            ],
            '四国' => [
                '徳島県' => 'tokushima',
                '香川県' => 'kagawa',
                '愛媛県' => 'ehime',
                '高知県' => 'kochi',
            ],
            '九州・沖縄' => [
                '福岡県' => 'fukuoka',
                '佐賀県' => 'saga',
                '長崎県' => 'nagasaki',
                '熊本県' => 'kumamoto',
                '大分県' => 'oita',
                '宮崎県' => 'miyazaki',
                '鹿児島県' => 'kagoshima',
                '沖縄県' => 'okinawa',
            ],
        ];
        
        foreach ($regions as $region_name => $prefectures) {
            // Create parent region term
            $parent = 0;
            if ($region_name !== '全国') {
                if (!term_exists($region_name, 'prefecture')) {
                    $parent_term = wp_insert_term($region_name, 'prefecture');
                    if (!is_wp_error($parent_term)) {
                        $parent = $parent_term['term_id'];
                    }
                }
            }
            
            // Create prefecture terms
            foreach ($prefectures as $name => $slug) {
                if (!term_exists($name, 'prefecture')) {
                    wp_insert_term($name, 'prefecture', [
                        'slug' => $slug,
                        'parent' => $parent
                    ]);
                }
            }
        }
    }
    
    /**
     * Insert default target types
     */
    private function insert_default_targets() {
        $targets = [
            '中小企業' => 'sme',
            '小規模事業者' => 'small-business',
            '個人事業主' => 'sole-proprietor',
            'スタートアップ' => 'startup',
            '製造業' => 'manufacturing',
            'サービス業' => 'service',
            '小売業' => 'retail',
            '卸売業' => 'wholesale',
            'NPO法人' => 'npo',
            '組合' => 'union',
            '農業者' => 'farmer',
            '漁業者' => 'fisherman',
            '林業者' => 'forester',
        ];
        
        foreach ($targets as $name => $slug) {
            if (!term_exists($name, 'grant_target')) {
                wp_insert_term($name, 'grant_target', ['slug' => $slug]);
            }
        }
    }
    
    /**
     * Insert default amount ranges
     */
    private function insert_default_amount_ranges() {
        $ranges = [
            '〜100万円' => 'under-1m',
            '100万円〜500万円' => '1m-5m',
            '500万円〜1000万円' => '5m-10m',
            '1000万円〜3000万円' => '10m-30m',
            '3000万円〜5000万円' => '30m-50m',
            '5000万円〜1億円' => '50m-100m',
            '1億円以上' => 'over-100m',
        ];
        
        foreach ($ranges as $name => $slug) {
            if (!term_exists($name, 'amount_range')) {
                wp_insert_term($name, 'amount_range', ['slug' => $slug]);
            }
        }
    }
}