<?php
namespace JGrants;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Post Type Registration Class
 */
class Post_Type {
    
    /**
     * Register custom post type for grants
     */
    public function register() {
        $labels = [
            'name'                  => _x('補助金', 'Post type general name', 'jgrants-integration'),
            'singular_name'         => _x('補助金', 'Post type singular name', 'jgrants-integration'),
            'menu_name'             => _x('補助金情報', 'Admin Menu text', 'jgrants-integration'),
            'name_admin_bar'        => _x('補助金', 'Add New on Toolbar', 'jgrants-integration'),
            'add_new'               => __('新規追加', 'jgrants-integration'),
            'add_new_item'          => __('新規補助金を追加', 'jgrants-integration'),
            'new_item'              => __('新規補助金', 'jgrants-integration'),
            'edit_item'             => __('補助金を編集', 'jgrants-integration'),
            'view_item'             => __('補助金を表示', 'jgrants-integration'),
            'all_items'             => __('すべての補助金', 'jgrants-integration'),
            'search_items'          => __('補助金を検索', 'jgrants-integration'),
            'parent_item_colon'     => __('親補助金:', 'jgrants-integration'),
            'not_found'             => __('補助金が見つかりませんでした。', 'jgrants-integration'),
            'not_found_in_trash'    => __('ゴミ箱に補助金が見つかりませんでした。', 'jgrants-integration'),
            'featured_image'        => _x('アイキャッチ画像', 'Overrides the "Featured Image" phrase', 'jgrants-integration'),
            'set_featured_image'    => _x('アイキャッチ画像を設定', 'Overrides the "Set featured image" phrase', 'jgrants-integration'),
            'remove_featured_image' => _x('アイキャッチ画像を削除', 'Overrides the "Remove featured image" phrase', 'jgrants-integration'),
            'use_featured_image'    => _x('アイキャッチ画像として使用', 'Overrides the "Use as featured image" phrase', 'jgrants-integration'),
            'archives'              => _x('補助金アーカイブ', 'The post type archive label', 'jgrants-integration'),
            'insert_into_item'      => _x('補助金に挿入', 'Overrides the "Insert into post" phrase', 'jgrants-integration'),
            'uploaded_to_this_item' => _x('この補助金にアップロード', 'Overrides the "Uploaded to this post" phrase', 'jgrants-integration'),
            'filter_items_list'     => _x('補助金リストをフィルター', 'Screen reader text', 'jgrants-integration'),
            'items_list_navigation' => _x('補助金リストナビゲーション', 'Screen reader text', 'jgrants-integration'),
            'items_list'            => _x('補助金リスト', 'Screen reader text', 'jgrants-integration'),
        ];
        
        $args = [
            'labels'                => $labels,
            'public'                => true,
            'publicly_queryable'    => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'query_var'             => true,
            'rewrite'               => ['slug' => 'grants', 'with_front' => false],
            'capability_type'       => 'post',
            'has_archive'           => true,
            'hierarchical'          => false,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-money-alt',
            'supports'              => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions'],
            'show_in_rest'          => true,
            'rest_base'             => 'grants',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        ];
        
        register_post_type('grant', $args);
        
        // Add custom post status
        register_post_status('expired', [
            'label'                     => _x('期限切れ', 'post status', 'jgrants-integration'),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('期限切れ <span class="count">(%s)</span>', '期限切れ <span class="count">(%s)</span>', 'jgrants-integration'),
        ]);
    }
    
    /**
     * Get post type capabilities
     */
    public function get_capabilities() {
        return [
            'edit_post'          => 'edit_grant',
            'edit_posts'         => 'edit_grants',
            'edit_others_posts'  => 'edit_others_grants',
            'publish_posts'      => 'publish_grants',
            'read_post'          => 'read_grant',
            'read_private_posts' => 'read_private_grants',
            'delete_post'        => 'delete_grant',
            'delete_posts'       => 'delete_grants',
        ];
    }
    
    /**
     * Add custom capabilities to roles
     */
    public function add_capabilities() {
        $role = get_role('administrator');
        
        if ($role) {
            $capabilities = $this->get_capabilities();
            foreach ($capabilities as $cap) {
                $role->add_cap($cap);
            }
        }
        
        // Add capabilities to editor role
        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap('edit_grant');
            $editor->add_cap('edit_grants');
            $editor->add_cap('edit_others_grants');
            $editor->add_cap('publish_grants');
            $editor->add_cap('read_grant');
            $editor->add_cap('read_private_grants');
            $editor->add_cap('delete_grant');
            $editor->add_cap('delete_grants');
        }
    }
    
    /**
     * Remove custom capabilities from roles
     */
    public function remove_capabilities() {
        $role = get_role('administrator');
        
        if ($role) {
            $capabilities = $this->get_capabilities();
            foreach ($capabilities as $cap) {
                $role->remove_cap($cap);
            }
        }
        
        $editor = get_role('editor');
        if ($editor) {
            $editor->remove_cap('edit_grant');
            $editor->remove_cap('edit_grants');
            $editor->remove_cap('edit_others_grants');
            $editor->remove_cap('publish_grants');
            $editor->remove_cap('read_grant');
            $editor->remove_cap('read_private_grants');
            $editor->remove_cap('delete_grant');
            $editor->remove_cap('delete_grants');
        }
    }
}