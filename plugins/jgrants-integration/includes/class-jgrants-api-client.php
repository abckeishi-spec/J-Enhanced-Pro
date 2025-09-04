<?php
namespace JGrants;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * JGrants Public API Client Class
 * No API key required - using public API
 */
class API_Client {
    
    private $api_base_url = 'https://api.jgrants-portal.go.jp/exp/v1/public';
    private $timeout = 30;
    
    /**
     * Constructor
     */
    public function __construct() {
        // No API key needed for public API
    }
    
    /**
     * Search subsidies with conditions
     */
    public function search_subsidies($params = []) {
        $endpoint = '/subsidies';
        
        // Default parameters based on API specification
        $default_params = [
            'keyword' => $params['keyword'] ?? '補助金',
            'sort' => $params['sort'] ?? 'created_date',
            'order' => $params['order'] ?? 'DESC',
            'acceptance' => $params['acceptance'] ?? '1', // Within acceptance period by default
            'limit' => $params['limit'] ?? 100,
            'page' => $params['page'] ?? 1
        ];
        
        // Optional parameters
        if (!empty($params['use_purpose'])) {
            $default_params['use_purpose'] = $params['use_purpose'];
        }
        if (!empty($params['industry'])) {
            $default_params['industry'] = $params['industry'];
        }
        if (!empty($params['target_number_of_employees'])) {
            $default_params['target_number_of_employees'] = $params['target_number_of_employees'];
        }
        if (!empty($params['target_area_search'])) {
            $default_params['target_area_search'] = $params['target_area_search'];
        }
        if (!empty($params['prefectures'])) {
            $default_params['prefectures'] = $params['prefectures'];
        }
        
        try {
            $response = $this->make_request($endpoint, 'GET', $default_params);
            
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from API');
            }
            
            return $this->format_subsidies_list($data);
            
        } catch (\Exception $e) {
            error_log('JGrants API Search Error: ' . $e->getMessage());
            return new \WP_Error('api_error', $e->getMessage());
        }
    }
    
    /**
     * Get subsidy details by ID
     */
    public function get_subsidy_by_id($subsidy_id) {
        $endpoint = '/subsidies/id/' . $subsidy_id;
        
        try {
            $response = $this->make_request($endpoint, 'GET');
            
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from API');
            }
            
            return $this->format_single_subsidy($data);
            
        } catch (\Exception $e) {
            error_log('JGrants API Detail Error: ' . $e->getMessage());
            return new \WP_Error('api_error', $e->getMessage());
        }
    }
    
    /**
     * Get popular subsidies
     */
    public function get_popular_subsidies($params = []) {
        $endpoint = '/subsidies/popular';
        
        try {
            $response = $this->make_request($endpoint, 'GET', $params);
            
            if (is_wp_error($response)) {
                // If popular endpoint fails, fallback to regular search
                return $this->search_subsidies(['sort' => 'created_date', 'order' => 'DESC']);
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            return $this->format_subsidies_list($data);
            
        } catch (\Exception $e) {
            error_log('JGrants API Popular Error: ' . $e->getMessage());
            // Fallback to regular search
            return $this->search_subsidies(['sort' => 'created_date', 'order' => 'DESC']);
        }
    }
    
    /**
     * Make API request without authentication
     */
    private function make_request($endpoint, $method = 'GET', $params = []) {
        $url = $this->api_base_url . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];
        
        if ($method === 'GET' && !empty($params)) {
            $url = add_query_arg($params, $url);
        } elseif ($method !== 'GET' && !empty($params)) {
            $args['body'] = json_encode($params);
        }
        
        // Add debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('JGrants API Request: ' . $url);
        }
        
        $response = wp_remote_request($url, $args);
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200 && $response_code !== 201) {
            return new \WP_Error(
                'api_error',
                sprintf('API returned status code %d', $response_code)
            );
        }
        
        return $response;
    }
    
    /**
     * Format subsidies list from API response
     */
    private function format_subsidies_list($data) {
        if (empty($data['result'])) {
            return [];
        }
        
        $formatted = [];
        foreach ($data['result'] as $subsidy) {
            $formatted[] = $this->format_single_subsidy($subsidy);
        }
        
        return $formatted;
    }
    
    /**
     * Format single subsidy data based on actual API response
     */
    private function format_single_subsidy($subsidy) {
        // Map API fields to our structure
        return [
            'subsidy_id' => $subsidy['id'] ?? '',
            'title' => $subsidy['title'] ?? '',
            'organization' => $this->extract_organization($subsidy),
            'description' => $subsidy['detail'] ?? $subsidy['summary'] ?? '',
            'purpose' => $subsidy['use_purpose'] ?? '',
            'target' => $this->extract_target($subsidy),
            'max_amount' => $this->extract_amount($subsidy['subsidy_max_limit'] ?? ''),
            'min_amount' => $this->extract_amount($subsidy['subsidy_min_limit'] ?? ''),
            'subsidy_rate' => $subsidy['subsidy_rate'] ?? '',
            'deadline' => $subsidy['acceptance_end_datetime'] ?? '',
            'application_start' => $subsidy['acceptance_start_datetime'] ?? '',
            'official_url' => $subsidy['inquiry_url'] ?? '',
            'status' => $this->determine_status($subsidy),
            'prefecture' => $this->extract_prefecture($subsidy),
            'category' => $this->determine_category($subsidy),
            'industry' => $subsidy['industry'] ?? '',
            'keywords' => $subsidy['keywords'] ?? [],
            'target_area' => $subsidy['target_area_search'] ?? '全国',
            'created_date' => $subsidy['created_date'] ?? '',
            'updated_date' => $subsidy['last_modified_date'] ?? '',
            'target_number_of_employees' => $subsidy['target_number_of_employees'] ?? '',
            'is_recommended' => $subsidy['recommended_subsidy_flag'] ?? false,
            'competent_authorities' => $subsidy['competent_authorities'] ?? [],
        ];
    }
    
    /**
     * Extract organization from subsidy data
     */
    private function extract_organization($subsidy) {
        if (!empty($subsidy['competent_authorities'])) {
            if (is_array($subsidy['competent_authorities'])) {
                return implode(', ', $subsidy['competent_authorities']);
            }
            return $subsidy['competent_authorities'];
        }
        return '実施機関不明';
    }
    
    /**
     * Extract target information
     */
    private function extract_target($subsidy) {
        $targets = [];
        
        if (!empty($subsidy['industry'])) {
            $targets[] = $subsidy['industry'];
        }
        if (!empty($subsidy['target_number_of_employees'])) {
            $targets[] = '従業員数: ' . $subsidy['target_number_of_employees'];
        }
        if (!empty($subsidy['target_area_search'])) {
            $targets[] = '対象地域: ' . $subsidy['target_area_search'];
        }
        
        return implode(' / ', $targets) ?: '対象者情報なし';
    }
    
    /**
     * Extract amount from string (e.g., "1000万円" -> 10000000)
     */
    private function extract_amount($amount_str) {
        if (empty($amount_str)) {
            return 0;
        }
        
        // Remove non-numeric characters except for 万 and 億
        preg_match('/([0-9,]+)(万|億)?円?/', $amount_str, $matches);
        
        if (empty($matches[1])) {
            return 0;
        }
        
        $number = str_replace(',', '', $matches[1]);
        
        if (!empty($matches[2])) {
            if ($matches[2] === '万') {
                $number = $number * 10000;
            } elseif ($matches[2] === '億') {
                $number = $number * 100000000;
            }
        }
        
        return intval($number);
    }
    
    /**
     * Extract prefecture from subsidy data
     */
    private function extract_prefecture($subsidy) {
        if (!empty($subsidy['prefectures'])) {
            if (is_array($subsidy['prefectures'])) {
                return $subsidy['prefectures'];
            }
            return [$subsidy['prefectures']];
        }
        
        if (!empty($subsidy['target_area_search'])) {
            return $this->area_to_prefectures($subsidy['target_area_search']);
        }
        
        return ['全国'];
    }
    
    /**
     * Convert area name to prefecture list
     */
    private function area_to_prefectures($area) {
        $area_map = [
            '全国' => ['全国'],
            '北海道地方' => ['北海道'],
            '東北地方' => ['青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県'],
            '関東・甲信越地方' => ['茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県', '新潟県', '山梨県', '長野県'],
            '東海・北陸地方' => ['富山県', '石川県', '福井県', '岐阜県', '静岡県', '愛知県', '三重県'],
            '近畿地方' => ['滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県'],
            '中国地方' => ['鳥取県', '島根県', '岡山県', '広島県', '山口県'],
            '四国地方' => ['徳島県', '香川県', '愛媛県', '高知県'],
            '九州・沖縄地方' => ['福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'],
        ];
        
        return $area_map[$area] ?? [$area];
    }
    
    /**
     * Determine subsidy status based on acceptance period
     */
    private function determine_status($subsidy) {
        $now = current_time('timestamp');
        
        if (!empty($subsidy['acceptance_end_datetime'])) {
            $end_time = strtotime($subsidy['acceptance_end_datetime']);
            if ($end_time && $end_time < $now) {
                return 'closed';
            }
        }
        
        if (!empty($subsidy['acceptance_start_datetime'])) {
            $start_time = strtotime($subsidy['acceptance_start_datetime']);
            if ($start_time && $start_time > $now) {
                return 'upcoming';
            }
        }
        
        return 'active';
    }
    
    /**
     * Determine category based on use_purpose
     */
    private function determine_category($subsidy) {
        $purpose = $subsidy['use_purpose'] ?? '';
        
        $category_map = [
            'IT導入' => 'IT・デジタル化',
            'デジタル' => 'IT・デジタル化',
            '設備' => '設備投資・機械導入',
            '機械' => '設備投資・機械導入',
            '研究開発' => '研究開発・技術開発',
            '実証' => '研究開発・技術開発',
            '人材育成' => '人材育成・雇用',
            '雇用' => '人材育成・雇用',
            '創業' => '創業・起業',
            '起業' => '創業・起業',
            '海外展開' => '海外展開・輸出',
            '輸出' => '海外展開・輸出',
            'エコ' => '環境・エネルギー',
            'SDGs' => '環境・エネルギー',
            '地域振興' => '地域振興・観光',
            'まちづくり' => '地域振興・観光',
            '農' => '農林水産業',
            '林' => '農林水産業',
            '漁' => '農林水産業',
            '医療' => '医療・福祉・介護',
            '福祉' => '医療・福祉・介護',
            '介護' => '医療・福祉・介護',
            '災害' => '災害対策・BCP',
            '防災' => '災害対策・BCP',
        ];
        
        foreach ($category_map as $keyword => $category) {
            if (mb_strpos($purpose, $keyword) !== false) {
                return $category;
            }
        }
        
        return 'その他';
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        try {
            // Try to fetch one subsidy to test connection
            $response = $this->search_subsidies([
                'keyword' => '補助金',
                'limit' => 1,
                'acceptance' => '0'
            ]);
            
            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'message' => $response->get_error_message()
                ];
            }
            
            return [
                'success' => true,
                'message' => 'JGrants公開API接続成功',
                'api_version' => 'v1',
                'status' => 'ok'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'API接続エラー: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get use purpose options from API spec
     */
    public function get_use_purpose_options() {
        return [
            '新たな事業を行いたい',
            '販路拡大・海外展開をしたい',
            'イベント・事業運営支援がほしい',
            '事業を引き継ぎたい',
            '研究開発・実証事業を行いたい',
            '人材育成を行いたい',
            '資金繰りを改善したい',
            '設備整備・IT導入をしたい',
            '雇用・職場環境を改善したい',
            'エコ・SDGs活動支援がほしい',
            '災害（自然災害、感染症等）支援がほしい',
            '教育・子育て・少子化支援がほしい',
            'スポーツ・文化支援がほしい',
            '安全・防災対策支援がほしい',
            'まちづくり・地域振興支援がほしい'
        ];
    }
    
    /**
     * Get industry options from API spec
     */
    public function get_industry_options() {
        return [
            '農業、林業',
            '漁業',
            '鉱業、採石業、砂利採取業',
            '建設業',
            '製造業',
            '電気・ガス・熱供給・水道業',
            '情報通信業',
            '運輸業、郵便業',
            '卸売業、小売業',
            '金融業、保険業',
            '不動産業、物品賃貸業',
            '学術研究、専門・技術サービス業',
            '宿泊業、飲食サービス業',
            '生活関連サービス業、娯楽業',
            '教育、学習支援業',
            '医療、福祉',
            '複合サービス事業',
            'サービス業（他に分類されないもの）',
            '公務（他に分類されるものを除く）',
            '分類不能の産業'
        ];
    }
    
    /**
     * Get employee number options from API spec
     */
    public function get_employee_options() {
        return [
            '従業員数の制約なし',
            '5名以下',
            '20名以下',
            '50名以下',
            '100名以下',
            '300名以下',
            '900名以下',
            '901名以上'
        ];
    }
    
    /**
     * Get target area options from API spec  
     */
    public function get_target_area_options() {
        return [
            '全国',
            '北海道地方',
            '東北地方',
            '関東・甲信越地方',
            '東海・北陸地方',
            '近畿地方',
            '中国地方',
            '四国地方',
            '九州・沖縄地方'
        ];
    }
}