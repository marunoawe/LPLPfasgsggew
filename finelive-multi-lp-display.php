<?php
/**
 * Plugin Name: FineLive Multi LP Display
 * Description: カスタム投稿タイプLPで複数のLPを管理。静的画像ごとにボタン表示/スライダー表示選択。スライダー自動切替、再生時間設定。LP複製機能。表示期間設定。クリック測定対応。使い方説明とボタンデザイン機能を追加。
 * Version: 2.2
 * Author: FineLive
 */

if (!defined('ABSPATH')) exit;

// プラグイン定数の定義
define('FLP_VERSION', '2.2');
define('FLP_PLUGIN_FILE', __FILE__);
define('FLP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FLP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FLP_ASSETS_URL', FLP_PLUGIN_URL . 'assets/');
define('FLP_INCLUDES_DIR', FLP_PLUGIN_DIR . 'includes/');
define('FLP_ADMIN_DIR', FLP_INCLUDES_DIR . 'admin/');
define('FLP_FRONTEND_DIR', FLP_INCLUDES_DIR . 'frontend/');

// オートローダーを読み込み
require_once FLP_INCLUDES_DIR . 'class-flp-autoloader.php';

// メインクラスを読み込み
require_once FLP_INCLUDES_DIR . 'class-flp-main.php';

/**
 * プラグインのメインインスタンスを返す
 */
function FLP() {
    return FLP_Main::instance();
}

// プラグイン初期化
add_action('plugins_loaded', array(FLP(), 'init'));

// アクティベーション・非アクティベーションフック
register_activation_hook(__FILE__, array(FLP(), 'activate'));
register_deactivation_hook(__FILE__, array(FLP(), 'deactivate'));
