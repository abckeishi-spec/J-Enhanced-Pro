<?php
namespace JGrants;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Content Generator Class using Gemini API
 */
class AI_Generator {
    
    private $api_key;
    private $model;
    private $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('gemini_api_key', '');
        $this->model = get_option('gemini_model', 'gemini-pro');
    }
    
    /**
     * Generate all AI content for a grant post
     */
    public function generate_content_for_post($post_id) {
        // Check rate limiting
        if (!$this->check_rate_limit()) {
            error_log('AI generation rate limit reached');
            return false;
        }
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'grant') {
            return false;
        }
        
        // Get grant data
        $grant_data = $this->get_grant_data($post_id);
        
        // Generate title if needed
        if (get_option('ai_generate_title', true)) {
            $title = $this->generate_title($grant_data);
            if ($title && empty($post->post_title)) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_title' => $title
                ]);
            }
        }
        
        // Generate excerpt
        if (get_option('ai_generate_excerpt', true)) {
            $excerpt = $this->generate_excerpt($grant_data);
            if ($excerpt) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_excerpt' => $excerpt
                ]);
            }
        }
        
        // Generate main content
        if (get_option('ai_generate_content', true)) {
            $content = $this->generate_detailed_content($grant_data);
            if ($content) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $content
                ]);
            }
        }
        
        // Auto-categorize - Dynamic category creation
        if (get_option('ai_categorization', true)) {
            $this->categorize_grant($post_id, $grant_data);
        }
        
        // Extract prefecture
        if (get_option('ai_prefecture_extraction', true)) {
            $this->extract_prefecture($post_id, $grant_data);
        }
        
        // Update AI generation timestamp and count
        update_post_meta($post_id, '_ai_generated', current_time('mysql'));
        $this->increment_generation_count();
        
        return true;
    }
    
    /**
     * Check rate limiting for AI generation
     */
    private function check_rate_limit() {
        $interval_minutes = get_option('ai_rate_limit_minutes', 3);
        $max_requests = get_option('ai_rate_limit_requests', 2);
        
        $last_requests = get_transient('jgrants_ai_requests') ?: [];
        $now = time();
        
        // Clean old requests
        $last_requests = array_filter($last_requests, function($timestamp) use ($now, $interval_minutes) {
            return $timestamp > ($now - ($interval_minutes * 60));
        });
        
        if (count($last_requests) >= $max_requests) {
            return false;
        }
        
        // Add current request
        $last_requests[] = $now;
        set_transient('jgrants_ai_requests', $last_requests, $interval_minutes * 60);
        
        return true;
    }
    
    /**
     * Increment generation count for statistics
     */
    private function increment_generation_count() {
        $count = get_option('ai_generation_count', 0);
        update_option('ai_generation_count', $count + 1);
        
        // Daily count
        $today = date('Y-m-d');
        $daily_key = 'ai_generation_count_' . $today;
        $daily_count = get_option($daily_key, 0);
        update_option($daily_key, $daily_count + 1);
    }
    
    /**
     * Generate SEO-optimized title using Gemini
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
        
        $response = $this->call_gemini_api($prompt);
        
        if ($response && !is_wp_error($response)) {
            return $this->sanitize_title($response);
        }
        
        return false;
    }
    
    /**
     * Generate excerpt/summary using Gemini
     */
    public function generate_excerpt($grant_data) {
        $prompt = "以下の補助金情報から、重要なポイントを150文字以内で簡潔にまとめてください。\n\n";
        $prompt .= $this->format_grant_data_for_prompt($grant_data);
        
        $response = $this->call_gemini_api($prompt);
        
        if ($response && !is_wp_error($response)) {
            return $this->sanitize_excerpt($response);
        }
        
        return false;
    }
    
    /**
     * Generate detailed content using Gemini
     */
    public function generate_detailed_content($grant_data) {
        $prompt_template = get_option('ai_content_prompt',
            '以下の補助金情報から、事業者に役立つ詳細な解説記事を生成してください。見出しは以下の構成で作成してください: 1.概要, 2.対象者・条件, 3.支援内容, 4.申請のポイント, 5.注意事項, 6.まとめ'
        );
        
        $prompt = $prompt_template . "\n\n";
        $prompt .= $this->format_grant_data_for_prompt($grant_data);
        $prompt .= "\n\nHTMLタグ（h2, h3, p, ul, li, strong, table等）を使用して、読みやすく構造化された記事を作成してください。";
        
        $response = $this->call_gemini_api($prompt);
        
        if ($response && !is_wp_error($response)) {
            return $this->format_content_html($response);
        }
        
        return false;
    }
    
    /**
     * Auto-categorize grant using AI - Dynamic category creation
     */
    public function categorize_grant($post_id, $grant_data) {
        // Get existing categories
        $existing_categories = get_terms([
            'taxonomy' => 'grant_category',
            'hide_empty' => false,
            'fields' => 'names'
        ]);
        
        $prompt = "以下の補助金情報を分析し、最も適切なカテゴリを判定してください。\n";
        $prompt .= "既存のカテゴリがある場合はそれを使用し、適切なものがない場合は新しいカテゴリ名を提案してください。\n";
        $prompt .= "既存カテゴリ: " . implode(', ', $existing_categories) . "\n\n";
        $prompt .= $this->format_grant_data_for_prompt($grant_data);
        $prompt .= "\n\nカテゴリ名のみを回答してください。";
        
        $response = $this->call_gemini_api($prompt);
        
        if ($response && !is_wp_error($response)) {
            $category_name = trim($response);
            
            // Check if category exists, if not create it
            $term = term_exists($category_name, 'grant_category');
            if (!$term) {
                $term = wp_insert_term($category_name, 'grant_category', [
                    'slug' => sanitize_title($category_name)
                ]);
            }
            
            if (!is_wp_error($term)) {
                $term_id = is_array($term) ? $term['term_id'] : $term;
                wp_set_object_terms($post_id, $term_id, 'grant_category');
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract prefecture from grant data using AI
     */
    public function extract_prefecture($post_id, $grant_data) {
        // If prefecture data already exists, use it
        if (!empty($grant_data['prefecture']) && is_array($grant_data['prefecture'])) {
            $term_ids = [];
            foreach ($grant_data['prefecture'] as $prefecture) {
                $term = term_exists($prefecture, 'prefecture');
                if (!$term) {
                    $term = wp_insert_term($prefecture, 'prefecture');
                }
                if (!is_wp_error($term)) {
                    $term_ids[] = is_array($term) ? $term['term_id'] : $term;
                }
            }
            
            if (!empty($term_ids)) {
                wp_set_object_terms($post_id, $term_ids, 'prefecture');
                return true;
            }
        }
        
        // Use AI to extract if not clear
        $prefectures = $this->get_prefecture_list();
        $prefectures_list = implode(', ', $prefectures);
        
        $prompt = "以下の補助金情報から、対象となる都道府県を特定してください。\n";
        $prompt .= "複数の都道府県が対象の場合は、カンマ区切りで全て列挙してください。\n";
        $prompt .= "全国が対象の場合は「全国」と回答してください。\n";
        $prompt .= "都道府県リスト: " . $prefectures_list . "\n\n";
        $prompt .= $this->format_grant_data_for_prompt($grant_data);
        $prompt .= "\n\n都道府県名のみを回答してください。";
        
        $response = $this->call_gemini_api($prompt);
        
        if ($response && !is_wp_error($response)) {
            $extracted_prefectures = array_map('trim', explode(',', $response));
            $term_ids = [];
            
            foreach ($extracted_prefectures as $prefecture) {
                if (in_array($prefecture, $prefectures)) {
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
        
        // Default to "全国"
        $nationwide_term = term_exists('全国', 'prefecture');
        if ($nationwide_term) {
            wp_set_object_terms($post_id, $nationwide_term['term_id'], 'prefecture');
        }
        
        return false;
    }
    
    /**
     * Call Gemini API
     */
    private function call_gemini_api($prompt) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'Gemini APIキーが設定されていません');
        }
        
        $url = $this->api_url . $this->model . ':generateContent?key=' . $this->api_key;
        
        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => get_option('gemini_max_tokens', 2048),
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ]
            ]
        ];
        
        $response = wp_remote_post($url, [
            'timeout' => 60,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
        ]);
        
        if (is_wp_error($response)) {
            error_log('Gemini API Error: ' . $response->get_error_message());
            return $response;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if (isset($data['error'])) {
            error_log('Gemini API Error: ' . $data['error']['message']);
            return new \WP_Error('api_error', $data['error']['message']);
        }
        
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }
        
        return new \WP_Error('invalid_response', 'Invalid API response');
    }
    
    /**
     * Batch generate AI content for multiple posts
     */
    public function batch_generate($post_ids, $options = []) {
        $results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0
        ];
        
        $batch_size = $options['batch_size'] ?? get_option('ai_batch_size', 5);
        $delay = $options['delay'] ?? get_option('ai_batch_delay', 10); // seconds
        
        $chunks = array_chunk($post_ids, $batch_size);
        
        foreach ($chunks as $chunk) {
            foreach ($chunk as $post_id) {
                // Check if already generated recently
                if ($this->is_recently_generated($post_id)) {
                    $results['skipped']++;
                    continue;
                }
                
                $result = $this->generate_content_for_post($post_id);
                
                if ($result) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
                
                // Delay between requests
                if ($delay > 0) {
                    sleep($delay);
                }
            }
            
            // Longer delay between chunks
            if ($delay > 0) {
                sleep($delay * 2);
            }
        }
        
        return $results;
    }
    
    /**
     * Check if content was recently generated
     */
    private function is_recently_generated($post_id) {
        $generated_time = get_post_meta($post_id, '_ai_generated', true);
        if (!$generated_time) {
            return false;
        }
        
        $hours = get_option('ai_regenerate_hours', 24);
        $threshold = strtotime('-' . $hours . ' hours');
        
        return strtotime($generated_time) > $threshold;
    }
    
    /**
     * Get grant data for AI processing
     */
    private function get_grant_data($post_id) {
        $post = get_post($post_id);
        
        return [
            'title' => $post->post_title,
            'description' => $post->post_content,
            'subsidy_id' => get_post_meta($post_id, '_subsidy_id', true),
            'organization' => get_post_meta($post_id, '_organization', true),
            'max_amount' => get_post_meta($post_id, '_max_amount', true),
            'min_amount' => get_post_meta($post_id, '_min_amount', true),
            'subsidy_rate' => get_post_meta($post_id, '_subsidy_rate', true),
            'target' => get_post_meta($post_id, '_target', true),
            'purpose' => get_post_meta($post_id, '_purpose', true),
            'deadline' => get_post_meta($post_id, '_deadline', true),
            'application_start' => get_post_meta($post_id, '_application_start', true),
            'official_url' => get_post_meta($post_id, '_official_url', true),
            'industry' => get_post_meta($post_id, '_industry', true),
            'target_area' => get_post_meta($post_id, '_target_area', true),
            'prefecture' => get_post_meta($post_id, '_prefecture', true),
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
        $formatted .= "業種: " . ($grant_data['industry'] ?? 'N/A') . "\n";
        $formatted .= "対象地域: " . ($grant_data['target_area'] ?? 'N/A') . "\n";
        
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
     * REST API handler for batch generation
     */
    public function rest_batch_generate($request) {
        $post_ids = $request->get_param('post_ids');
        $options = [
            'batch_size' => $request->get_param('batch_size') ?? 5,
            'delay' => $request->get_param('delay') ?? 10,
        ];
        
        if (!is_array($post_ids) || empty($post_ids)) {
            return new \WP_Error('invalid_params', 'Invalid post IDs', ['status' => 400]);
        }
        
        $results = $this->batch_generate($post_ids, $options);
        
        return new \WP_REST_Response([
            'success' => true,
            'results' => $results,
            'message' => sprintf(
                '処理完了: %d件成功, %d件失敗, %d件スキップ',
                $results['success'],
                $results['failed'],
                $results['skipped']
            )
        ], 200);
    }
    
    /**
     * Get AI generation statistics
     */
    public function get_statistics() {
        $today = date('Y-m-d');
        
        return [
            'total_generations' => get_option('ai_generation_count', 0),
            'today_generations' => get_option('ai_generation_count_' . $today, 0),
            'rate_limit' => [
                'minutes' => get_option('ai_rate_limit_minutes', 3),
                'requests' => get_option('ai_rate_limit_requests', 2),
            ],
            'batch_settings' => [
                'size' => get_option('ai_batch_size', 5),
                'delay' => get_option('ai_batch_delay', 10),
            ],
            'model' => get_option('gemini_model', 'gemini-pro'),
        ];
    }
}