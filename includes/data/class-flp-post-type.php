<?php
/**
 * カスタム投稿タイプ管理クラス
 */

if (!defined('ABSPATH')) exit;

class FLP_Post_Type {

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'setup_capabilities'));
    }

    /**
     * カスタム投稿タイプ 'flp_lp' の登録
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => __('LP', 'finelive-lp'),
            'singular_name'         => __('LP', 'finelive-lp'),
            'menu_name'             => __('LP管理', 'finelive-lp'),
            'name_admin_bar'        => __('LP', 'finelive-lp'),
            'add_new'               => __('新規追加', 'finelive-lp'),
            'add_new_item'          => __('新しいLPを追加', 'finelive-lp'),
            'new_item'              => __('新しいLP', 'finelive-lp'),
            'edit_item'             => __('LPを編集', 'finelive-lp'),
            'view_item'             => __('LPを表示', 'finelive-lp'),
            'all_items'             => __('LP一覧', 'finelive-lp'),
            'search_items'          => __('LPを検索', 'finelive-lp'),
            'parent_item_colon'     => __('親LP:', 'finelive-lp'),
            'not_found'             => __('LPが見つかりません', 'finelive-lp'),
            'not_found_in_trash'    => __('ゴミ箱にLPが見つかりません', 'finelive-lp'),
            'featured_image'        => __('アイキャッチ画像', 'finelive-lp'),
            'set_featured_image'    => __('アイキャッチ画像を設定', 'finelive-lp'),
            'remove_featured_image' => __('アイキャッチ画像を削除', 'finelive-lp'),
            'use_featured_image'    => __('アイキャッチ画像として使用', 'finelive-lp'),
            'archives'              => __('LPアーカイブ', 'finelive-lp'),
            'insert_into_item'      => __('LPに挿入', 'finelive-lp'),
            'uploaded_to_this_item' => __('このLPにアップロード', 'finelive-lp'),
            'filter_items_list'     => __('LPリストをフィルター', 'finelive-lp'),
            'items_list_navigation' => __('LPリストナビゲーション', 'finelive-lp'),
            'items_list'            => __('LPリスト', 'finelive-lp'),
        );

        $args = array(
            'labels'                => $labels,
            'description'           => __('ランディングページを管理します', 'finelive-lp'),
            'public'                => false,
            'publicly_queryable'    => false,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'query_var'             => true,
            'rewrite'               => false,
            'capabilities'          => $this->get_post_type_capabilities(),
            'map_meta_cap'          => true,
            'has_archive'           => false,
            'hierarchical'          => false,
            'menu_position'         => 20,
            'menu_icon'             => 'dashicons-format-gallery',
            'supports'              => array('title'),
            'show_in_rest'          => false, // ブロックエディタを無効化
        );

        register_post_type('flp_lp', $args);
    }

    /**
     * カスタム投稿タイプの権限設定を取得
     *
     * @return array
     */
    private function get_post_type_capabilities() {
        return array(
            'edit_post'              => 'edit_flp_lp',
            'read_post'              => 'read_flp_lp',
            'delete_post'            => 'delete_flp_lp',
            'edit_posts'             => 'edit_flp_lps',
            'edit_others_posts'      => 'edit_others_flp_lps',
            'delete_posts'           => 'delete_flp_lps',
            'publish_posts'          => 'publish_flp_lps',
            'read_private_posts'     => 'read_private_flp_lps',
            'delete_private_posts'   => 'delete_private_flp_lps',
            'delete_published_posts' => 'delete_published_flp_lps',
            'delete_others_posts'    => 'delete_others_flp_lps',
            'edit_private_posts'     => 'edit_private_flp_lps',
            'edit_published_posts'   => 'edit_published_flp_lps',
            'create_posts'           => 'edit_flp_lps',
        );
    }

    /**
     * ユーザー権限の設定
     */
    public function setup_capabilities() {
        // 管理者権限を取得
        $role = get_role('administrator');
        
        if (!$role) {
            return;
        }

        // 既に権限が設定されている場合はスキップ
        if ($role->has_cap('edit_flp_lps')) {
            return;
        }

        // 管理者にLPの全権限を付与
        $capabilities = array(
            'edit_flp_lp',
            'read_flp_lp',
            'delete_flp_lp',
            'edit_flp_lps',
            'edit_others_flp_lps',
            'delete_flp_lps',
            'publish_flp_lps',
            'read_private_flp_lps',
            'delete_private_flp_lps',
            'delete_published_flp_lps',
            'delete_others_flp_lps',
            'edit_private_flp_lps',
            'edit_published_flp_lps',
            'create_flp_lps',
        );

        foreach ($capabilities as $capability) {
            $role->add_cap($capability);
        }

        // 編集者にも基本的な権限を付与（オプション）
        $editor_role = get_role('editor');
        if ($editor_role && apply_filters('flp_grant_editor_capabilities', false)) {
            $editor_capabilities = array(
                'edit_flp_lp',
                'read_flp_lp',
                'delete_flp_lp',
                'edit_flp_lps',
                'publish_flp_lps',
                'edit_published_flp_lps',
                'create_flp_lps',
            );

            foreach ($editor_capabilities as $capability) {
                $editor_role->add_cap($capability);
            }
        }
    }

    /**
     * プラグイン無効化時に権限を削除
     */
    public static function remove_capabilities() {
        $roles = array('administrator', 'editor');
        
        $capabilities = array(
            'edit_flp_lp',
            'read_flp_lp',
            'delete_flp_lp',
            'edit_flp_lps',
            'edit_others_flp_lps',
            'delete_flp_lps',
            'publish_flp_lps',
            'read_private_flp_lps',
            'delete_private_flp_lps',
            'delete_published_flp_lps',
            'delete_others_flp_lps',
            'edit_private_flp_lps',
            'edit_published_flp_lps',
            'create_flp_lps',
        );

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $capability) {
                    $role->remove_cap($capability);
                }
            }
        }
    }

    /**
     * 投稿一覧にカスタムカラムを追加
     */
    public function add_custom_columns() {
        add_filter('manage_flp_lp_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_flp_lp_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        add_filter('manage_edit-flp_lp_sortable_columns', array($this, 'set_sortable_columns'));
    }

    /**
     * カスタムカラムの定義
     *
     * @param array $columns 既存のカラム
     * @return array
     */
    public function set_custom_columns($columns) {
        $new_columns = array();
        
        // 既存のチェックボックスとタイトルを維持
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        
        // カスタムカラムを追加
        $new_columns['lp_id'] = __('LP ID', 'finelive-lp');
        $new_columns['shortcode'] = __('ショートコード', 'finelive-lp');
        $new_columns['display_period'] = __('表示期間', 'finelive-lp');
        $new_columns['click_count'] = __('クリック数', 'finelive-lp');
        
        // 既存の日付カラムを維持
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }

    /**
     * カスタムカラムの内容を出力
     *
     * @param string $column カラム名
     * @param int $post_id 投稿ID
     */
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'lp_id':
                echo '<strong>' . $post_id . '</strong>';
                break;
                
            case 'shortcode':
                $shortcode = '[finelive_lp id="' . $post_id . '"]';
                echo '<code onclick="this.select()" style="cursor:pointer;" title="' . esc_attr__('クリックして選択', 'finelive-lp') . '">' . esc_html($shortcode) . '</code>';
                break;
                
            case 'display_period':
                $data = get_post_meta($post_id, 'flp_lp_data', true);
                $start_date = isset($data['display_start_date']) ? $data['display_start_date'] : '';
                $end_date = isset($data['display_end_date']) ? $data['display_end_date'] : '';
                
                if (empty($start_date) && empty($end_date)) {
                    echo '<span style="color: #666;">' . __('制限なし', 'finelive-lp') . '</span>';
                } else {
                    $current_date = current_time('Y-m-d');
                    $status = '';
                    
                    if (!empty($start_date) && $current_date < $start_date) {
                        $status = '<span style="color: #d63638;">' . __('未開始', 'finelive-lp') . '</span>';
                    } elseif (!empty($end_date) && $current_date > $end_date) {
                        $status = '<span style="color: #d63638;">' . __('終了', 'finelive-lp') . '</span>';
                    } else {
                        $status = '<span style="color: #00a32a;">' . __('表示中', 'finelive-lp') . '</span>';
                    }
                    
                    echo $status . '<br>';
                    
                    if (!empty($start_date)) {
                        echo '<small>' . sprintf(__('開始: %s', 'finelive-lp'), $start_date) . '</small><br>';
                    }
                    if (!empty($end_date)) {
                        echo '<small>' . sprintf(__('終了: %s', 'finelive-lp'), $end_date) . '</small>';
                    }
                }
                break;
                
            case 'click_count':
                $click_data = get_option('flp_lp_click_data', array());
                $total_clicks = 0;
                
                if (isset($click_data[$post_id])) {
                    foreach ($click_data[$post_id] as $date_data) {
                        foreach ($date_data as $button_clicks) {
                            $total_clicks += $button_clicks;
                        }
                    }
                }
                
                if ($total_clicks > 0) {
                    echo '<strong>' . number_format($total_clicks) . '</strong>';
                    echo '<br><small><a href="' . admin_url('admin.php?page=flp_lp_clicks_report&lp_id=' . $post_id) . '">' . __('詳細', 'finelive-lp') . '</a></small>';
                } else {
                    echo '<span style="color: #666;">0</span>';
                }
                break;
        }
    }

    /**
     * ソート可能カラムを設定
     *
     * @param array $columns ソート可能カラム
     * @return array
     */
    public function set_sortable_columns($columns) {
        $columns['lp_id'] = 'ID';
        return $columns;
    }
}
