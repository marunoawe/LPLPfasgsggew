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
        include FLP_ADMIN_DIR . 'views/meta-box-lp-settings.php';
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
            <p>
                <strong><?php _e('このLPを表示するショートコード:', 'finelive-lp'); ?></strong>
            </p>
            <p>
                <input type="text" 
                       value="<?php echo esc_attr($shortcode); ?>" 
                       readonly 
                       onclick="this.select()" 
                       style="width: 100%; font-family: monospace; background: #f1f1f1; border: 1px solid #ccd0d4; padding: 6px 8px; border-radius: 3px;"
                       title="<?php esc_attr_e('クリックして選択', 'finelive-lp'); ?>">
            </p>
            <p class="description">
                <?php _e('このショートコードを投稿や固定ページにコピー&ペーストしてLPを表示できます。', 'finelive-lp'); ?>
            </p>
            
            <?php if ($post->ID): ?>
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                <p><strong><?php _e('統計情報:', 'finelive-lp'); ?></strong></p>
                <?php $this->display_lp_statistics($post->ID); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * LP統計情報の表示
     *
     * @param int $post_id 投稿ID
     */
    private function display_lp_statistics($post_id) {
        $click_data = get_option('flp_lp_click_data', array());
        $total_clicks = 0;
        $today_clicks = 0;
        $week_clicks = 0;
        
        $current_date = date('Y-m-d');
        $week_ago = date('Y-m-d', strtotime('-7 days'));
        
        if (isset($click_data[$post_id])) {
            foreach ($click_data[$post_id] as $date => $date_data) {
                foreach ($date_data as $button_clicks) {
                    $clicks = intval($button_clicks);
                    $total_clicks += $clicks;
                    
                    if ($date === $current_date) {
                        $today_clicks += $clicks;
                    }
                    
                    if ($date >= $week_ago) {
                        $week_clicks += $clicks;
                    }
                }
            }
        }
        ?>
        <ul style="margin: 0; padding-left: 20px;">
            <li><?php printf(__('今日のクリック数: %s', 'finelive-lp'), '<strong>' . number_format($today_clicks) . '</strong>'); ?></li>
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
        
        // 最小値チェック
        if ($sanitized['slider_interval'] < 1000) {
            $sanitized['slider_interval'] = 1000;
        }

        // 表示期間のサニタイズ
        $sanitized['display_start_date'] = $this->sanitize_date($input_data['display_start_date'] ?? '');
        $sanitized['display_end_date'] = $this->sanitize_date($input_data['display_end_date'] ?? '');

        // ボタンデザインのサニタイズ
        $sanitized['btn_bg_color'] = $this->sanitize_color($input_data['btn_bg_color'] ?? '#ff4081');
        $sanitized['btn_text_color'] = $this->sanitize_color($input_data['btn_text_color'] ?? '#ffffff');
        $sanitized['btn_padding_tb'] = absint($input_data['btn_padding_tb'] ?? 15);
        $sanitized['btn_padding_lr'] = absint($input_data['btn_padding_lr'] ?? 30);
        $sanitized['btn_border_radius'] = absint($input_data['btn_border_radius'] ?? 5);

        return $sanitized;
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
                if (empty($url)) {
                    continue;
                }
                
                $static_images[] = array(
                    'url' => $url,
                    'show_button' => isset($input_data['show_button'][$index]) ? 1 : 0,
                    'show_slider' => isset($input_data['show_slider'][$index]) ? 1 : 0,
                );
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
