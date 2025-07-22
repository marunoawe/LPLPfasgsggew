<?php
/**
 * FLP オートローダークラス
 */

if (!defined('ABSPATH')) exit;

class FLP_Autoloader {

    /**
     * クラス名とファイルパスのマッピング
     */
    private static $class_files = array(
        // コアクラス
        'FLP_Main'                  => 'class-flp-main.php',
        
        // 管理画面関連
        'FLP_Admin'                 => 'admin/class-flp-admin.php',
        'FLP_Meta_Boxes'            => 'admin/class-flp-meta-boxes.php',
        'FLP_Admin_Menu'            => 'admin/class-flp-admin-menu.php',
        'FLP_Usage_Guide'           => 'admin/class-flp-usage-guide.php',
        'FLP_Click_Reports'         => 'admin/class-flp-click-reports.php',
        'FLP_LP_Duplicator'         => 'admin/class-flp-lp-duplicator.php',
        
        // フロントエンド関連
        'FLP_Frontend'              => 'frontend/class-flp-frontend.php',
        'FLP_Shortcode'             => 'frontend/class-flp-shortcode.php',
        'FLP_Assets'                => 'frontend/class-flp-assets.php',
        
        // AJAX関連
        'FLP_Ajax'                  => 'ajax/class-flp-ajax.php',
        'FLP_Click_Tracking'        => 'ajax/class-flp-click-tracking.php',
        
        // データ管理
        'FLP_Post_Type'             => 'data/class-flp-post-type.php',
        'FLP_Meta_Data'             => 'data/class-flp-meta-data.php',
        
        // ユーティリティ
        'FLP_Helper'                => 'utils/class-flp-helper.php',
        'FLP_Validator'             => 'utils/class-flp-validator.php',
    );

    /**
     * オートローダーを登録
     */
    public static function register() {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }

    /**
     * クラスファイルを自動読み込み
     *
     * @param string $class_name クラス名
     */
    public static function autoload($class_name) {
        // FLP_プレフィックスのクラスのみ処理
        if (strpos($class_name, 'FLP_') !== 0) {
            return;
        }

        // マッピングから直接取得
        if (isset(self::$class_files[$class_name])) {
            $file_path = FLP_INCLUDES_DIR . self::$class_files[$class_name];
            
            if (file_exists($file_path)) {
                require_once $file_path;
                return;
            }
        }

        // マッピングにない場合は規則に基づいて推測
        $file_name = self::get_file_name_from_class($class_name);
        
        // 推測されるディレクトリ
        $directories = array(
            '',
            'admin/',
            'frontend/', 
            'ajax/',
            'data/',
            'utils/',
            'components/',
        );

        foreach ($directories as $dir) {
            $file_path = FLP_INCLUDES_DIR . $dir . $file_name;
            if (file_exists($file_path)) {
                require_once $file_path;
                return;
            }
        }
    }

    /**
     * クラス名からファイル名を推測
     *
     * @param string $class_name クラス名
     * @return string ファイル名
     */
    private static function get_file_name_from_class($class_name) {
        // FLP_Admin_Menu -> class-flp-admin-menu.php
        $file_name = strtolower($class_name);
        $file_name = str_replace('_', '-', $file_name);
        return 'class-' . $file_name . '.php';
    }

    /**
     * 新しいクラスマッピングを追加
     *
     * @param string $class_name クラス名
     * @param string $file_path ファイルパス (includes/からの相対パス)
     */
    public static function add_class($class_name, $file_path) {
        self::$class_files[$class_name] = $file_path;
    }

    /**
     * 登録されているクラス一覧を取得
     *
     * @return array
     */
    public static function get_registered_classes() {
        return self::$class_files;
    }
}

// オートローダーを登録
FLP_Autoloader::register();
