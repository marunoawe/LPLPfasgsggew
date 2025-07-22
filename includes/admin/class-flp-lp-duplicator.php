<?php
/**
 * LP複製管理クラス
 */

if (!defined('ABSPATH')) exit;

class FLP_LP_Duplicator {

    /**
     * コンストラクタ
     */
    public function __construct() {
        // 投稿一覧の行アクションに複製リンクを追加
        add_filter('post_row_actions', array($this, 'add_duplicate_row_action'), 10, 2);
        
        // 複製処理のAJAXアクション
        add_action('admin_action_flp_duplicate_lp', array($this, 'handle_duplicate_action'));
    }

    /**
     * 投稿一覧に複製リンクを追加
     *
     * @param array $actions 既存のアクション
     * @param WP_Post $post 投稿オブジェクト
     * @return array
     */
    public function add_duplicate_row_action($actions, $post) {
        if ($post->post_type === 'flp_lp' && current_user_can('edit_flp_lp', $post->ID)) {
            $duplicate_url = wp_nonce_url(
                admin_url('admin.php?action=flp_duplicate_lp&post=' . $post->ID),
                'flp_duplicate_nonce'
            );
            
            $actions['duplicate'] = sprintf(
                '<a href="%s" title="%s" onclick="return confirm(\'%s\')">%s</a>',
                esc_url($duplicate_url),
                esc_attr__('このLPを複製', 'finelive-lp'),
                esc_js(__('このLPを複製しますか？', 'finelive-lp')),
                __('複製', 'finelive-lp')
            );
        }
        
        return $actions;
    }

    /**
     * 複製処理のハンドル
     */
    public function handle_duplicate_action() {
        // セキュリティチェック
        if (!isset($_GET['post']) || !isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'flp_duplicate_nonce')) {
            wp_die(__('セキュリティチェックに失敗しました。', 'finelive-lp'));
        }

        $source_post_id = absint($_GET['post']);
        
