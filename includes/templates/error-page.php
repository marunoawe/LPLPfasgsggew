<?php
/**
 * エラーページテンプレート（改善版）
 * templates/error-page.php
 */

// エラーハンドラーのインスタンスを取得
$error_handler = FLP_Error_Handler::instance();
$is_debug_mode = $error_handler->is_debug_mode();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('エラーが発生しました', 'finelive-lp'); ?></title>
    <!-- スタイルは同じ -->
</head>
<body>
    <div class="error-container">
        <div class="error-icon">&#9888;&#65039;</div>
        
        <h1><?php _e('申し訳ございません', 'finelive-lp'); ?></h1>
        
        <div class="error-message">
            <?php if ($is_admin && $can_see_details): ?>
                <p><?= esc_html($error['message'] ?? __('予期しないエラーが発生しました。', 'finelive-lp')) ?></p>
            <?php else: ?>
                <p><?php _e('一時的な問題が発生しています。しばらくしてから再度お試しください。', 'finelive-lp'); ?></p>
            <?php endif; ?>
        </div>
        
        <?php if ($can_see_details && $is_debug_mode): ?>
            <div class="error-details">
                <!-- エラー詳細表示 -->
            </div>
        <?php endif; ?>
        
        <!-- アクションボタン -->
    </div>
</body>
</html>

<?php
/**
 * エラーログビューア管理画面（改善版）
 * includes/admin/class-flp-error-log-viewer.php
 */

// テーブル名を定数として定義
if (!defined('FLP_ERROR_LOG_TABLE')) {
    define('FLP_ERROR_LOG_TABLE', $GLOBALS['wpdb']->prefix . 'flp_error_logs');
}

class FLP_Error_Log_Viewer {
    
    /**
     * シングルトンインスタンス
     */
    private static $instance = null;
    
    /**
     * インスタンス取得
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * コンストラクタ
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * フックの初期化
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // AJAXハンドラー
        add_action('wp_ajax_flp_get_error_details', [$this, 'ajax_get_error_details']);
        add_action('wp_ajax_flp_clear_error_logs', [$this, 'ajax_clear_logs']);
        add_action('wp_ajax_flp_export_error_logs', [$this, 'ajax_export_logs']);
    }
    
    /**
     * エラーログのエクスポート機能
     */
    public function ajax_export_logs() {
        check_ajax_referer('flp_error_log', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        global $wpdb;
        
        $errors = $wpdb->get_results(
            "SELECT * FROM " . FLP_ERROR_LOG_TABLE . " ORDER BY created_at DESC",
            ARRAY_A
        );
        
        // CSVヘッダー
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=error-logs-' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // BOM付きUTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // ヘッダー行
        fputcsv($output, [
            'ID',
            'レベル',
            'エラーコード',
            'メッセージ',
            'ファイル',
            '行番号',
            'URL',
            'ユーザーID',
            '発生時刻'
        ]);
        
        // データ行
        foreach ($errors as $error) {
            fputcsv($output, [
                $error['id'],
                $error['error_level'],
                $error['error_code'],
                $error['error_message'],
                $error['error_file'],
                $error['error_line'],
                $error['url'],
                $error['user_id'],
                $error['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * ログのクリア
     */
    public function ajax_clear_logs() {
        check_ajax_referer('flp_error_log', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        global $wpdb;
        
        // 30日以上前のログのみ削除（安全のため）
        $deleted = $wpdb->query(
            "DELETE FROM " . FLP_ERROR_LOG_TABLE . " 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        wp_send_json_success([
            'message' => sprintf(
                __('%d件の古いログを削除しました。', 'finelive-lp'),
                $deleted
            )
        ]);
    }
    
    // 他のメソッドは元のコードと同じ...
}

/**
 * 強化されたトライキャッチハンドラー
 * includes/utils/class-flp-try-catch-handler.php
 */

class FLP_Try_Catch_Handler {
    
    /**
     * リトライ機能付き安全実行
     */
    public static function safe_execute_with_retry($callback, $max_attempts = 3, $delay = 1) {
        $attempts = 0;
        $last_exception = null;
        
        while ($attempts < $max_attempts) {
            try {
                return call_user_func($callback);
            } catch (Exception $e) {
                $last_exception = $e;
                $attempts++;
                
                if ($attempts < $max_attempts) {
                    sleep($delay);
                }
            }
        }
        
        // 全ての試行が失敗した場合
        if ($last_exception) {
            FLP_Error_Handler::instance()->log(
                sprintf(
                    __('%d回の試行後も処理に失敗しました: %s', 'finelive-lp'),
                    $max_attempts,
                    $last_exception->getMessage()
                ),
                FLP_Error_Handler::LEVEL_ERROR
            );
            
            throw $last_exception;
        }
    }
    
    /**
     * バッチ処理の安全実行
     */
    public static function batch_safe_execute($items, $callback, $batch_size = 100) {
        $results = [];
        $errors = [];
        
        $chunks = array_chunk($items, $batch_size);
        
        foreach ($chunks as $chunk_index => $chunk) {
            foreach ($chunk as $item_index => $item) {
                try {
                    $results[] = call_user_func($callback, $item);
                } catch (Exception $e) {
                    $errors[] = [
                        'item' => $item,
                        'index' => ($chunk_index * $batch_size) + $item_index,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            // メモリ解放
            if ($chunk_index % 10 === 0) {
                wp_cache_flush();
            }
        }
        
        return [
            'success' => $results,
            'errors' => $errors,
            'total' => count($items),
            'succeeded' => count($results),
            'failed' => count($errors)
        ];
    }
    
    /**
     * 非同期処理の安全実行（バックグラウンドジョブ用）
     */
    public static function async_safe_execute($callback, $args = [], $priority = 10) {
        // WordPressのクーロンジョブとして登録
        $hook = 'flp_async_task_' . wp_generate_uuid4();
        
        add_action($hook, function() use ($callback, $args) {
            try {
                call_user_func_array($callback, $args);
            } catch (Exception $e) {
                FLP_Error_Handler::instance()->log(
                    sprintf(__('非同期タスクの実行に失敗しました: %s', 'finelive-lp'), $e->getMessage()),
                    FLP_Error_Handler::LEVEL_ERROR,
                    'ASYNC_TASK_FAILED'
                );
            }
        });
        
        // 5秒後に実行
        wp_schedule_single_event(time() + 5, $hook);
        
        return $hook;
    }
}

/**
 * カスタム例外クラス
 */
class FLP_Exception extends Exception {
    protected $error_code;
    protected $context;
    
    public function __construct($message = "", $error_code = "", $context = [], $previous = null) {
        parent::__construct($message, 0, $previous);
        $this->error_code = $error_code;
        $this->context = $context;
    }
    
    public function getErrorCode() {
        return $this->error_code;
    }
    
    public function getContext() {
        return $this->context;
    }
}