<?php
/**
 * ショートコード管理クラス
 */

if (!defined('ABSPATH')) exit;

class FLP_Shortcode {

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action('init', array($this, 'register_shortcodes'));
        
        // Gutenbergブロックエディターでのショートコード表示改善
        add_filter('pre_do_shortcode_tag', array($this, 'improve_editor_preview'), 10, 4);
    }

    /**
     * ショートコードの登録
     */
    public function register_shortcodes() {
        add_shortcode('finelive_lp', array($this, 'render_lp_shortcode'));
        
        // 短縮形も登録
        add_shortcode('flp', array($this, 'render_lp_shortcode'));
        
        /**
         * カスタムショートコード登録のアクション
         */
        do_action('flp_register_shortcodes', $this);
    }

    /**
     * [finelive_lp] ショートコードの処理
     *
     * @param array $atts ショートコード属性
     * @param string $content コンテンツ（使用しない）
     * @param string $tag ショートコードタグ
     * @return string 出力HTML
     */
    public function render_lp_shortcode($atts, $content = null, $tag = '') {
        // 属性のデフォルト値とマージ
        $atts = shortcode_atts(array(
            'id' => 0,
            'preview' => 'false', // プレビューモード
            'cache' => 'true',    // キャッシュ使用
        ), $atts, $tag);

        // LP IDの検証
        $lp_id = intval($atts['id']);
        if (!$lp_id) {
            return $this->get_error_message('invalid_id', __('LP IDが指定されていないか、無効です。', 'finelive-lp'));
        }

        // LPの存在確認
        $post = get_post($lp_id);
        if (!$post || $post->post_type !== 'flp_lp') {
            return $this->get_error_message('not_found', sprintf(__('LP ID %d が見つかりません。', 'finelive-lp'), $lp_id));
        }

        // プレビューモードの処理
        $is_preview = ($atts['preview'] === 'true') || (isset($_GET['flp_preview']) && current_user_can('edit_flp_lp', $lp_id));

        // キャッシュの使用可否
        $use_cache = ($atts['cache'] === 'true') && !$is_preview && !current_user_can('edit_posts');

        // キャッシュから取得を試行
        if ($use_cache) {
            $cached_output = $this->get_cached_output($lp_id, $atts);
            if ($cached_output !== false) {
                return $cached_output;
            }
        }

        // フロントエンドインスタンスから出力を生成
        $frontend = FLP()->frontend();
        if (!$frontend) {
            return $this->get_error_message('system_error', __('システムエラーが発生しました。', 'finelive-lp'));
        }

        // プレビューモードでない場合は表示期間チェック
        if (!$is_preview && !$frontend->is_lp_displayable($lp_id)) {
            // 管理者には情報を表示
            if (current_user_can('edit_flp_lp', $lp_id)) {
                return $this->get_admin_notice($lp_id, __('このLPは表示期間外です。', 'finelive-lp'));
            }
            return '';
        }

        // LP出力の生成
        $output = $frontend->render_lp($lp_id);
        
        // プレビューモードの場合は特別なマークを追加
        if ($is_preview && current_user_can('edit_flp_lp', $lp_id)) {
            $output = $this->wrap_preview_mode($output, $lp_id);
        }

        // キャッシュに保存
        if ($use_cache && !empty($output)) {
            $this->save_cached_output($lp_id, $atts, $output);
        }

        /**
         * ショートコード出力のフィルター
         *
         * @param string $output 出力HTML
         * @param int $lp_id LP ID
         * @param array $atts ショートコード属性
         */
        return apply_filters('flp_shortcode_output', $output, $lp_id, $atts);
    }

    /**
     * エラーメッセージの生成
     *
     * @param string $error_type エラータイプ
     * @param string $message エラーメッセージ
     * @return string エラーHTML
     */
    private function get_error_message($error_type, $message) {
        // 管理者のみにエラーメッセージを表示
        if (!current_user_can('manage_options')) {
            return '';
        }

        return sprintf(
            '<div class="flp-error flp-error-%s" style="padding: 10px; margin: 10px 0; background: #fff5f5; border: 1px solid #fed7d7; color: #742a2a; border-radius: 4px;">
                <strong>%s</strong> %s
                <div style="margin-top: 5px; font-size: 12px;">
                    <a href="%s">%s</a> | 
                    <a href="%s" target="_blank">%s</a>
                </div>
            </div>',
            esc_attr($error_type),
            __('[FineLive LP] エラー:', 'finelive-lp'),
            esc_html($message),
            admin_url('edit.php?post_type=flp_lp'),
            __('LP一覧', 'finelive-lp'),
            admin_url('edit.php?post_type=flp_lp&page=flp-lp-usage'),
            __('使い方ガイド', 'finelive-lp')
        );
    }

    /**
     * 管理者向け通知の生成
     *
     * @param int $lp_id LP ID
     * @param string $message 通知メッセージ
     * @return string 通知HTML
     */
    private function get_admin_notice($lp_id, $message) {
        if (!current_user_can('edit_flp_lp', $lp_id)) {
            return '';
        }

        return sprintf(
            '<div class="flp-admin-notice" style="padding: 10px; margin: 10px 0; background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; border-radius: 4px;">
                <strong>%s</strong> %s
                <div style="margin-top: 5px;">
                    <a href="%s" class="button button-small">%s</a>
                </div>
            </div>',
            sprintf(__('[LP ID: %d]', 'finelive-lp'), $lp_id),
            esc_html($message),
            get_edit_post_link($lp_id),
            __('編集', 'finelive-lp')
        );
    }

    /**
     * プレビューモードの出力をラップ
     *
     * @param string $output LP出力
     * @param int $lp_id LP ID
     * @return string ラップされた出力
     */
    private function wrap_preview_mode($output, $lp_id) {
        $preview_bar = sprintf(
            '<div class="flp-preview-bar" style="background: #0073aa; color: white; padding: 8px 12px; margin-bottom: 10px; border-radius: 4px; font-size: 14px; text-align: center;">
                <strong>%s</strong> - LP ID: %d
                <a href="%s" style="color: #b3d4fc; margin-left: 10px;">%s</a>
            </div>',
            __('プレビューモード', 'finelive-lp'),
            $lp_id,
            get_edit_post_link($lp_id),
            __('編集', 'finelive-lp')
        );

        return '<div class="flp-preview-wrapper">' . $preview_bar . $output . '</div>';
    }

    /**
     * キャッシュされた出力を取得
     *
     * @param int $lp_id LP ID
     * @param array $atts ショートコード属性
     * @return string|false キャッシュされた出力またはfalse
     */
    private function get_cached_output($lp_id, $atts) {
        $cache_key = $this->get_cache_key($lp_id, $atts);
        return get_transient($cache_key);
    }

    /**
     * 出力をキャッシュに保存
     *
     * @param int $lp_id LP ID
     * @param array $atts ショートコード属性
     * @param string $output 出力HTML
     */
    private function save_cached_output($lp_id, $atts, $output) {
        $cache_key = $this->get_cache_key($lp_id, $atts);
        $expiration = apply_filters('flp_cache_expiration', HOUR_IN_SECONDS, $lp_id);
        
        set_transient($cache_key, $output, $expiration);
    }

    /**
     * キャッシュキーの生成
     *
     * @param int $lp_id LP ID
     * @param array $atts ショートコード属性
     * @return string キャッシュキー
     */
    private function get_cache_key($lp_id, $atts) {
        // LP最終更新時刻を含めてキーを生成
        $post_modified = get_post_modified_time('U', true, $lp_id);
        $atts_hash = md5(serialize($atts));
        
        return 'flp_shortcode_' . $lp_id . '_' . $post_modified . '_' . $atts_hash;
    }

    /**
     * LPのキャッシュをクリア
     *
     * @param int $lp_id LP ID
     */
    public static function clear_lp_cache($lp_id) {
        global $wpdb;
        
        // 該当LPのキャッシュを全て削除
        $cache_pattern = 'flp_shortcode_' . $lp_id . '_';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_' . $cache_pattern) . '%'
        ));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_timeout_' . $cache_pattern) . '%'
        ));

        /**
         * LPキャッシュクリア後のアクション
         *
         * @param int $lp_id LP ID
         */
        do_action('flp_cleared_lp_cache', $lp_id);
    }

    /**
     * 全LPのキャッシュをクリア
     */
    public static function clear_all_cache() {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_flp_shortcode_%' OR option_name LIKE '_transient_timeout_flp_shortcode_%'"
        );

        /**
         * 全キャッシュクリア後のアクション
         */
        do_action('flp_cleared_all_cache');
    }

    /**
     * ブロックエディターでのプレビュー改善
     *
     * @param false|string $return ショートコード出力
     * @param string $tag ショートコードタグ
     * @param array $attr 属性
     * @param array $m マッチした内容
     * @return false|string
     */
    public function improve_editor_preview($return, $tag, $attr, $m) {
        // 管理画面のブロックエディターでのみ動作
        if (!is_admin() || $tag !== 'finelive_lp') {
            return $return;
        }

        // REST APIリクエスト（Gutenberg）の場合
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $lp_id = intval($attr['id'] ?? 0);
            
            if (!$lp_id) {
                return '<div style="padding: 20px; background: #f0f0f0; border: 1px dashed #ccc; text-align: center; color: #666;">' . 
                       __('[finelive_lp] LP IDを指定してください', 'finelive-lp') . '</div>';
            }

            $post = get_post($lp_id);
            if (!$post || $post->post_type !== 'flp_lp') {
                return '<div style="padding: 20px; background: #fff5f5; border: 1px dashed #ff6b6b; text-align: center; color: #d63031;">' . 
                       sprintf(__('[finelive_lp] LP ID %d が見つかりません', 'finelive-lp'), $lp_id) . '</div>';
            }

            // エディター用の簡易プレビューを生成
            return $this->generate_editor_preview($lp_id, $post);
        }

        return $return;
    }

    /**
     * エディター用の簡易プレビュー生成
     *
     * @param int $lp_id LP ID
     * @param WP_Post $post 投稿オブジェクト
     * @return string プレビューHTML
     */
    private function generate_editor_preview($lp_id, $post) {
        $data = FLP_Meta_Boxes::get_lp_data($lp_id);
        $image_count = count($data['static_images']);
        $slider_count = count($data['slider_images']);

        // ステータス確認
        $current_date = current_time('Y-m-d');
        $is_active = true;
        $status_message = '';

        if (!empty($data['display_start_date']) && $current_date < $data['display_start_date']) {
            $is_active = false;
            $status_message = sprintf(__('開始予定: %s', 'finelive-lp'), $data['display_start_date']);
        } elseif (!empty($data['display_end_date']) && $current_date > $data['display_end_date']) {
            $is_active = false;
            $status_message = sprintf(__('終了日: %s', 'finelive-lp'), $data['display_end_date']);
        }

        $status_color = $is_active ? '#00a32a' : '#d63638';
        $status_text = $is_active ? __('表示中', 'finelive-lp') : __('非表示', 'finelive-lp');

        return sprintf(
            '<div style="border: 1px solid #e0e0e0; border-radius: 4px; padding: 15px; background: white; margin: 10px 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h4 style="margin: 0; color: #333;">📄 %s</h4>
                    <span style="color: %s; font-weight: bold;">● %s</span>
                </div>
                <div style="color: #666; font-size: 14px;">
                    <div>ID: <strong>%d</strong></div>
                    <div>画像: %d枚 | スライダー: %d枚</div>
                    <div>ボタン: <span style="background: %s; color: %s; padding: 2px 8px; border-radius: 3px; font-size: 12px;">%s</span></div>
                    %s
                </div>
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #f0f0f0;">
                    <a href="%s" target="_blank" style="color: #0073aa; text-decoration: none;">編集 →</a>
                </div>
            </div>',
            esc_html($post->post_title),
            $status_color,
            $status_text,
            $lp_id,
            $image_count,
            $slider_count,
            esc_attr($data['btn_bg_color']),
            esc_attr($data['btn_text_color']),
            esc_html($data['button_text']),
            $status_message ? '<div style="margin-top: 5px; font-style: italic;">' . esc_html($status_message) . '</div>' : '',
            get_edit_post_link($lp_id)
        );
    }

    /**
     * ショートコード属性のサニタイズ
     *
     * @param array $atts 生の属性
     * @return array サニタイズされた属性
     */
    public function sanitize_shortcode_atts($atts) {
        $sanitized = array();

        // ID
        if (isset($atts['id'])) {
            $sanitized['id'] = absint($atts['id']);
        }

        // プレビューモード
        if (isset($atts['preview'])) {
            $sanitized['preview'] = in_array($atts['preview'], array('true', '1')) ? 'true' : 'false';
        }

        // キャッシュ使用
        if (isset($atts['cache'])) {
            $sanitized['cache'] = in_array($atts['cache'], array('false', '0')) ? 'false' : 'true';
        }

        return $sanitized;
    }

    /**
     * LP保存時にキャッシュをクリア
     */
    public static function init_cache_hooks() {
        add_action('save_post', function($post_id) {
            if (get_post_type($post_id) === 'flp_lp') {
                self::clear_lp_cache($post_id);
            }
        });

        add_action('delete_post', function($post_id) {
            if (get_post_type($post_id) === 'flp_lp') {
                self::clear_lp_cache($post_id);
            }
        });
    }
}

// キャッシュフックの初期化
FLP_Shortcode::init_cache_hooks();