        try {
            $new_post_id = $this->duplicate_lp($source_post_id);
            
            // 成功時は編集画面にリダイレクト
            wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id . '&flp_duplicated=1'));
            exit;
            
        } catch (Exception $e) {
            wp_die(__('複製中にエラーが発生しました: ', 'finelive-lp') . $e->getMessage());
        }
    }

    /**
     * LPの複製実行
     *
     * @param int $source_post_id 複製元の投稿ID
     * @return int 新しい投稿のID
     * @throws Exception
     */
    public function duplicate_lp($source_post_id) {
        // 元投稿の取得と検証
        $source_post = get_post($source_post_id);
        
        if (empty($source_post)) {
            throw new Exception(__('複製するLPが見つかりません。', 'finelive-lp'));
        }
        
        if ($source_post->post_type !== 'flp_lp') {
            throw new Exception(__('複製できるのはLPタイプの投稿のみです。', 'finelive-lp'));
        }
        
        // 権限チェック
        if (!current_user_can('edit_flp_lp', $source_post_id)) {
            throw new Exception(__('このLPを複製する権限がありません。', 'finelive-lp'));
        }

        // 新しい投稿データの準備
        $new_post_data = $this->prepare_duplicate_post_data($source_post);
        
        // 新しい投稿を作成
        $new_post_id = wp_insert_post($new_post_data, true);
        
        if (is_wp_error($new_post_id)) {
            throw new Exception(__('新しい投稿の作成に失敗しました: ', 'finelive-lp') . $new_post_id->get_error_message());
        }

        // メタデータのコピー
        $this->copy_post_meta($source_post_id, $new_post_id);

        // タクソノミーのコピー（将来的にカテゴリなどを追加する場合）
        $this->copy_post_taxonomies($source_post_id, $new_post_id);

        // カスタムフィールドのコピー（WordPress標準以外）
        $this->copy_custom_fields($source_post_id, $new_post_id);

        /**
         * LP複製完了後のアクション
         *
         * @param int $new_post_id 新しい投稿のID
         * @param int $source_post_id 複製元の投稿ID
         * @param WP_Post $source_post 複製元の投稿オブジェクト
         */
        do_action('flp_lp_duplicated', $new_post_id, $source_post_id, $source_post);

        return $new_post_id;
    }

    /**
     * 複製用の投稿データを準備
     *
     * @param WP_Post $source_post 複製元の投稿
     * @return array 新しい投稿のデータ
     */
    private function prepare_duplicate_post_data($source_post) {
        // 複製回数を検出してタイトルに追加
        $copy_suffix = $this->get_copy_suffix($source_post->post_title);
        
        return array(
            'post_title'     => $source_post->post_title . $copy_suffix,
            'post_content'   => $source_post->post_content,
            'post_excerpt'   => $source_post->post_excerpt,
            'post_status'    => 'draft', // 複製は常に下書きとして作成
            'post_type'      => $source_post->post_type,
            'post_author'    => get_current_user_id(), // 複製実行者を作成者にする
            'post_parent'    => $source_post->post_parent,
            'menu_order'     => $source_post->menu_order,
            'comment_status' => $source_post->comment_status,
            'ping_status'    => $source_post->ping_status,
        );
    }

    /**
     * コピーサフィックスの生成
     *
     * @param string $original_title 元のタイトル
     * @return string コピーサフィックス
     */
    private function get_copy_suffix($original_title) {
        // 既存の「のコピー」を検出
        $copy_pattern = '/^(.+?)(?:\s*のコピー(?:\s*(\d+))?)?$/u';
        
        if (preg_match($copy_pattern, $original_title, $matches)) {
            $base_title = $matches[1];
            $current_number = isset($matches[2]) ? intval($matches[2]) : 0;
        } else {
            $base_title = $original_title;
            $current_number = 0;
        }

        // 同じベースタイトルの投稿数をチェック
        $existing_posts = get_posts(array(
            'post_type' => 'flp_lp',
            'post_status' => array('publish', 'draft', 'private'),
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_flp_original_title',
                    'value' => $base_title,
                    'compare' => '='
                ),
            )
        ));

        // 新しい番号を決定
        $max_number = $current_number;
        foreach ($existing_posts as $post) {
            if (preg_match('/のコピー(?:\s*(\d+))?$/', $post->post_title, $matches)) {
                $number = isset($matches[1]) ? intval($matches[1]) : 1;
                $max_number = max($max_number, $number);
            }
        }

        $next_number = $max_number + 1;
        
        if ($next_number <= 1) {
            return ' のコピー';
        } else {
            return ' のコピー ' . $next_number;
        }
    }

    /**
     * メタデータのコピー
     *
     * @param int $source_post_id 複製元の投稿ID
     * @param int $new_post_id 新しい投稿のID
     */
    private function copy_post_meta($source_post_id, $new_post_id) {
        global $wpdb;

        // 全てのメタデータを取得
        $meta_data = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
            $source_post_id
        ));

        // 除外するメタキー
        $excluded_meta_keys = apply_filters('flp_duplicate_excluded_meta_keys', array(
            '_edit_last',
            '_edit_lock',
            '_wp_old_slug',
            '_wp_old_date',
        ));

        foreach ($meta_data as $meta) {
            // 除外対象のメタキーはスキップ
            if (in_array($meta->meta_key, $excluded_meta_keys)) {
                continue;
            }

            // メタデータを複製
            update_post_meta($new_post_id, $meta->meta_key, maybe_unserialize($meta->meta_value));
        }

        // 元のタイトルを記録（複製回数計算用）
        $original_title = get_the_title($source_post_id);
        $copy_pattern = '/^(.+?)(?:\s*のコピー(?:\s*\d+)?)?$/u';
        if (preg_match($copy_pattern, $original_title, $matches)) {
            update_post_meta($new_post_id, '_flp_original_title', $matches[1]);
        } else {
            update_post_meta($new_post_id, '_flp_original_title', $original_title);
        }

        // 複製元の情報を記録
        update_post_meta($new_post_id, '_flp_duplicated_from', $source_post_id);
        update_post_meta($new_post_id, '_flp_duplicated_at', current_time('mysql'));

        /**
         * メタデータコピー後のアクション
         *
         * @param int $new_post_id 新しい投稿のID
         * @param int $source_post_id 複製元の投稿ID
         */
        do_action('flp_after_copy_post_meta', $new_post_id, $source_post_id);
    }

    /**
     * タクソノミーのコピー
     *
     * @param int $source_post_id 複製元の投稿ID
     * @param int $new_post_id 新しい投稿のID
     */
    private function copy_post_taxonomies($source_post_id, $new_post_id) {
        // 投稿タイプに関連付けられたタクソノミーを取得
        $taxonomies = get_object_taxonomies('flp_lp', 'names');
        
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($source_post_id, $taxonomy, array('fields' => 'ids'));
            
            if (!is_wp_error($terms) && !empty($terms)) {
                wp_set_object_terms($new_post_id, $terms, $taxonomy);
            }
        }
    }

    /**
     * カスタムフィールドのコピー（プラグイン固有の処理）
     *
     * @param int $source_post_id 複製元の投稿ID
     * @param int $new_post_id 新しい投稿のID
     */
    private function copy_custom_fields($source_post_id, $new_post_id) {
        // LP固有のデータをコピー
        $lp_data = get_post_meta($source_post_id, 'flp_lp_data', true);
        
        if (is_array($lp_data)) {
            // 特定のフィールドをリセット（複製で引き継がない設定）
            $reset_fields = apply_filters('flp_duplicate_reset_fields', array(
                'display_start_date',
                'display_end_date',
            ));

            foreach ($reset_fields as $field) {
                if (isset($lp_data[$field])) {
                    $lp_data[$field] = '';
                }
            }

            // LP設定データを保存
            update_post_meta($new_post_id, 'flp_lp_data', $lp_data);
        }

        /**
         * カスタムフィールドコピー後のアクション
         *
         * @param int $new_post_id 新しい投稿のID
         * @param int $source_post_id 複製元の投稿ID
         * @param array $lp_data コピーされたLPデータ
         */
        do_action('flp_after_copy_custom_fields', $new_post_id, $source_post_id, $lp_data);
    }

    /**
     * 一括複製（管理画面の一括操作用）
     *
     * @param array $post_ids 複製する投稿IDの配列
     * @return array 結果の配列 (成功/失敗)
     */
    public function bulk_duplicate($post_ids) {
        $results = array(
            'success' => array(),
            'errors' => array(),
        );

        foreach ($post_ids as $post_id) {
            try {
                $new_post_id = $this->duplicate_lp($post_id);
                $results['success'][] = array(
                    'source_id' => $post_id,
                    'new_id' => $new_post_id,
                    'title' => get_the_title($new_post_id)
                );
            } catch (Exception $e) {
                $results['errors'][] = array(
                    'source_id' => $post_id,
                    'error' => $e->getMessage()
                );
            }
        }

        return $results;
    }

    /**
     * 複製履歴の取得
     *
     * @param int $post_id 投稿ID
     * @return array 複製履歴
     */
    public function get_duplicate_history($post_id) {
        $history = array(
            'duplicated_from' => null,
            'duplicated_to' => array(),
            'duplicate_count' => 0,
        );

        // この投稿が複製されたものかチェック
        $duplicated_from = get_post_meta($post_id, '_flp_duplicated_from', true);
        if ($duplicated_from) {
            $history['duplicated_from'] = array(
                'id' => $duplicated_from,
                'title' => get_the_title($duplicated_from),
                'date' => get_post_meta($post_id, '_flp_duplicated_at', true),
            );
        }

        // この投稿から複製された投稿を検索
        $duplicated_posts = get_posts(array(
            'post_type' => 'flp_lp',
            'post_status' => array('publish', 'draft', 'private'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_flp_duplicated_from',
                    'value' => $post_id,
                    'compare' => '='
                )
            )
        ));

        foreach ($duplicated_posts as $duplicate) {
            $history['duplicated_to'][] = array(
                'id' => $duplicate->ID,
                'title' => $duplicate->post_title,
                'status' => $duplicate->post_status,
                'date' => get_post_meta($duplicate->ID, '_flp_duplicated_at', true),
            );
        }

        $history['duplicate_count'] = count($history['duplicated_to']);

        return $history;
    }

    /**
     * 複製可能かどうかをチェック
     *
     * @param int $post_id 投稿ID
     * @return bool|WP_Error 複製可能な場合はtrue、そうでなければWP_Error
     */
    public function can_duplicate($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', __('投稿が見つかりません。', 'finelive-lp'));
        }

        if ($post->post_type !== 'flp_lp') {
            return new WP_Error('invalid_post_type', __('LPタイプの投稿のみ複製できます。', 'finelive-lp'));
        }

        if (!current_user_can('edit_flp_lp', $post_id)) {
            return new WP_Error('insufficient_permissions', __('この投稿を複製する権限がありません。', 'finelive-lp'));
        }

        /**
         * 複製可能性の追加チェック
         *
         * @param bool|WP_Error $can_duplicate 複製可能かどうか
         * @param int $post_id 投稿ID
         * @param WP_Post $post 投稿オブジェクト
         */
        return apply_filters('flp_can_duplicate_lp', true, $post_id, $post);
    }

    /**
     * 複製統計の取得
     *
     * @return array 複製に関する統計
     */
    public function get_duplicate_stats() {
        global $wpdb;

        $stats = array(
            'total_duplicates' => 0,
            'most_duplicated' => array(),
            'recent_duplicates' => array(),
        );

        // 複製投稿の総数
        $stats['total_duplicates'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_flp_duplicated_from'"
        );

        // 最も複製されているLP
        $most_duplicated = $wpdb->get_results(
            "SELECT meta_value as source_id, COUNT(*) as duplicate_count 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_flp_duplicated_from' 
             GROUP BY meta_value 
             ORDER BY duplicate_count DESC 
             LIMIT 5"
        );

        foreach ($most_duplicated as $item) {
            $stats['most_duplicated'][] = array(
                'id' => $item->source_id,
                'title' => get_the_title($item->source_id),
                'duplicate_count' => $item->duplicate_count,
            );
        }

        // 最近の複製
        $recent_duplicates = $wpdb->get_results(
            "SELECT post_id, meta_value as source_id 
             FROM {$wpdb->postmeta} pm
             WHERE meta_key = '_flp_duplicated_from'
             ORDER BY pm.meta_id DESC
             LIMIT 10"
        );

        foreach ($recent_duplicates as $item) {
            $stats['recent_duplicates'][] = array(
                'new_id' => $item->post_id,
                'new_title' => get_the_title($item->post_id),
                'source_id' => $item->source_id,
                'source_title' => get_the_title($item->source_id),
                'date' => get_post_meta($item->post_id, '_flp_duplicated_at', true),
            );
        }

        return $stats;
    }
}
