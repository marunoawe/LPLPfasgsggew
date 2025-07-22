<?php
/**
 * アセット（CSS/JS）管理クラス
 */

if (!defined('ABSPATH')) exit;

class FLP_Assets {

    /**
     * 読み込み済みアセット
     */
    private $loaded_assets = array();

    /**
     * インライン CSS
     */
    private $inline_css = array();

    /**
     * インライン JavaScript
     */
    private $inline_js = array();

    /**
     * コンストラクタ
     */
    public function __construct() {
        // アセット読み込みの最適化は FLP_Frontend で制御
    }

    /**
     * フロントエンド用CSSの読み込み
     */
    public function enqueue_frontend_css() {
        if (in_array('frontend_css', $this->loaded_assets)) {
            return;
        }

        wp_enqueue_style(
            'flp-frontend',
            FLP_ASSETS_URL . 'css/frontend.css',
            array(),
            $this->get_asset_version('css/frontend.css')
        );

        $this->loaded_assets[] = 'frontend_css';

        // カスタムCSSがある場合は追加
        $this->add_custom_frontend_css();
    }

    /**
     * フロントエンド用JavaScriptの読み込み
     */
    public function enqueue_frontend_js() {
        if (in_array('frontend_js', $this->loaded_assets)) {
            return;
        }

        wp_enqueue_script(
            'flp-frontend',
            FLP_ASSETS_URL . 'js/frontend.js',
            array('jquery'),
            $this->get_asset_version('js/frontend.js'),
            true
        );

        $this->loaded_assets[] = 'frontend_js';

        // JavaScriptのローカライゼーション
        $this->localize_frontend_script();
    }

    /**
     * 管理画面用CSSの読み込み
     */
    public function enqueue_admin_css() {
        if (in_array('admin_css', $this->loaded_assets)) {
            return;
        }

        wp_enqueue_style(
            'flp-admin',
            FLP_ASSETS_URL . 'css/admin.css',
            array('wp-color-picker'),
            $this->get_asset_version('css/admin.css')
        );

        $this->loaded_assets[] = 'admin_css';
    }

    /**
     * 管理画面用JavaScriptの読み込み
     */
    public function enqueue_admin_js() {
        if (in_array('admin_js', $this->loaded_assets)) {
            return;
        }

        wp_enqueue_script(
            'flp-admin',
            FLP_ASSETS_URL . 'js/admin.js',
            array('jquery', 'jquery-ui-datepicker', 'wp-color-picker'),
            $this->get_asset_version('js/admin.js'),
            true
        );

        $this->loaded_assets[] = 'admin_js';

        // JavaScriptのローカライゼーション
        $this->localize_admin_script();
    }

    /**
     * フロントエンドスクリプトのローカライゼーション
     */
    private function localize_frontend_script() {
        wp_localize_script('flp-frontend', 'flp_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('flp_frontend_nonce'),
            'site_url' => site_url(),
            'plugin_url' => FLP_PLUGIN_URL,
            'version' => FLP_VERSION,
            'debug' => (defined('WP_DEBUG') && WP_DEBUG),
            'strings' => array(
                'loading' => __('読み込み中...', 'finelive-lp'),
                'error' => __('エラーが発生しました。', 'finelive-lp'),
                'success' => __('送信されました。', 'finelive-lp'),
                'click_tracked' => __('クリックが記録されました。', 'finelive-lp'),
            ),
            'settings' => array(
                'enable_click_tracking' => apply_filters('flp_enable_click_tracking', true),
                'animation_duration' => apply_filters('flp_animation_duration', 300),
                'slider_touch_enabled' => apply_filters('flp_slider_touch_enabled', true),
            ),
        ));
    }

    /**
     * 管理画面スクリプトのローカライゼーション
     */
    private function localize_admin_script() {
        wp_localize_script('flp-admin', 'flp_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('flp_admin_nonce'),
            'plugin_url' => FLP_PLUGIN_URL,
            'version' => FLP_VERSION,
            'strings' => array(
                'confirm_delete' => __('本当に削除しますか？', 'finelive-lp'),
                'image_select' => __('画像を選択', 'finelive-lp'),
                'image_remove' => __('画像を削除', 'finelive-lp'),
                'saving' => __('保存中...', 'finelive-lp'),
                'saved' => __('保存されました', 'finelive-lp'),
                'error' => __('エラーが発生しました', 'finelive-lp'),
                'preview' => __('プレビュー', 'finelive-lp'),
                'close' => __('閉じる', 'finelive-lp'),
            ),
            'settings' => array(
                'autosave_enabled' => apply_filters('flp_autosave_enabled', true),
                'autosave_interval' => apply_filters('flp_autosave_interval', 30000), // 30秒
                'preview_width' => apply_filters('flp_preview_width', 400),
                'preview_height' => apply_filters('flp_preview_height', 600),
            ),
        ));
    }

    /**
     * カスタムフロントエンドCSSの追加
     */
    private function add_custom_frontend_css() {
        // 基本のアニメーションCSS
        $custom_css = "
/* FineLive LP Display - Frontend Styles */
@keyframes flp_pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

@keyframes flp_bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

@keyframes flp_shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

@keyframes flp_glow {
    0%, 100% { box-shadow: 0 0 5px rgba(255, 64, 129, 0.5); }
    50% { box-shadow: 0 0 20px rgba(255, 64, 129, 0.8); }
}

@keyframes flp_float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-6px); }
}

