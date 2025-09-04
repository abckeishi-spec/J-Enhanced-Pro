<?php
namespace JGrants;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Content Generator Class
 */
class AI_Generator {
    
    private $api_key;
    private $model;
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('openai_api_key', '');
        $this->model = get_option('openai_model', 'gpt-4-turbo-preview');
    }
    
    /**
     * Generate all AI content for a grant post
     */
    public function generate_content_for_post($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'grant') {
            return false;
        }
        
        // Get grant data
        $grant_data = $this->get_grant_data($post_id);
        
        // Generate title if needed
        $title = $this->generate_title($grant_data);
        if ($title && empty($post->post_title)) {
            wp_update_post([
                'ID' => $post_id,
                'post_title' => $title
            ]);
        }
        
        // Generate excerpt
        $excerpt = $this->generate_excerpt($grant_data);
        if ($excerpt) {
            wp_update_post([
                'ID' => $post_id,
                'post_excerpt' => $excerpt
            ]);
        }
        
        // Generate main content
        $content = $this->generate_detailed_content($grant_data);
        if ($content) {
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $content
            ]);
        }
        
        // Auto-categorize
        if (get_option('ai_categorization', true)) {
            $this->categorize_grant($post_id, $grant_data);
        }
        
        // Extract prefecture
        if (get_option('ai_prefecture_extraction', true)) {
            $this->extract_prefecture($post_id, $grant_data);
        }
        
        // Update AI generation timestamp
        update_post_meta($post_id, '_ai_generated', current_time('mysql'));
        
        return true;
    }
    
    /**
     * Generate SEO-optimized title
     */
    public function generate_title($grant_data) {
        $prompt_template = get_option('ai_title_prompt', 
            '以下の補助金情報から、SEOに最適化された魅力的なタイトルを生成してください。タイトルは60文字以内で、キーワードを含め、クリック率が高くなるようにしてください。補助金名: {grant_name}, 実施機関: {organization}, 最大支援額: {max_amount}, 対象: {target}, 締切: {deadline}'
        );
        
        $prompt = str_replace(
            ['{grant_name}', '{organization}', '{max_amount}', '{target}', '{deadline}'],
            [
                $grant_data['title'] ?? '',
                $grant_data['organization'] ?? '',
                $grant_data['max_amount'] ? '最大' . number_format($grant_data['max_amount']) . '円' : '',
                $grant_data['target'] ?? '',
                $grant_data['deadline'] ?? ''
            ],
            $prompt_template
        );
        
        $system_prompt = "あなたは補助金情報のSEOスペシャリストです。検索エンジンで上位表示され、ユーザーがクリックしたくなるタイトルを作成してください。";
        
        $response = $this->call_openai_api($system_prompt, $prompt);
        
        if ($response && !is_wp_error($response)) {
            return $this->sanitize_title($response);
        }
        
        return false;
    }
    
    /**
     * Generate excerpt/summary
     */
    public function generate_excerpt($grant_data) {
        $prompt = "以下の補助金情報から、重要なポイントを150文字以内で簡潔にまとめてください。\n\n";
        $prompt .= $this->format_grant_data_for_prompt($grant_data);
        
        $system_prompt = "あなたは補助金情報を簡潔にまとめる専門家です。事業者が最も知りたい情報を優先的に含めてください。";
        
        $response = $this->call_openai_api($system_prompt, $prompt);
        
        if ($response && !is_wp_error($response)) {
            return $this->sanitize_excerpt($response);
        }
        
        return false;
    }
    
    /**
     * Generate detailed content
     */
    public function generate_detailed_content($grant_data) {
        $prompt_template = get_option('ai_content_prompt',
            '以下の補助金情報から、事業者に役立つ詳細な解説記事を生成してください。見出しは以下の構成で作成してください: 1.概要, 2.対象者・条件, 3.支援内容, 4.申請のポイント, 5.注意事項, 6.まとめ'
        );
        
        $prompt = $prompt_template . "\n\n";
        $prompt .= $this->format_grant_data_for_prompt($grant_data);
        $prompt .= "\n\nHTMLタグ（h2, h3, p, ul, li, strong, table等）を使用して、読みやすく構造化された記事を作成してください。";
        
        $system_prompt = "あなたは補助金申請のコンサルタントです。申請を検討している事業者に対して、分かりやすく実用的な情報を提供してください。専門用語は必要に応じて説明を加えてください。";
        
        $response = $this->call_openai_api($system_prompt, $prompt, 2000);
        
        if ($response && !is_wp_error($response)) {
            return $this->format_content_html($response);
        }
        
        return false;
    }
    
    /**
     * Auto-categorize grant using AI
     */
    public function categorize_grant($post_id, $grant_data) {
        $categories = $this->get_available_categories();
        $categories_list = implode(', ', array_column($categories, 'name'));
        
        $prompt = "以下の補助金情報を分析し、最も適切なカテゴリを1つ選んでください。\n";
        $prompt .= "選択可能なカテゴリ: " . $categories_list . "\n\n";
        $prompt .= $this->format_grant_data_for_prompt($grant_data);
        $prompt .= "\n\n選択したカテゴリ名のみを回答してください。";
        
        $system_prompt = "あなたは補助金の分類専門家です。補助金の内容を正確に分析し、最も適切なカテゴリを判定してください。";
        
        $response = $this->call_openai_api($system_prompt, $prompt);
        
        if ($response && !is_wp_error($response)) {
            $category_name = trim($response);
            
            // Find matching category
            foreach ($categories as $category) {
                if ($category['name'] === $category_name || 
                    stripos($category['name'], $category_name) !== false ||
                    stripos($category_name, $category['name']) !== false) {
                    
                    // Get or create term
                    $term = term_exists($category['name'], 'grant_category');
                    if (!$term) {
                        $term = wp_insert_term($category['name'], 'grant_category', [
                            'slug' => $category['slug']
                        ]);
                    }
                    
                    if (!is_wp_error($term)) {
                        $term_id = is_array($term) ? $term['term_id'] : $term;
                        wp_set_object_terms($post_id, $term_id, 'grant_category');
                        return true;
                    }
                }
            }
        }
        
        // Default to "その他" if categorization fails
        $other_term = term_exists('その他', 'grant_category');
        if ($other_term) {
            wp_set_object_terms($post_id, $other_term['term_id'], 'grant_category');
        }
        
        return false;
    }
    
    /**
     * Extract prefecture from grant data using AI
     */
    public function extract_prefecture($post_id, $grant_data) {
        $prefectures = $this->get_prefecture_list();
        $prefectures_list = implode(', ', $prefectures);
        
        $prompt = "以下の補助金情報から、対象となる都道府県を特定してください。\n";
        $prompt .= "複数の都道府県が対象の場合は、カンマ区切りで全て列挙してください。\n";
        $prompt .= "全国が対象の場合は「全国」と回答してください。\n";
        $prompt .= "都道府県リスト: " . $prefectures_list . "\n\n";
        $prompt .= $this->format_grant_data_for_prompt($grant_data);
        $prompt .= "\n\n都道府県名のみを回答してください。";
        
        $system_prompt = "あなたは日本の地理と行政区分の専門家です。補助金の対象地域を正確に判定してください。";
        
        $response = $this->call_openai_api($system_prompt, $prompt);
        
        if ($response && !is_wp_error($response)) {
            $extracted_prefectures = array_map('trim', explode(',', $response));
            $term_ids = [];
            
            foreach ($extracted_prefectures as $prefecture) {
                if (in_array($prefecture, $prefectures)) {
                    // Get or create term
                    $term = term_exists($prefecture, 'prefecture');
                    if (!$term) {
                        $term = wp_insert_term($prefecture, 'prefecture');
                    }
                    
                    if (!is_wp_error($term)) {
                        $term_ids[] = is_array($term) ? $term['term_id'] : $term;
                    }
                }
            }
            
            if (!empty($term_ids)) {
                wp_set_object_terms($post_id, $term_ids, 'prefecture');
                return true;
            }
        }
        
        // Default to "全国" if extraction fails
        $nationwide_term = term_exists('全国', 'prefecture');
        if ($nationwide_term) {
            wp_set_object_terms($post_id, $nationwide_term['term_id'], 'prefecture');
        }
        
        return false;
    }
    
    /**
     * Call OpenAI API
     */
    private function call_openai_api($system_prompt, $user_prompt, $max_tokens = 500) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'OpenAI APIキーが設定されていません');
        }
        
        $messages = [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_prompt]
        ];
        
        $body = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $max_tokens,
            'temperature' => 0.7,
            'top_p' => 0.9,
        ];
        
        $response = wp_remote_post($this->api_url, [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
        ]);
        
        if (is_wp_error($response)) {
            error_log('OpenAI API Error: ' . $response->get_error_message());
            return $response;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if (isset($data['error'])) {
            error_log('OpenAI API Error: ' . $data['error']['message']);
            return new \WP_Error('api_error', $data['error']['message']);
        }
        
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        
        return new \WP_Error('invalid_response', 'Invalid API response');
    }
    
    /**
     * Get grant data for AI processing
     */
    private function get_grant_data($post_id) {
        $post = get_post($post_id);
        
        return [
            'title' => $post->post_title,
            'description' => $post->post_content,
            'grant_id' => get_post_meta($post_id, '_grant_id', true),
            'organization' => get_post_meta($post_id, '_organization', true),
            'max_amount' => get_post_meta($post_id, '_max_amount', true),
            'min_amount' => get_post_meta($post_id, '_min_amount', true),
            'subsidy_rate' => get_post_meta($post_id, '_subsidy_rate', true),
            'target' => get_post_meta($post_id, '_target', true),
            'purpose' => get_post_meta($post_id, '_purpose', true),
            'deadline' => get_post_meta($post_id, '_deadline', true),
            'application_start' => get_post_meta($post_id, '_application_start', true),
            'official_url' => get_post_meta($post_id, '_official_url', true),
        ];
    }
    
    /**
     * Format grant data for prompt
     */
    private function format_grant_data_for_prompt($grant_data) {
        $formatted = "補助金情報:\n";
        $formatted .= "補助金名: " . ($grant_data['title'] ?? 'N/A') . "\n";
        $formatted .= "実施機関: " . ($grant_data['organization'] ?? 'N/A') . "\n";
        $formatted .= "目的: " . ($grant_data['purpose'] ?? 'N/A') . "\n";
        $formatted .= "対象者: " . ($grant_data['target'] ?? 'N/A') . "\n";
        
        if (!empty($grant_data['max_amount'])) {
            $formatted .= "最大支援額: " . number_format($grant_data['max_amount']) . "円\n";
        }
        if (!empty($grant_data['min_amount'])) {
            $formatted .= "最小支援額: " . number_format($grant_data['min_amount']) . "円\n";
        }
        if (!empty($grant_data['subsidy_rate'])) {
            $formatted .= "補助率: " . $grant_data['subsidy_rate'] . "\n";
        }
        if (!empty($grant_data['deadline'])) {
            $formatted .= "締切日: " . $grant_data['deadline'] . "\n";
        }
        if (!empty($grant_data['application_start'])) {
            $formatted .= "申請開始日: " . $grant_data['application_start'] . "\n";
        }
        
        return $formatted;
    }
    
    /**
     * Get available categories
     */
    private function get_available_categories() {
        $api_client = new API_Client();
        return $api_client->fetch_categories();
    }
    
    /**
     * Get prefecture list
     */
    private function get_prefecture_list() {
        $api_client = new API_Client();
        return array_keys($api_client->get_prefectures());
    }
    
    /**
     * Sanitize AI-generated title
     */
    private function sanitize_title($title) {
        $title = strip_tags($title);
        $title = trim($title);
        $title = mb_substr($title, 0, 100);
        return $title;
    }
    
    /**
     * Sanitize AI-generated excerpt
     */
    private function sanitize_excerpt($excerpt) {
        $excerpt = strip_tags($excerpt);
        $excerpt = trim($excerpt);
        $excerpt = mb_substr($excerpt, 0, 200);
        return $excerpt;
    }
    
    /**
     * Format AI-generated content as HTML
     */
    private function format_content_html($content) {
        // Ensure proper HTML structure
        $content = wpautop($content);
        
        // Allow specific HTML tags
        $allowed_html = [
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'p' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'strong' => [],
            'em' => [],
            'br' => [],
            'table' => ['class' => []],
            'thead' => [],
            'tbody' => [],
            'tr' => [],
            'th' => [],
            'td' => [],
            'div' => ['class' => []],
            'span' => ['class' => []],
        ];
        
        $content = wp_kses($content, $allowed_html);
        
        return $content;
    }
    
    /**
     * AJAX handler for regenerating content
     */
    public function ajax_regenerate_content() {
        check_ajax_referer('jgrants_ajax_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $result = $this->generate_content_for_post($post_id);
        
        if ($result) {
            wp_send_json_success(['message' => 'AIコンテンツが正常に生成されました']);
        } else {
            wp_send_json_error(['message' => 'AIコンテンツの生成に失敗しました']);
        }
    }
    
    /**
     * REST API handler for regenerating content
     */
    public function rest_regenerate_content($request) {
        $post_id = $request->get_param('id');
        
        if (!current_user_can('edit_post', $post_id)) {
            return new \WP_Error('permission_denied', 'Permission denied', ['status' => 403]);
        }
        
        $result = $this->generate_content_for_post($post_id);
        
        if ($result) {
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'AIコンテンツが正常に生成されました'
            ], 200);
        } else {
            return new \WP_Error('generation_failed', 'AIコンテンツの生成に失敗しました', ['status' => 500]);
        }
    }
}