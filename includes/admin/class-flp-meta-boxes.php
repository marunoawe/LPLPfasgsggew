<?php
/**
 * メタボックス管理クラス
 */

if (!defined('ABSPATH')) exit;

class FLP_Meta_Boxes {

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'), 10, 2);
    }

    /**
     * メタボックスを追加
     */
    public function add_meta_boxes() {
        // LP設定メタボックス
        add_meta_box(
            'flp_lp_settings',
            __('LP設定', 'finelive-lp'),
            array($this, 'render_lp_settings_meta_box'),
            'flp_lp',
            'normal',
            'high'
        );

        // 使い方ガイドメタボックス
        add_meta_box(
            'flp_usage_guide',
            __('使い方ガイド', 'finelive-lp'),
            array($this, 'render_usage_guide_meta_box'),
            'flp_lp',
            'side',
            'high'
        );

        // ショートコード情報メタボックス
        add_meta_box(
            'flp_shortcode_info',
            __('ショートコード情報', 'finelive-lp'),
            array($this, 'render_shortcode_info_meta_box'),
            'flp_lp',
            'side',
            'default'
        );
    }

    /**
     * LP設定メタボックスの描画
     *
     * @param WP_Post $post 投稿オブジェクト
     */
    public function render_lp_settings_meta_box($post) {
        // nonce フィールドの追加
        wp_nonce_field('flp_lp_save_meta', 'flp_lp_nonce');

        // 保存されているデータを取得
        $data = get_post_meta($post->ID, 'flp_lp_data', true);
        $data = is_array($data) ? $data : array();

        // デフォルト値の設定
        $defaults = $this->get_default_values();
        $data = wp_parse_args($data, $defaults);

        // テンプレートファイルを読み込み
        // 修正: ファイルパスを正しく結合
        $template_file = FLP_ADMIN_DIR . 'views/meta-box-lp-settings.php';
        
        // ファイルの存在確認
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            // エラーハンドリング
            $error_message = sprintf(
                __('テンプレートファイルが見つかりません: %s', 'finelive-lp'),
                $template_file
            );
            
            // 管理者にエラーメッセージを表示
            if (current_user_can('manage_options')) {
                echo '<div class="notice notice-error"><p>' . esc_html($error_message) . '</p></div>';
                error_log('FLP Error: ' . $error_message);
            }
            
            // フォールバック: 基本的なフォームを表示
            $this->render_fallback_form($data);
        }
    }

    /**
     * フォールバック用のフォーム表示
     *
     * @param array $data LP設定データ
     */
    private function render_fallback_form($data) {
        ?>
        <div class="flp-meta-box-content">
            <div class="notice notice-warning">
                <p><?php _e('テンプレートファイルの読み込みに失敗しました。基本的な設定のみ表示しています。', 'finelive-lp'); ?></p>
            </div>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="flp_button_text"><?php _e('ボタンテキスト', 'finelive-lp'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="flp_button_text" 
                               name="flp_lp_data[button_text]" 
                               value="<?php echo esc_attr($data['button_text']); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="flp_button_url"><?php _e('ボタンURL', 'finelive-lp'); ?></label>
                    </th>
                    <td>
                        <input type="url" 
                               id="flp_button_url" 
                               name="flp_lp_data[button_url]" 
                               value="<?php echo esc_attr($data['button_url']); ?>" 
                               class="large-text">
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * 使い方ガイドメタボックスの描画
     *
     * @param WP_Post $post 投稿オブジェクト
     */
    public function render_usage_guide_meta_box($post) {
        ?>
        <div class="flp-usage-guide-box">
            <div style="padding: 15px; border: 1px solid #007cba; background: #f0f6fc; margin-bottom: 15px; border-radius: 4px;">
                <h4 style="margin: 0 0 10px; color: #1d2327;">
                    <span class="dashicons dashicons-info" style="color: #007cba;"></span>
                    <?php _e('使い方に困ったら？', 'finelive-lp'); ?>
                </h4>
                <p style="margin: 0 0 10px;">
                    <?php _e('基本的な設定方法やショートコードの使い方は、以下のボタンから確認できます。', 'finelive-lp'); ?>
                </p>
                <p style="margin: 0;">
                    <a href="<?php echo admin_url('edit.php?post_type=flp_lp&page=flp-lp-usage'); ?>" 
                       class="button button-primary" 
                       target="_blank">
                        <span class="dashicons dashicons-book-alt" style="vertical-align: middle;"></span>
                        <?php _e('使い方ガイドを見る', 'finelive-lp'); ?>
                    </a>
                </p>
            </div>
            
            <div class="flp-quick-tips">
                <h4><?php _e('クイックヒント', 'finelive-lp'); ?></h4>
                <ul style="list-style: disc; padding-left: 20px;">
                    <li><?php _e('画像は縦に並んで表示されます', 'finelive-lp'); ?></li>
                    <li><?php _e('各画像の下にボタンやスライダーを個別設定可能', 'finelive-lp'); ?></li>
                    <li><?php _e('表示期間を設定して自動でLP表示を制御', 'finelive-lp'); ?></li>
                    <li><?php _e('クリック数は自動的に測定・記録されます', 'finelive-lp'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * ショートコード情報メタボックスの描画
     *
     * @param WP_Post $post 投稿オブジェクト
     */
    public function render_shortcode_info_meta_box($post) {
        $shortcode = '[finelive_lp id="' . $post->ID . '"]';
        ?>
        <div class="flp-shortcode-info">
            <p><?php _e('このLPを表示するには、以下のショートコードを投稿や固定ページに貼り付けてください：', 'finelive-lp'); ?></p>
            
            <div style="background: #f0f0f0; padding: 10px; border: 1px solid #ddd; font-family: monospace; margin: 10px 0;">
                <code style="font-size: 13px; user-select: all;"><?php echo esc_html($shortcode); ?></code>
            </div>
            
            <p>
                <button type="button" class="button button-small" onclick="flpCopyShortcode(this)" data-shortcode="<?php echo esc_attr($shortcode); ?>">
                    <span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span>
                    <?php _e('コピー', 'finelive-lp'); ?>
                </button>
            </p>
            
            <script>
            function flpCopyShortcode(button) {
                var shortcode = button.getAttribute('data-shortcode');
                var temp = document.createElement('textarea');
                temp.value = shortcode;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
                
                button.innerHTML = '<span class="dashicons dashicons-yes" style="vertical-align: middle;"></span> <?php echo esc_js(__('コピーしました！', 'finelive-lp')); ?>';
                
                setTimeout(function() {
                    button.innerHTML = '<span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span> <?php echo esc_js(__('コピー', 'finelive-lp')); ?>';
                }, 2000);
            }
            </script>
            
            <?php if ($post->post_status === 'publish'): ?>
                <?php $this->render_click_stats($post->ID); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * クリック統計の表示
     *
     * @param int $post_id 投稿ID
     */
    private function render_click_stats($post_id) {
        $click_tracker = new FLP_Click_Tracking();
        $today_clicks = $click_tracker->get_clicks_count($post_id, 'today');
        $week_clicks = $click_tracker->get_clicks_count($post_id, 'week');
        $total_clicks = $click_tracker->get_clicks_count($post_id, 'all');
        
        ?>
        <hr style="margin: 15px 0;">
        <h4 style="margin: 10px 0;"><?php _e('クリック統計', 'finelive-lp'); ?></h4>
        <ul style="margin: 0; padding: 0; list-style: none;">
            <li><?php printf(__('今日のクリック: %s', 'finelive-lp'), '<strong>' . number_format($today_clicks) . '</strong>'); ?></li>
            <li><?php printf(__('7日間のクリック数: %s', 'finelive-lp'), '<strong>' . number_format($week_clicks) . '</strong>'); ?></li>
            <li><?php printf(__('総クリック数: %s', 'finelive-lp'), '<strong>' . number_format($total_clicks) . '</strong>'); ?></li>
        </ul>
        
        <?php if ($total_clicks > 0): ?>
        <p>
            <a href="<?php echo admin_url('admin.php?page=flp_lp_clicks_report&lp_id=' . $post_id); ?>" class="button button-small">
                <?php _e('詳細レポートを見る', 'finelive-lp'); ?>
            </a>
        </p>
        <?php endif; ?>
        <?php
    }

    /**
     * メタボックスのデータ保存
     *
     * @param int $post_id 投稿ID
     * @param WP_Post $post 投稿オブジェクト
     */
    public function save_meta_boxes($post_id, $post) {
        // 基本チェック
        if (!$this->should_save_meta($post_id, $post)) {
            return;
        }

        // フォームデータの取得と検証
        $input_data = isset($_POST['flp_lp_data']) ? $_POST['flp_lp_data'] : array();
        
        // データのサニタイズと保存
        $sanitized_data = $this->sanitize_meta_data($input_data);
        update_post_meta($post_id, 'flp_lp_data', $sanitized_data);

        /**
         * LP データ保存後のアクション
         *
         * @param int $post_id 投稿ID
         * @param array $sanitized_data 保存されたデータ
         * @param array $input_data 元の入力データ
         */
        do_action('flp_after_save_lp_data', $post_id, $sanitized_data, $input_data);
    }

    /**
     * メタデータ保存の可否を判定
     *
     * @param int $post_id 投稿ID
     * @param WP_Post $post 投稿オブジェクト
     * @return bool
     */
    private function should_save_meta($post_id, $post) {
        // 投稿タイプチェック
        if ($post->post_type !== 'flp_lp') {
            return false;
        }

        // nonce チェック
        if (!isset($_POST['flp_lp_nonce']) || !wp_verify_nonce($_POST['flp_lp_nonce'], 'flp_lp_save_meta')) {
            return false;
        }

        // 自動保存チェック
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        // 権限チェック
        if (!current_user_can('edit_post', $post_id)) {
            return false;
        }

        return true;
    }

    /**
     * メタデータのサニタイズ
     *
     * @param array $input_data 入力データ
     * @return array サニタイズされたデータ
     */
    private function sanitize_meta_data($input_data) {
        $sanitized = array();

        // 基本設定のサニタイズ
        $sanitized['button_text'] = sanitize_text_field($input_data['button_text'] ?? '応募はこちら');
        $sanitized['button_url'] = esc_url_raw($input_data['button_url'] ?? '');

        // 静的画像のサニタイズ
        $sanitized['static_images'] = $this->sanitize_static_images($input_data);

        // スライダー画像のサニタイズ
        $sanitized['slider_images'] = $this->sanitize_slider_images($input_data);

        // スライダー設定のサニタイズ
        $sanitized['slider_interval'] = absint($input_data['slider_interval'] ?? 4000);
        $sanitized['slider_interval'] = max(1000, min(10000, $sanitized['slider_interval'])); // 1-10秒の範囲に制限

        // 表示期間のサニタイズ
        $sanitized['display_start_date'] = $this->sanitize_date($input_data['display_start_date'] ?? '');
        $sanitized['display_end_date'] = $this->sanitize_date($input_data['display_end_date'] ?? '');

        // ボタンデザイン設定のサニタイズ
        $sanitized['btn_bg_color'] = $this->sanitize_color($input_data['btn_bg_color'] ?? '#ff4081');
        $sanitized['btn_text_color'] = $this->sanitize_color($input_data['btn_text_color'] ?? '#ffffff');
        $sanitized['btn_padding_tb'] = absint($input_data['btn_padding_tb'] ?? 15);
        $sanitized['btn_padding_lr'] = absint($input_data['btn_padding_lr'] ?? 30);
        $sanitized['btn_border_radius'] = absint($input_data['btn_border_radius'] ?? 5);

        /**
         * メタデータサニタイズのフィルター
         *
         * @param array $sanitized サニタイズ済みデータ
         * @param array $input_data 元の入力データ
         */
        return apply_filters('flp_sanitize_meta_data', $sanitized, $input_data);
    }

    /**
     * 静的画像データのサニタイズ
     *
     * @param array $input_data 入力データ
     * @return array サニタイズされた静的画像データ
     */
    private function sanitize_static_images($input_data) {
        $static_images = array();
        
        if (!empty($input_data['static_url']) && is_array($input_data['static_url'])) {
            foreach ($input_data['static_url'] as $index => $url) {
                $url = esc_url_raw($url);
                if (!empty($url)) {
                    $static_images[] = array(
                        'url' => $url,
                        'show_button' => isset($input_data['show_button'][$index]) ? 1 : 0,
                        'show_slider' => isset($input_data['show_slider'][$index]) ? 1 : 0,
                    );
                }
            }
        }
        
        return $static_images;
    }

    /**
     * スライダー画像データのサニタイズ
     *
     * @param array $input_data 入力データ
     * @return array サニタイズされたスライダー画像データ
     */
    private function sanitize_slider_images($input_data) {
        $slider_images = array();
        
        if (!empty($input_data['slider_url']) && is_array($input_data['slider_url'])) {
            foreach ($input_data['slider_url'] as $url) {
                $url = esc_url_raw($url);
                if (!empty($url)) {
                    $slider_images[] = $url;
                }
            }
        }
        
        return $slider_images;
    }

    /**
     * 日付のサニタイズ
     *
     * @param string $date 日付文字列
     * @return string サニタイズされた日付
     */
    private function sanitize_date($date) {
        if (empty($date)) {
            return '';
        }
        
        // Y-m-d形式の日付かチェック
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $timestamp = strtotime($date);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }
        
        return '';
    }

    /**
     * カラーコードのサニタイズ
     *
     * @param string $color カラーコード
     * @return string サニタイズされたカラーコード
     */
    private function sanitize_color($color) {
        if (empty($color)) {
            return '';
        }
        
        // HEXカラーコードの形式チェック
        if (preg_match('/^#([a-f0-9]{3}){1,2}$/i', $color)) {
            return strtolower($color);
        }
        
        return '';
    }

    /**
     * デフォルト値を取得
     *
     * @return array デフォルト値の配列
     */
    private function get_default_values() {
        return array(
            'button_text' => __('応募はこちら', 'finelive-lp'),
            'button_url' => '',
            'static_images' => array(),
            'slider_images' => array(),
            'slider_interval' => 4000,
            'display_start_date' => '',
            'display_end_date' => '',
            'btn_bg_color' => '#ff4081',
            'btn_text_color' => '#ffffff',
            'btn_padding_tb' => 15,
            'btn_padding_lr' => 30,
            'btn_border_radius' => 5,
        );
    }

    /**
     * 保存された設定データを取得
     *
     * @param int $post_id 投稿ID
     * @return array 設定データ
     */
    public static function get_lp_data($post_id) {
        $data = get_post_meta($post_id, 'flp_lp_data', true);
        
        if (!is_array($data)) {
            $data = array();
        }
        
        $instance = new self();
        return wp_parse_args($data, $instance->get_default_values());
    }

    /**
     * 特定の設定値を取得
     *
     * @param int $post_id 投稿ID
     * @param string $key 設定キー
     * @param mixed $default デフォルト値
     * @return mixed 設定値
     */
    public static function get_lp_setting($post_id, $key, $default = null) {
        $data = self::get_lp_data($post_id);
        return isset($data[$key]) ? $data[$key] : $default;
    }
}