@keyframes flp_fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* LP基本レイアウト */
.flp_lp_wrap {
    max-width: 100%;
    margin: 0 auto;
    position: relative;
}

.flp_block {
    margin-bottom: 20px;
    animation: flp_fadeIn 0.6s ease-out;
}

.flp_static_image {
    width: 100%;
    height: auto;
    display: block;
}

/* ボタンスタイル */
.flp_btn {
    display: inline-block;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-weight: bold;
    font-size: 16px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.flp_btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    text-decoration: none;
}

.flp_btn:active {
    transform: translateY(0);
}

.flp_btn_wrap {
    text-align: center;
    margin: 20px 0;
}

/* スライダースタイル */
.flp_slider {
    position: relative;
    overflow: hidden;
    margin-top: 10px;
    cursor: pointer;
    border-radius: 4px;
}

.flp_slides_container {
    display: flex;
    transition: transform 0.5s ease;
}

.flp_slide_img {
    width: 100%;
    flex-shrink: 0;
    display: block;
}

.flp_slider_controls {
    position: absolute;
    bottom: 10px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 8px;
    z-index: 10;
}

.flp_slider_dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: none;
    background: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    transition: all 0.3s ease;
}

.flp_slider_dot:hover {
    background: rgba(255, 255, 255, 0.8);
    transform: scale(1.2);
}

.flp_slider_dot.active {
    background: rgba(255, 255, 255, 1);
    box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.3);
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
    .flp_btn {
        font-size: 14px;
        padding: 12px 24px !important;
    }
    
    .flp_slider_controls {
        bottom: 5px;
    }
    
    .flp_slider_dot {
        width: 10px;
        height: 10px;
    }
}

@media (max-width: 480px) {
    .flp_btn {
        font-size: 13px;
        padding: 10px 20px !important;
    }
    
    .flp_block {
        margin-bottom: 15px;
    }
    
    .flp_btn_wrap {
        margin: 15px 0;
    }
}

/* アクセシビリティ対応 */
.flp_btn:focus {
    outline: 2px solid #007cba;
    outline-offset: 2px;
}

.flp_slider_dot:focus {
    outline: 2px solid #007cba;
    outline-offset: 2px;
}

/* プレビューモード */
.flp-preview-wrapper {
    position: relative;
}

.flp-preview-bar {
    background: #0073aa;
    color: white;
    padding: 8px 12px;
    margin-bottom: 10px;
    border-radius: 4px;
    font-size: 14px;
    text-align: center;
}

.flp-preview-bar a {
    color: #b3d4fc;
    text-decoration: none;
}

.flp-preview-bar a:hover {
    color: white;
}

/* エラー・通知 */
.flp-error,
.flp-admin-notice {
    padding: 10px;
    margin: 10px 0;
    border-radius: 4px;
    font-size: 14px;
}

.flp-error {
    background: #fff5f5;
    border: 1px solid #fed7d7;
    color: #742a2a;
}

