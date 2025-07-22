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
        'FLP_Error_Log_Viewer'      => 'admin/class-flp-error-log-viewer.php',
        
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
        'FLP_Error_Handler'         => 'utils/class-flp-error-handler.php',
        'FLP_Exception'             => 'utils/class-flp-exception.php',
        'FLP_Error'                 => 'utils/class-flp-error.php',
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
            'widgets/',
            'integrations/',
        );

        foreach ($directories as $dir) {
            $file_path = FLP_INCLUDES_DIR . $dir . $file_name;
            
            if (file_exists($file_path)) {
                require_once $file_path;
                return;
            }
        }

        // ファイルが見つからない場合のデバッグ情報
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'FLP Autoloader: クラス %s のファイルが見つかりません。予想されるファイル名: %s',
                $class_name,
                $file_name
            ));
        }
    }

    /**
     * クラス名からファイル名を生成
     *
     * @param string $class_name クラス名
     * @return string ファイル名
     */
    private static function get_file_name_from_class($class_name) {
        // FLP_プレフィックスを削除
        $class_name = str_replace('FLP_', '', $class_name);
        
        // アンダースコアをハイフンに変換し、小文字に
        $file_name = strtolower(str_replace('_', '-', $class_name));
        
        // class-プレフィックスとphp拡張子を追加
        return 'class-flp-' . $file_name . '.php';
    }

    /**
     * 登録されているクラスのリストを取得
     *
     * @return array クラスリスト
     */
    public static function get_registered_classes() {
        return array_keys(self::$class_files);
    }

    /**
     * クラスが登録されているかチェック
     *
     * @param string $class_name クラス名
     * @return bool
     */
    public static function is_class_registered($class_name) {
        return isset(self::$class_files[$class_name]);
    }
}

// オートローダーを登録
FLP_Autoloader::register();