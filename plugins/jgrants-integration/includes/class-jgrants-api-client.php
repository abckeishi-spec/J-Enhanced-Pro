<?php
namespace JGrants;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * JGrants API Client Class
 */
class API_Client {
    
    private $api_url;
    private $api_key;
    private $timeout = 30;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_url = get_option('jgrants_api_url', 'https://api.jgrants.go.jp/v2/');
        $this->api_key = get_option('jgrants_api_key', '');
    }
    
    /**
     * Fetch grants from JGrants API
     */
    public function fetch_grants($params = []) {
        $endpoint = 'subsidies';
        
        // Default parameters
        $default_params = [
            'limit' => 100,
            'offset' => 0,
            'status' => 'active',
            'sort' => 'deadline_desc'
        ];
        
        $params = wp_parse_args($params, $default_params);
        
        try {
            $response = $this->make_request($endpoint, 'GET', $params);
            
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from API');
            }
            
            return $this->format_grants_data($data);
            
        } catch (\Exception $e) {
            error_log('JGrants API Error: ' . $e->getMessage());
            return new \WP_Error('api_error', $e->getMessage());
        }
    }
    
    /**
     * Fetch single grant details
     */
    public function fetch_grant_details($grant_id) {
        $endpoint = 'subsidies/' . $grant_id;
        
        try {
            $response = $this->make_request($endpoint, 'GET');
            
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from API');
            }
            
            return $this->format_single_grant($data);
            
        } catch (\Exception $e) {
            error_log('JGrants API Error: ' . $e->getMessage());
            return new \WP_Error('api_error', $e->getMessage());
        }
    }
    
    /**
     * Search grants by keyword
     */
    public function search_grants($keyword, $filters = []) {
        $endpoint = 'subsidies/search';
        
        $params = array_merge([
            'keyword' => $keyword,
            'limit' => 50
        ], $filters);
        
        try {
            $response = $this->make_request($endpoint, 'GET', $params);
            
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            return $this->format_grants_data($data);
            
        } catch (\Exception $e) {
            error_log('JGrants API Search Error: ' . $e->getMessage());
            return new \WP_Error('api_error', $e->getMessage());
        }
    }
    
    /**
     * Get grant categories from API
     */
    public function fetch_categories() {
        $endpoint = 'categories';
        
        try {
            $response = $this->make_request($endpoint, 'GET');
            
            if (is_wp_error($response)) {
                // Return default categories if API fails
                return $this->get_default_categories();
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            if (!empty($data['categories'])) {
                return $data['categories'];
            }
            
            return $this->get_default_categories();
            
        } catch (\Exception $e) {
            error_log('JGrants API Categories Error: ' . $e->getMessage());
            return $this->get_default_categories();
        }
    }
    
    /**
     * Make API request
     */
    private function make_request($endpoint, $method = 'GET', $params = []) {
        $url = trailingslashit($this->api_url) . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
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
     * Format grants data from API response
     */
    private function format_grants_data($data) {
        if (empty($data['subsidies']) && empty($data['items'])) {
            return [];
        }
        
        $grants = $data['subsidies'] ?? $data['items'] ?? [];
        $formatted = [];
        
        foreach ($grants as $grant) {
            $formatted[] = $this->format_single_grant($grant);
        }
        
        return $formatted;
    }
    
    /**
     * Format single grant data
     */
    private function format_single_grant($grant) {
        // Map API fields to our structure
        return [
            'grant_id' => $grant['id'] ?? $grant['subsidy_id'] ?? '',
            'title' => $grant['title'] ?? $grant['name'] ?? '',
            'organization' => $grant['organization'] ?? $grant['provider'] ?? '',
            'description' => $grant['description'] ?? $grant['summary'] ?? '',
            'purpose' => $grant['purpose'] ?? '',
            'target' => $grant['target'] ?? $grant['eligible_entities'] ?? '',
            'max_amount' => $grant['max_amount'] ?? $grant['maximum_amount'] ?? 0,
            'min_amount' => $grant['min_amount'] ?? $grant['minimum_amount'] ?? 0,
            'subsidy_rate' => $grant['subsidy_rate'] ?? $grant['rate'] ?? '',
            'deadline' => $grant['deadline'] ?? $grant['application_deadline'] ?? '',
            'application_start' => $grant['application_start'] ?? $grant['start_date'] ?? '',
            'official_url' => $grant['official_url'] ?? $grant['url'] ?? '',
            'status' => $this->determine_status($grant),
            'prefecture' => $grant['prefecture'] ?? $grant['region'] ?? '',
            'category' => $grant['category'] ?? $grant['field'] ?? '',
            'requirements' => $grant['requirements'] ?? [],
            'documents' => $grant['required_documents'] ?? [],
            'contact' => $grant['contact'] ?? [],
            'updated_at' => $grant['updated_at'] ?? $grant['last_modified'] ?? '',
        ];
    }
    
    /**
     * Determine grant status based on deadline
     */
    private function determine_status($grant) {
        // Check if status is explicitly provided
        if (!empty($grant['status'])) {
            return $grant['status'] === 'open' ? 'active' : 'closed';
        }
        
        // Determine by deadline
        $deadline = $grant['deadline'] ?? $grant['application_deadline'] ?? '';
        if (empty($deadline)) {
            return 'active';
        }
        
        $deadline_timestamp = strtotime($deadline);
        if ($deadline_timestamp && $deadline_timestamp < time()) {
            return 'closed';
        }
        
        return 'active';
    }
    
    /**
     * Get default categories
     */
    private function get_default_categories() {
        return [
            ['id' => 'it-digital', 'name' => 'IT・デジタル化', 'slug' => 'it-digital'],
            ['id' => 'equipment', 'name' => '設備投資・機械導入', 'slug' => 'equipment'],
            ['id' => 'rd', 'name' => '研究開発・技術開発', 'slug' => 'rd'],
            ['id' => 'hr', 'name' => '人材育成・雇用', 'slug' => 'hr'],
            ['id' => 'startup', 'name' => '創業・起業', 'slug' => 'startup'],
            ['id' => 'overseas', 'name' => '海外展開・輸出', 'slug' => 'overseas'],
            ['id' => 'environment', 'name' => '環境・エネルギー', 'slug' => 'environment'],
            ['id' => 'regional', 'name' => '地域振興・観光', 'slug' => 'regional'],
            ['id' => 'agriculture', 'name' => '農林水産業', 'slug' => 'agriculture'],
            ['id' => 'medical', 'name' => '医療・福祉・介護', 'slug' => 'medical'],
            ['id' => 'disaster', 'name' => '災害対策・BCP', 'slug' => 'disaster'],
            ['id' => 'other', 'name' => 'その他', 'slug' => 'other'],
        ];
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        $endpoint = 'status';
        
        try {
            $response = $this->make_request($endpoint, 'GET');
            
            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'message' => $response->get_error_message()
                ];
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            return [
                'success' => true,
                'message' => 'API接続成功',
                'api_version' => $data['version'] ?? 'unknown',
                'status' => $data['status'] ?? 'ok'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'API接続エラー: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * AJAX handler for testing connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('jgrants_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $result = $this->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Get prefectures list
     */
    public function get_prefectures() {
        return [
            '北海道' => '北海道',
            '青森県' => '青森県',
            '岩手県' => '岩手県',
            '宮城県' => '宮城県',
            '秋田県' => '秋田県',
            '山形県' => '山形県',
            '福島県' => '福島県',
            '茨城県' => '茨城県',
            '栃木県' => '栃木県',
            '群馬県' => '群馬県',
            '埼玉県' => '埼玉県',
            '千葉県' => '千葉県',
            '東京都' => '東京都',
            '神奈川県' => '神奈川県',
            '新潟県' => '新潟県',
            '富山県' => '富山県',
            '石川県' => '石川県',
            '福井県' => '福井県',
            '山梨県' => '山梨県',
            '長野県' => '長野県',
            '岐阜県' => '岐阜県',
            '静岡県' => '静岡県',
            '愛知県' => '愛知県',
            '三重県' => '三重県',
            '滋賀県' => '滋賀県',
            '京都府' => '京都府',
            '大阪府' => '大阪府',
            '兵庫県' => '兵庫県',
            '奈良県' => '奈良県',
            '和歌山県' => '和歌山県',
            '鳥取県' => '鳥取県',
            '島根県' => '島根県',
            '岡山県' => '岡山県',
            '広島県' => '広島県',
            '山口県' => '山口県',
            '徳島県' => '徳島県',
            '香川県' => '香川県',
            '愛媛県' => '愛媛県',
            '高知県' => '高知県',
            '福岡県' => '福岡県',
            '佐賀県' => '佐賀県',
            '長崎県' => '長崎県',
            '熊本県' => '熊本県',
            '大分県' => '大分県',
            '宮崎県' => '宮崎県',
            '鹿児島県' => '鹿児島県',
            '沖縄県' => '沖縄県',
            '全国' => '全国',
        ];
    }
}