.flp-admin-notice {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}
";

        // カスタムCSSのフィルター適用
        $custom_css = apply_filters('flp_custom_frontend_css', $custom_css);

        // インラインCSSとして追加
        wp_add_inline_style('flp-frontend', $custom_css);
    }

    /**
     * インラインCSSの追加
     *
     * @param string $css CSS コード
     * @param string $handle ハンドル名（オプション）
     */
    public function add_inline_css($css, $handle = 'flp-frontend') {
        if (!isset($this->inline_css[$handle])) {
            $this->inline_css[$handle] = array();
        }

        $this->inline_css[$handle][] = $css;
        
        // 既にスタイルが読み込まれている場合は即座に追加
        if (wp_style_is($handle, 'enqueued')) {
            wp_add_inline_style($handle, $css);
        }
    }

    /**
     * インラインJavaScriptの追加
     *
     * @param string $js JavaScript コード
     * @param string $handle ハンドル名（オプション）
     * @param string $position 位置（before/after）
     */
    public function add_inline_js($js, $handle = 'flp-frontend', $position = 'after') {
        if (!isset($this->inline_js[$handle])) {
            $this->inline_js[$handle] = array(
                'before' => array(),
                'after' => array()
            );
        }

        $this->inline_js[$handle][$position][] = $js;
        
        // 既にスクリプトが読み込まれている場合は即座に追加
        if (wp_script_is($handle, 'enqueued')) {
            wp_add_inline_script($handle, $js, $position);
        }
    }

    /**
     * アセットのバージョン取得
     *
     * @param string $file_path アセットファイルのパス
     * @return string バージョン文字列
     */
    private function get_asset_version($file_path) {
        // 開発モードの場合はファイルの更新時刻を使用
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $full_path = FLP_PLUGIN_DIR . 'assets/' . $file_path;
            if (file_exists($full_path)) {
                return filemtime($full_path);
            }
        }

        // プロダクションではプラグインバージョンを使用
        return FLP_VERSION;
    }

    /**
     * アセットのURLを取得
     *
     * @param string $file_path アセットファイルのパス
     * @return string アセットURL
     */
    public function get_asset_url($file_path) {
        return FLP_ASSETS_URL . ltrim($file_path, '/');
    }

    /**
     * アセットファイルが存在するかチェック
     *
     * @param string $file_path アセットファイルのパス
     * @return bool ファイルの存在状況
     */
    public function asset_exists($file_path) {
        $full_path = FLP_PLUGIN_DIR . 'assets/' . ltrim($file_path, '/');
        return file_exists($full_path);
    }

    /**
     * 条件付きアセット読み込み
     *
     * @param string $type アセットタイプ（css/js）
     * @param string $file_path ファイルパス
     * @param array $conditions 読み込み条件
     * @param array $dependencies 依存関係
     */
    public function conditional_enqueue($type, $file_path, $conditions = array(), $dependencies = array()) {
        // 条件チェック
        if (!$this->check_conditions($conditions)) {
            return false;
        }

        $handle = 'flp-' . basename($file_path, '.' . pathinfo($file_path, PATHINFO_EXTENSION));
        $url = $this->get_asset_url($file_path);
        $version = $this->get_asset_version($file_path);

        if ($type === 'css') {
            wp_enqueue_style($handle, $url, $dependencies, $version);
        } elseif ($type === 'js') {
            wp_enqueue_script($handle, $url, $dependencies, $version, true);
        }

        return true;
    }

    /**
     * 読み込み条件のチェック
     *
     * @param array $conditions 条件配列
     * @return bool 条件を満たすかどうか
     */
    private function check_conditions($conditions) {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition => $value) {
            switch ($condition) {
                case 'post_type':
                    if (get_post_type() !== $value) {
                        return false;
                    }
                    break;
                    
                case 'page_template':
                    if (get_page_template_slug() !== $value) {
                        return false;
                    }
                    break;
                    
                case 'is_front_page':
                    if (is_front_page() !== $value) {
                        return false;
                    }
                    break;
                    
                case 'has_shortcode':
                    global $post;
                    if (!$post || !has_shortcode($post->post_content, $value)) {
                        return false;
                    }
                    break;
                    
                case 'user_can':
                    if (!current_user_can($value)) {
                        return false;
                    }
                    break;
                    
                case 'callback':
                    if (is_callable($value) && !call_user_func($value)) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * アセットのプリロード
     *
     * @param array $assets プリロードするアセット
     */
    public function preload_assets($assets) {
        foreach ($assets as $asset) {
            $url = is_array($asset) ? $asset['url'] : $asset;
            $type = is_array($asset) && isset($asset['as']) ? $asset['as'] : $this->get_asset_type_from_url($url);
            
            printf(
                '<link rel="preload" href="%s" as="%s">',
                esc_url($url),
                esc_attr($type)
            );
        }
    }

    /**
     * URLからアセットタイプを推測
     *
     * @param string $url アセットURL
     * @return string アセットタイプ
     */
    private function get_asset_type_from_url($url) {
        $extension = pathinfo($url, PATHINFO_EXTENSION);
        
        switch ($extension) {
            case 'css':
                return 'style';
            case 'js':
                return 'script';
            case 'woff':
            case 'woff2':
                return 'font';
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
            case 'webp':
                return 'image';
            default:
                return 'fetch';
        }
    }

    /**
     * 読み込み済みアセットの一覧を取得
     *
     * @return array 読み込み済みアセット
     */
    public function get_loaded_assets() {
        return $this->loaded_assets;
    }

    /**
     * アセットの読み込み状況をリセット
     */
    public function reset_loaded_assets() {
        $this->loaded_assets = array();
        $this->inline_css = array();
        $this->inline_js = array();
    }

    /**
     * クリティカルCSSの出力
     */
    public function output_critical_css() {
        // 基本的なクリティカルCSS
        $critical_css = "
.flp_lp_wrap { max-width: 100%; margin: 0 auto; }
.flp_static_image { width: 100%; height: auto; display: block; }
.flp_btn_wrap { text-align: center; margin: 20px 0; }
.flp_btn { display: inline-block; text-decoration: none; padding: 15px 30px; border-radius: 5px; font-weight: bold; }
";

        /**
         * クリティカルCSSのフィルター
         *
         * @param string $critical_css クリティカルCSS
         */
        $critical_css = apply_filters('flp_critical_css', $critical_css);

        if (!empty($critical_css)) {
            echo '<style id="flp-critical-css">' . $critical_css . '</style>';
        }
    }
}
