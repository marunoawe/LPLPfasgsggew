<?php
/**
 * プラグインのメインクラス
 */

if (!defined('ABSPATH')) exit;

class FLP_Main {

    /**
     * シングルトンインスタンス
     */
    private static $instance = null;

    /**
     * 管理画面インスタンス
     */
    private $admin = null;

    /**
     * フロントエンドインスタンス
     */
    private $frontend = null;

    /**
     * AJAXインスタンス
     */
    private $ajax = null;

    /**
     * 初期化済みフラグ
     */
    private $initialized = false;

    /**
     * シングルトンインスタンスを取得
     *
     * @return FLP_Main
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ（プライベート）
     */
    private function __construct() {
        // シングルトンパターンのため、外部からのインスタンス化を禁止
    }

    /**
     * プラグイン初期化
     */
    public function init() {
        if ($this->initialized) {
            return;
        }

        // 基本フックの設定
        $this->define_hooks();

        // カスタム投稿タイプの登録
        new FLP_Post_Type();

        // 管理画面の初期化
        if (is_admin()) {
            $this->admin = new FLP_Admin();
        }

        // フロントエンドの初期化
        if (!is_admin() || wp_doing_ajax()) {
            $this->frontend = new FLP_Frontend();
        }

        // AJAX処理の初期化（管理画面・フロントエンド共通）
        $this->ajax = new FLP_Ajax();

        // 翻訳ファイルの読み込み
        $this->load_textdomain();

        $this->initialized = true;

        /**
         * プラグイン初期化完了後のアクション
         *
         * @param FLP_Main $this メインインスタンス
         */
        do_action('flp_init', $this);
    }

    /**
     * 基本フックの定義
     */
    private function define_hooks() {
        // プラグイン情報リンクの追加
        add_filter('plugin_action_links_' . plugin_basename(FLP_PLUGIN_FILE), array($this, 'plugin_action_links'));
        
        // プラグインメタリンクの追加
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
    }

    /**
     * プラグイン有効化時の処理
     */
    public function activate() {
        // カスタム投稿タイプの登録（リライトルールの更新のため）
        if (!class_exists('FLP_Post_Type')) {
            new FLP_Post_Type();
        }
        
        // リライトルールを更新
        flush_rewrite_rules();

        // バージョン情報を保存
        update_option('flp_version', FLP_VERSION);
        
        // 初回有効化フラグ
        if (!get_option('flp_activated_time')) {
            update_option('flp_activated_time', current_time('timestamp'));
        }

        /**
         * プラグイン有効化後のアクション
         */
        do_action('flp_activated');
    }

    /**
     * プラグイン無効化時の処理
     */
    public function deactivate() {
        // リライトルールをクリア
        flush_rewrite_rules();

        /**
         * プラグイン無効化後のアクション
         */
        do_action('flp_deactivated');
    }

    /**
     * 翻訳ファイルの読み込み
     */
    public function load_textdomain() {
        $locale = determine_locale();
        $locale = apply_filters('plugin_locale', $locale, 'finelive-lp');

        // wp-content/languages/plugins/ から読み込み
        load_textdomain('finelive-lp', WP_LANG_DIR . '/plugins/finelive-lp-' . $locale . '.mo');
        
        // プラグイン内の languages/ から読み込み
        load_plugin_textdomain('finelive-lp', false, dirname(plugin_basename(FLP_PLUGIN_FILE)) . '/languages');
    }

    /**
     * プラグインアクションリンクの追加
     *
     * @param array $links リンク配列
     * @return array
     */
    public function plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('edit.php?post_type=flp_lp'),
            __('LP管理', 'finelive-lp')
        );
        
        $usage_link = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            admin_url('edit.php?post_type=flp_lp&page=flp-lp-usage'),
            __('使い方', 'finelive-lp')
        );

        array_unshift($links, $settings_link, $usage_link);
        return $links;
    }

    /**
     * プラグインメタリンクの追加
     *
     * @param array $links リンク配列
     * @param string $file プラグインファイル
     * @return array
     */
    public function plugin_row_meta($links, $file) {
        if (plugin_basename(FLP_PLUGIN_FILE) === $file) {
            $row_meta = array(
                'support' => sprintf(
                    '<a href="%s" target="_blank">%s</a>',
                    'https://example.com/support',
                    __('サポート', 'finelive-lp')
                ),
                'docs' => sprintf(
                    '<a href="%s" target="_blank">%s</a>',
                    admin_url('edit.php?post_type=flp_lp&page=flp-lp-usage'),
                    __('ドキュメント', 'finelive-lp')
                ),
            );
            $links = array_merge($links, $row_meta);
        }
        return $links;
    }

    /**
     * 管理画面インスタンスの取得
     *
     * @return FLP_Admin|null
     */
    public function admin() {
        return $this->admin;
    }

    /**
     * フロントエンドインスタンスの取得
     *
     * @return FLP_Frontend|null
     */
    public function frontend() {
        return $this->frontend;
    }

    /**
     * AJAXインスタンスの取得
     *
     * @return FLP_Ajax|null
     */
    public function ajax() {
        return $this->ajax;
    }

    /**
     * プラグインのバージョンを取得
     *
     * @return string
     */
    public function get_version() {
        return FLP_VERSION;
    }

    /**
     * デバッグモードかどうか
     *
     * @return bool
     */
    public function is_debug() {
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * プラグインのURLを取得
     *
     * @param string $path パス（オプション）
     * @return string
     */
    public function plugin_url($path = '') {
        return FLP_PLUGIN_URL . ltrim($path, '/');
    }

    /**
     * プラグインのパスを取得
     *
     * @param string $path パス（オプション）
     * @return string
     */
    public function plugin_path($path = '') {
        return FLP_PLUGIN_DIR . ltrim($path, '/');
    }

    /**
     * アセットのURLを取得
     *
     * @param string $path アセットのパス
     * @return string
     */
    public function asset_url($path = '') {
        return FLP_ASSETS_URL . ltrim($path, '/');
    }

    /**
     * ログ出力（デバッグモード時のみ）
     *
     * @param mixed $message メッセージ
     * @param string $level ログレベル
     */
    public function log($message, $level = 'info') {
        if (!$this->is_debug()) {
            return;
        }

        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }

        error_log(sprintf('[FLP %s] %s', strtoupper($level), $message));
    }
}
