<?php
/**
 * ===================================
 * 1. エラーハンドリングメインクラス
 * includes/utils/class-flp-error-handler.php
 * ===================================
 */

if (!defined('ABSPATH')) exit;

// エラーレベル定数
if (!defined('FLP_ERROR_LEVEL_CRITICAL')) {
    define('FLP_ERROR_LEVEL_CRITICAL', 'critical');
    define('FLP_ERROR_LEVEL_ERROR', 'error');
    define('FLP_ERROR_LEVEL_WARNING', 'warning');
    define('FLP_ERROR_LEVEL_NOTICE', 'notice');
    define('FLP_ERROR_LEVEL_DEBUG', 'debug');
}

class FLP_Error_Handler {
    
    /**
     * シングルトンインスタンス
     */
    private static $instance = null;
    
    /**
     * エラーログ
     */
    private $errors = [];
    
    /**
     * エラーログテーブル名
     */
    private $table_name;
    
    /**
     * エラーレベル定義
     */
    const LEVEL_CRITICAL = FLP_ERROR_LEVEL_CRITICAL;
    const LEVEL_ERROR = FLP_ERROR_LEVEL_ERROR;
    const LEVEL_WARNING = FLP_ERROR_LEVEL_WARNING;
    const LEVEL_NOTICE = FLP_ERROR_LEVEL_NOTICE;
    const LEVEL_DEBUG = FLP_ERROR_LEVEL_DEBUG;
    
    /**
     * エラーコード定義
     */
    const ERROR_CODES = [
        // システムエラー (1000番台)
        'SYSTEM_INIT_FAILED' => 1001,
        'DATABASE_ERROR' => 1002,
        'FILE_PERMISSION' => 1003,
        'MEMORY_LIMIT' => 1004,
        'TIMEOUT' => 1005,
        
        // LP関連エラー (2000番台)
        'LP_NOT_FOUND' => 2001,
        'LP_INVALID_DATA' => 2002,
        'LP_SAVE_FAILED' => 2003,
        'LP_DELETE_FAILED' => 2004,
        'LP_DUPLICATE_FAILED' => 2005,
        'LP_PERMISSION_DENIED' => 2006,
        
        // メディア関連エラー (3000番台)
        'MEDIA_UPLOAD_FAILED' => 3001,
        'MEDIA_INVALID_TYPE' => 3002,
        'MEDIA_SIZE_EXCEEDED' => 3003,
        'MEDIA_NOT_FOUND' => 3004,
        'MEDIA_PROCESSING_FAILED' => 3005,
        
        // AJAX関連エラー (4000番台)
        'AJAX_NONCE_FAILED' => 4001,
        'AJAX_PERMISSION_DENIED' => 4002,
        'AJAX_INVALID_ACTION' => 4003,
        'AJAX_MISSING_PARAMS' => 4004,
        'AJAX_INVALID_REQUEST' => 4005,
        
        // 外部API関連エラー (5000番台)
        'API_CONNECTION_FAILED' => 5001,
        'API_RATE_LIMIT' => 5002,
        'API_INVALID_RESPONSE' => 5003,
        'API_AUTH_FAILED' => 5004,
        'API_TIMEOUT' => 5005,
    ];
    
    /**
     * シングルトンインスタンス取得
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * コンストラクタ
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'flp_error_logs';
        
        $this->init_error_handling();
        $this->init_exception_handling();
        $this->init_shutdown_handling();
        $this->init_hooks();
        $this->schedule_cleanup();
    }
    
    /**
     * フックの初期化
     */
    private function init_hooks() {
        add_action('admin_notices', [$this, 'display_admin_errors']);
        add_action('wp_ajax_flp_dismiss_error', [$this, 'ajax_dismiss_error']);
        add_action('flp_cleanup_error_logs', [$this, 'cleanup_old_logs']);
        
        // プラグイン有効化時のテーブル作成
        register_activation_hook(FLP_PLUGIN_FILE, [$this, 'create_error_log_table']);
    }
    
    /**
     * エラーハンドリングの初期化
     */
    private function init_error_handling() {
        if ($this->is_debug_mode()) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            ini_set('log_errors', 1);
        } else {
            error_reporting(E_ERROR | E_WARNING | E_PARSE);
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
        }
        
        set_error_handler([$this, 'error_handler'], E_ALL);
    }
    
    /**
     * 例外ハンドリングの初期化
     */
    private function init_exception_handling() {
        set_exception_handler([$this, 'exception_handler']);
    }
    
    /**
     * シャットダウンハンドリングの初期化
     */
    private function init_shutdown_handling() {
        register_shutdown_function([$this, 'shutdown_handler']);
    }
    
    /**
     * 定期クリーンアップのスケジューリング
     */
    private function schedule_cleanup() {
        if (!wp_next_scheduled('flp_cleanup_error_logs')) {
            wp_schedule_event(time(), 'daily', 'flp_cleanup_error_logs');
        }
    }
    
    /**
     * エラーハンドラー
     */
    public function error_handler($errno, $errstr, $errfile, $errline) {
        // エラー報告レベルのチェック
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        // サプレスエラー演算子（@）のチェック
        if (error_reporting() === 0) {
            return false;
        }
        
        $level = $this->get_error_level($errno);
        $context = $this->get_error_context();
        
        $error = [
            'level' => $level,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'type' => $this->get_error_type($errno),
            'code' => null,
            'context' => $context,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'url' => $this->get_current_url(),
            'trace' => $this->get_simplified_trace()
        ];
        
        $this->log_error($error);
        
        // 致命的エラーの場合
        if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->handle_fatal_error($error);
            return true;
        }
        
        // デバッグモードでない場合はデフォルトハンドラーを実行しない
        return !$this->is_debug_mode();
    }
    
    /**
     * 例外ハンドラー
     */
    public function exception_handler($exception) {
        $error = [
            'level' => self::LEVEL_ERROR,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'type' => get_class($exception),
            'code' => $exception instanceof FLP_Exception ? $exception->getErrorCode() : $exception->getCode(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'url' => $this->get_current_url(),
            'context' => $this->get_error_context()
        ];
        
        // カスタム例外の追加データ
        if ($exception instanceof FLP_Exception) {
            $error['data'] = $exception->getData();
        }
        
        $this->log_error($error);
        
        // カスタム例外の処理
        if ($exception instanceof FLP_Exception) {
            $this->handle_custom_exception($exception);
        } else {
            $this->handle_fatal_error($error);
        }
    }
    
    /**
     * シャットダウンハンドラー
     */
    public function shutdown_handler() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->error_handler($error['type'], $error['message'], $error['file'], $error['line']);
        }
        
        // エラーサマリーの保存
        if (!empty($this->errors)) {
            $this->save_error_summary();
        }
    }
    
    /**
     * エラーログ記録
     */
    private function log_error($error) {
        // メモリに一時保存
        $this->errors[] = $error;
        
        // ファイルログ
        if ($this->should_log_to_file($error['level'])) {
            $this->write_to_log_file($error);
        }
        
        // データベースログ
        if ($this->should_log_to_database($error['level'])) {
            $this->write_to_database($error);
        }
        
        // 管理者への通知
        if ($error['level'] === self::LEVEL_CRITICAL) {
            $this->notify_admin($error);
        }
        
        // 外部サービスへの送信
        if ($this->is_external_logging_enabled()) {
            $this->send_to_external_service($error);
        }
    }
    
    /**
     * エラーの記録（公開API）
     */
    public function log($message, $level = self::LEVEL_ERROR, $code = null, $data = []) {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1] ?? $backtrace[0];
        
        $error = [
            'level' => $level,
            'message' => $message,
            'code' => $code,
            'data' => $data,
            'file' => $caller['file'] ?? 'unknown',
            'line' => $caller['line'] ?? 0,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'url' => $this->get_current_url(),
            'context' => $this->get_error_context(),
            'trace' => $this->get_simplified_trace(1)
        ];
        
        $this->log_error($error);
        
        return new FLP_Error($message, $code, $data);
    }
    
    /**
     * 致命的エラーの処理
     */
    private function handle_fatal_error($error) {
        // クリーンなエラーページを表示
        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
            header('X-Content-Type-Options: nosniff');
        }
        
        // AJAX リクエストの場合
        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => __('システムエラーが発生しました。', 'finelive-lp'),
                'code' => $error['code'] ?? 'FATAL_ERROR',
                'debug' => $this->is_debug_mode() ? $error : null
            ]);
        }
        
        // REST APIリクエストの場合
        if (defined('REST_REQUEST') && REST_REQUEST) {
            wp_die(
                json_encode([
                    'code' => $error['code'] ?? 'FATAL_ERROR',
                    'message' => __('システムエラーが発生しました。', 'finelive-lp'),
                    'data' => ['status' => 500]
                ]),
                '',
                ['response' => 500]
            );
        }
        
        // 通常のリクエストの場合
        $this->display_error_page($error);
        
        // エラーリカバリーの試行
        $this->attempt_recovery($error);
        
        exit;
    }
    
    /**
     * カスタム例外の処理
     */
    private function handle_custom_exception($exception) {
        $error_code = $exception->getErrorCode();
        $data = $exception->getData();
        
        // AJAX リクエストの場合
        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => $exception->getMessage(),
                'code' => $error_code,
                'data' => $data,
                'debug' => $this->is_debug_mode() ? [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString()
                ] : null
            ]);
        }
        
        // それ以外
        $this->handle_fatal_error([
            'message' => $exception->getMessage(),
            'code' => $error_code,
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ]);
    }
    
    /**
     * エラーページの表示
     */
    private function display_error_page($error) {
        $is_admin = is_admin();
        $can_see_details = current_user_can('manage_options');
        $error_handler = $this; // テンプレートで使用
        
        $template_file = FLP_PLUGIN_DIR . 'templates/error-page.php';
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            // フォールバック
            echo '<h1>Error</h1>';
            echo '<p>' . esc_html($error['message'] ?? 'An error occurred.') . '</p>';
        }
    }
    
    /**
     * 管理画面でのエラー表示
     */
    public function display_admin_errors() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // 表示済みエラーの除外
        $dismissed = get_user_meta(get_current_user_id(), 'flp_dismissed_errors', true) ?: [];
        
        $recent_errors = $this->get_recent_errors(5);
        
        if (empty($recent_errors)) {
            return;
        }
        
        foreach ($recent_errors as $error) {
            if (!in_array($error['id'], $dismissed)) {
                $this->display_error_notice($error);
            }
        }
    }
    
    /**
     * エラー通知の表示
     */
    private function display_error_notice($error) {
        $class = 'notice notice-' . $this->get_notice_class($error['error_level']);
        $dismissible = $error['error_level'] !== self::LEVEL_CRITICAL ? 'is-dismissible' : '';
        
        ?>
        <div class="<?= esc_attr($class) ?> <?= esc_attr($dismissible) ?> flp-error-notice" data-error-id="<?= esc_attr($error['id']) ?>">
            <p>
                <strong>[FineLive LP]</strong>
                <?php if ($error['error_code']): ?>
                    <code><?= esc_html($error['error_code']) ?></code>
                <?php endif; ?>
                <?= esc_html($error['error_message']) ?>
                
                <?php if ($this->is_debug_mode()): ?>
                    <br><small><?= esc_html($error['error_file']) ?>:<?= esc_html($error['error_line']) ?></small>
                <?php endif; ?>
            </p>
            
            <?php if ($error['error_level'] === self::LEVEL_CRITICAL): ?>
                <p>
                    <a href="<?= admin_url('edit.php?post_type=flp_lp&page=flp_error_logs') ?>" class="button button-primary">
                        <?= __('エラーログを確認', 'finelive-lp') ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * エラー通知の非表示（AJAX）
     */
    public function ajax_dismiss_error() {
        check_ajax_referer('flp_dismiss_error', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $error_id = intval($_POST['error_id']);
        $user_id = get_current_user_id();
        
        $dismissed = get_user_meta($user_id, 'flp_dismissed_errors', true) ?: [];
        $dismissed[] = $error_id;
        
        update_user_meta($user_id, 'flp_dismissed_errors', array_unique($dismissed));
        
        wp_send_json_success();
    }
    
    /**
     * エラーリカバリーの試行
     */
    private function attempt_recovery($error) {
        // トランジェントのクリア
        delete_transient('flp_cache_lock');
        delete_transient('flp_processing');
        
        // 一時ファイルのクリーンアップ
        $this->cleanup_temp_files();
        
        // データベース修復の試行
        if (strpos($error['message'], 'database') !== false || strpos($error['message'], 'MySQL') !== false) {
            $this->repair_database_tables();
        }
        
        // メモリ制限エラーの場合
        if (strpos($error['message'], 'memory') !== false || strpos($error['message'], 'Allowed memory') !== false) {
            $this->increase_memory_limit();
        }
        
        // タイムアウトエラーの場合
        if (strpos($error['message'], 'Maximum execution time') !== false) {
            @set_time_limit(300);
        }
    }
    
    /**
     * ログファイルへの書き込み
     */
    private function write_to_log_file($error) {
        $log_dir = WP_CONTENT_DIR . '/flp-logs/';
        
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            
            // .htaccess でアクセス制限
            $htaccess_content = "Order Deny,Allow\nDeny from all\n";
            file_put_contents($log_dir . '.htaccess', $htaccess_content);
            
            // index.php で直接アクセスを防ぐ
            file_put_contents($log_dir . 'index.php', '<?php // Silence is golden');
        }
        
        $log_file = $log_dir . 'error-' . date('Y-m-d') . '.log';
        $log_entry = sprintf(
            "[%s] %s | %s | %s in %s:%d\n%s\n%s\n",
            $error['timestamp'],
            strtoupper($error['level']),
            $error['message'],
            $error['type'] ?? 'Unknown',
            $error['file'] ?? 'Unknown',
            $error['line'] ?? 0,
            isset($error['trace']) ? "Trace: " . $error['trace'] : '',
            str_repeat('-', 80)
        );
        
        error_log($log_entry, 3, $log_file);
    }
    
    /**
     * データベースへの書き込み
     */
    private function write_to_database($error) {
        global $wpdb;
        
        // テーブルが存在しない場合は作成
        if (!$this->table_exists()) {
            $this->create_error_log_table();
        }
        
        // データサニタイズ
        $data = [
            'error_level' => sanitize_text_field($error['level']),
            'error_message' => wp_kses_post($error['message']),
            'error_code' => sanitize_text_field($error['code'] ?? ''),
            'error_file' => sanitize_text_field($error['file'] ?? ''),
            'error_line' => intval($error['line'] ?? 0),
            'error_context' => wp_json_encode($error['context'] ?? []),
            'user_id' => intval($error['user_id'] ?? 0),
            'url' => esc_url_raw($error['url'] ?? ''),
            'created_at' => sanitize_text_field($error['timestamp'])
        ];
        
        $wpdb->insert($this->table_name, $data);
    }
    
    /**
     * エラーログテーブルの作成
     */
    public function create_error_log_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            error_level varchar(20) NOT NULL,
            error_message text NOT NULL,
            error_code varchar(50) DEFAULT '',
            error_file varchar(500) DEFAULT '',
            error_line int(11) DEFAULT 0,
            error_context longtext,
            user_id bigint(20) unsigned DEFAULT 0,
            url varchar(500) DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY error_level (error_level),
            KEY error_code (error_code),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * テーブルの存在確認
     */
    private function table_exists() {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
    }
    
    /**
     * エラーコンテキストの取得
     */
    private function get_error_context() {
        return [
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => defined('FLP_VERSION') ? FLP_VERSION : 'unknown',
            'theme' => get_option('template'),
            'active_plugins' => get_option('active_plugins'),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'is_ajax' => wp_doing_ajax(),
            'is_cron' => wp_doing_cron(),
            'is_rest' => defined('REST_REQUEST') && REST_REQUEST,
            'is_admin' => is_admin(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
            'php_sapi' => php_sapi_name()
        ];
    }
    
    /**
     * 簡略化されたスタックトレースの取得
     */
    private function get_simplified_trace($skip = 0) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $simplified = [];
        
        foreach (array_slice($trace, $skip + 1, 5) as $frame) {
            $simplified[] = sprintf(
                "%s%s%s() at %s:%d",
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'] ?? 'unknown',
                isset($frame['file']) ? basename($frame['file']) : 'unknown',
                $frame['line'] ?? 0
            );
        }
        
        return implode("\n", $simplified);
    }
    
    /**
     * 現在のURLの取得
     */
    private function get_current_url() {
        if (wp_doing_ajax()) {
            return admin_url('admin-ajax.php') . '?action=' . ($_REQUEST['action'] ?? 'unknown');
        }
        
        if (wp_doing_cron()) {
            return 'wp-cron.php';
        }
        
        $protocol = is_ssl() ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        return $protocol . $host . $uri;
    }
    
    /**
     * 最近のエラーを取得
     */
    public function get_recent_errors($limit = 10, $level = null) {
        global $wpdb;
        
        $where = $level ? $wpdb->prepare("WHERE error_level = %s", $level) : '';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} $where ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }
    
    /**
     * エラー統計の取得
     */
    public function get_error_stats() {
        global $wpdb;
        
        $stats = [
            'by_level' => [],
            'timeline' => [],
            'frequent' => [],
            'by_code' => []
        ];
        
        // レベル別カウント
        $stats['by_level'] = $wpdb->get_results(
            "SELECT error_level, COUNT(*) as count 
             FROM {$this->table_name}
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY error_level",
            OBJECT_K
        );
        
        // 時系列データ（過去30日）
        $stats['timeline'] = $wpdb->get_results(
            "SELECT DATE(created_at) as date, 
                    COUNT(*) as total,
                    SUM(CASE WHEN error_level = 'critical' THEN 1 ELSE 0 END) as critical,
                    SUM(CASE WHEN error_level = 'error' THEN 1 ELSE 0 END) as error,
                    SUM(CASE WHEN error_level = 'warning' THEN 1 ELSE 0 END) as warning,
                    SUM(CASE WHEN error_level = 'notice' THEN 1 ELSE 0 END) as notice
             FROM {$this->table_name}
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC"
        );
        
        // 頻出エラー
        $stats['frequent'] = $wpdb->get_results(
            "SELECT error_message, error_code, COUNT(*) as count 
             FROM {$this->table_name}
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY error_message, error_code
             ORDER BY count DESC
             LIMIT 10"
        );
        
        // エラーコード別
        $stats['by_code'] = $wpdb->get_results(
            "SELECT error_code, COUNT(*) as count 
             FROM {$this->table_name}
             WHERE error_code != '' 
             AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY error_code
             ORDER BY count DESC
             LIMIT 20"
        );
        
        return $stats;
    }
    
    /**
     * デバッグモードかどうか
     */
    public function is_debug_mode() {
        return (defined('WP_DEBUG') && WP_DEBUG) || 
               (defined('FLP_DEBUG') && FLP_DEBUG);
    }
    
    /**
     * エラーレベルの取得
     */
    private function get_error_level($errno) {
        switch ($errno) {
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return self::LEVEL_CRITICAL;
            
            case E_WARNING:
            case E_USER_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_RECOVERABLE_ERROR:
                return self::LEVEL_WARNING;
            
            case E_NOTICE:
            case E_USER_NOTICE:
                return self::LEVEL_NOTICE;
            
            case E_STRICT:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return self::LEVEL_DEBUG;
            
            default:
                return self::LEVEL_ERROR;
        }
    }
    
    /**
     * エラータイプの取得
     */
    private function get_error_type($errno) {
        $types = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];
        
        return $types[$errno] ?? 'UNKNOWN';
    }
    
    /**
     * 通知クラスの取得
     */
    private function get_notice_class($level) {
        switch ($level) {
            case self::LEVEL_CRITICAL:
            case self::LEVEL_ERROR:
                return 'error';
            case self::LEVEL_WARNING:
                return 'warning';
            case self::LEVEL_NOTICE:
                return 'info';
            default:
                return 'info';
        }
    }
    
    /**
     * ファイルへのログ記録が必要か
     */
    private function should_log_to_file($level) {
        // デバッグレベルは開発環境のみ
        if ($level === self::LEVEL_DEBUG && !$this->is_debug_mode()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * データベースへのログ記録が必要か
     */
    private function should_log_to_database($level) {
        // 通知レベル以上をデータベースに記録
        $levels = [
            self::LEVEL_CRITICAL => 5,
            self::LEVEL_ERROR => 4,
            self::LEVEL_WARNING => 3,
            self::LEVEL_NOTICE => 2,
            self::LEVEL_DEBUG => 1
        ];
        
        $threshold = $this->is_debug_mode() ? 1 : 2;
        
        return ($levels[$level] ?? 0) >= $threshold;
    }
    
    /**
     * 外部ログサービスが有効か
     */
    private function is_external_logging_enabled() {
        return defined('FLP_EXTERNAL_LOGGING') && FLP_EXTERNAL_LOGGING;
    }
    
    /**
     * 外部サービスへの送信
     */
    private function send_to_external_service($error) {
        // Sentry, Bugsnag, LogRocket等への送信
        // 実装は使用するサービスに応じて
        do_action('flp_send_error_to_external', $error);
    }
    
    /**
     * 管理者への通知
     */
    private function notify_admin($error) {
        // 通知の頻度制限
        $last_notified = get_transient('flp_last_error_notification');
        if ($last_notified && (time() - $last_notified) < 3600) {
            return; // 1時間に1回まで
        }
        
        $admin_email = get_option('admin_email');
        $subject = sprintf(
            '[%s] 致命的エラーが発生しました',
            get_bloginfo('name')
        );
        
        $message = sprintf(
            "サイトで致命的なエラーが発生しました。\n\n" .
            "エラー: %s\n" .
            "ファイル: %s\n" .
            "行: %d\n" .
            "時刻: %s\n\n" .
            "詳細は管理画面のエラーログをご確認ください。\n%s",
            $error['message'],
            $error['file'] ?? 'Unknown',
            $error['line'] ?? 0,
            $error['timestamp'],
            admin_url('edit.php?post_type=flp_lp&page=flp_error_logs')
        );
        
        wp_mail($admin_email, $subject, $message);
        set_transient('flp_last_error_notification', time(), 3600);
    }
    
    /**
     * エラーサマリーの保存
     */
    private function save_error_summary() {
        if (empty($this->errors)) {
            return;
        }
        
        $summary = [
            'total' => count($this->errors),
            'by_level' => [],
            'timestamp' => current_time('mysql')
        ];
        
        foreach ($this->errors as $error) {
            $level = $error['level'];
            if (!isset($summary['by_level'][$level])) {
                $summary['by_level'][$level] = 0;
            }
            $summary['by_level'][$level]++;
        }
        
        update_option('flp_last_error_summary', $summary);
    }
    
    /**
     * 一時ファイルのクリーンアップ
     */
    private function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/flp-temp/';
        
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '*');
            $threshold = time() - 3600; // 1時間以上前
            
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $threshold) {
                    @unlink($file);
                }
            }
        }
    }
    
    /**
     * データベーステーブルの修復
     */
    private function repair_database_tables() {
        global $wpdb;
        
        // プラグインのテーブルをチェック
        $tables = [
            $this->table_name,
            $wpdb->prefix . 'flp_conversions',
            $wpdb->prefix . 'flp_analytics'
        ];
        
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                $wpdb->query("REPAIR TABLE $table");
                $wpdb->query("OPTIMIZE TABLE $table");
            }
        }
    }
    
    /**
     * メモリ制限の増加
     */
    private function increase_memory_limit() {
        $current_limit = ini_get('memory_limit');
        $current_limit_int = wp_convert_hr_to_bytes($current_limit);
        
        // 256MBまで増やす
        $new_limit = 256 * 1024 * 1024;
        
        if ($current_limit_int < $new_limit) {
            @ini_set('memory_limit', '256M');
        }
    }
    
    /**
     * 古いログのクリーンアップ
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        // 設定から保持期間を取得（デフォルト30日）
        $retention_days = get_option('flp_error_log_retention', 30);
        
        // データベースログのクリーンアップ
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} 
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_days
            )
        );
        
        // ファイルログのクリーンアップ
        $log_dir = WP_CONTENT_DIR . '/flp-logs/';
        if (is_dir($log_dir)) {
            $files = glob($log_dir . '*.log');
            $threshold = strtotime("-{$retention_days} days");
            
            foreach ($files as $file) {
                if (filemtime($file) < $threshold) {
                    @unlink($file);
                }
            }
        }
        
        // 削除済みエラーのメタデータクリーンアップ
        $users = get_users(['fields' => 'ID']);
        foreach ($users as $user_id) {
            $dismissed = get_user_meta($user_id, 'flp_dismissed_errors', true);
            if (is_array($dismissed) && count($dismissed) > 100) {
                // 最新100件のみ保持
                update_user_meta($user_id, 'flp_dismissed_errors', array_slice($dismissed, -100));
            }
        }
        
        // クリーンアップ結果をログ
        if ($deleted > 0) {
            $this->log(
                sprintf(__('%d件の古いエラーログを削除しました。', 'finelive-lp'), $deleted),
                self::LEVEL_NOTICE,
                'CLEANUP_SUCCESS'
            );
        }
    }
}

/**
 * ===================================
 * 2. カスタム例外クラス
 * includes/utils/class-flp-exception.php
 * ===================================
 */
class FLP_Exception extends Exception {
    
    protected $error_code;
    protected $data;
    
    public function __construct($message = "", $error_code = null, $data = [], $previous = null) {
        $this->error_code = $error_code;
        $this->data = $data;
        
        $code = 0;
        if ($error_code && isset(FLP_Error_Handler::ERROR_CODES[$error_code])) {
            $code = FLP_Error_Handler::ERROR_CODES[$error_code];
        }
        
        parent::__construct($message, $code, $previous);
    }
    
    public function getErrorCode() {
        return $this->error_code;
    }
    
    public function getData() {
        return $this->data;
    }
    
    public function toArray() {
        return [
            'message' => $this->getMessage(),
            'code' => $this->error_code,
            'data' => $this->data,
            'file' => $this->getFile(),
            'line' => $this->getLine()
        ];
    }
}

/**
 * ===================================
 * 3. エラー結果クラス
 * includes/utils/class-flp-error.php
 * ===================================
 */
class FLP_Error {
    
    private $message;
    private $code;
    private $data;
    
    public function __construct($message, $code = null, $data = []) {
        $this->message = $message;
        $this->code = $code;
        $this->data = $data;
    }
    
    public function get_error_message() {
        return $this->message;
    }
    
    public function get_error_code() {
        return $this->code;
    }
    
    public function get_error_data($key = null) {
        if ($key !== null) {
            return isset($this->data[$key]) ? $this->data[$key] : null;
        }
        return $this->data;
    }
    
    public function add_data($data, $key = null) {
        if ($key !== null) {
            $this->data[$key] = $data;
        } else {
            $this->data = array_merge($this->data, (array) $data);
        }
    }
    
    public function is_error() {
        return true;
    }
    
    public function to_wp_error() {
        return new WP_Error($this->code, $this->message, $this->data);
    }
}

/**
 * ===================================
 * 4. Try-Catchハンドラー
 * includes/utils/class-flp-try-catch-handler.php
 * ===================================
 */
class FLP_Try_Catch_Handler {
    
    /**
     * 安全な実行（例外をキャッチしてエラーハンドリング）
     */
    public static function safe_execute($callback, $error_message = null, $default_return = null) {
        try {
            return call_user_func($callback);
        } catch (Exception $e) {
            $error_handler = FLP_Error_Handler::instance();
            
            // エラーログに記録
            $error_handler->log(
                $error_message ?: $e->getMessage(),
                FLP_Error_Handler::LEVEL_ERROR,
                $e instanceof FLP_Exception ? $e->getErrorCode() : null,
                [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            );
            
            // デバッグモードの場合は例外を再スロー
            if ($error_handler->is_debug_mode()) {
                throw $e;
            }
            
            return $default_return;
        }
    }
    
    /**
     * AJAX安全実行
     */
    public static function ajax_safe_execute($callback) {
        try {
            return call_user_func($callback);
        } catch (Exception $e) {
            $error_handler = FLP_Error_Handler::instance();
            
            $error_data = [
                'message' => __('処理中にエラーが発生しました。', 'finelive-lp'),
                'code' => $e instanceof FLP_Exception ? $e->getErrorCode() : 'UNKNOWN_ERROR'
            ];
            
            // デバッグモードの場合は詳細情報を含める
            if ($error_handler->is_debug_mode()) {
                $error_data['debug'] = [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString())
                ];
            }
            
            // エラーログに記録
            $error_handler->log(
                $e->getMessage(),
                FLP_Error_Handler::LEVEL_ERROR,
                $e instanceof FLP_Exception ? $e->getErrorCode() : null,
                ['ajax_action' => $_REQUEST['action'] ?? 'unknown']
            );
            
            wp_send_json_error($error_data);
        }
    }
    
    /**
     * データベース操作の安全実行
     */
    public static function db_safe_execute($callback, $operation = 'database operation') {
        global $wpdb;
        
        // 既にトランザクション中の場合はそのまま実行
        $in_transaction = $wpdb->get_var("SELECT @@autocommit") === '0';
        
        try {
            if (!$in_transaction) {
                $wpdb->query('START TRANSACTION');
            }
            
            $result = call_user_func($callback);
            
            if (!$in_transaction) {
                $wpdb->query('COMMIT');
            }
            
            return $result;
            
        } catch (Exception $e) {
            if (!$in_transaction) {
                $wpdb->query('ROLLBACK');
            }
            
            throw new FLP_Exception(
                sprintf(__('データベース操作に失敗しました: %s', 'finelive-lp'), $operation),
                'DATABASE_ERROR',
                [
                    'operation' => $operation,
                    'original_error' => $e->getMessage(),
                    'query_error' => $wpdb->last_error
                ]
            );
        }
    }
    
    /**
     * ファイル操作の安全実行
     */
    public static function file_safe_execute($callback, $file_path = null) {
        try {
            return call_user_func($callback);
        } catch (Exception $e) {
            throw new FLP_Exception(
                sprintf(__('ファイル操作に失敗しました: %s', 'finelive-lp'), basename($file_path ?: 'unknown')),
                'FILE_OPERATION_FAILED',
                [
                    'file' => $file_path,
                    'error' => $e->getMessage(),
                    'permissions' => $file_path ? substr(sprintf('%o', fileperms($file_path)), -4) : null
                ]
            );
        }
    }
    
    /**
     * リトライ機能付き安全実行
     */
    public static function safe_execute_with_retry($callback, $max_attempts = 3, $delay = 1, $backoff = 2) {
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
                    $delay *= $backoff; // 指数バックオフ
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
                FLP_Error_Handler::LEVEL_ERROR,
                'RETRY_EXHAUSTED'
            );
            
            throw $last_exception;
        }
    }
    
    /**
     * バッチ処理の安全実行
     */
    public static function batch_safe_execute($items, $callback, $batch_size = 100, $continue_on_error = true) {
        $results = [];
        $errors = [];
        $processed = 0;
        
        $chunks = array_chunk($items, $batch_size);
        
        foreach ($chunks as $chunk_index => $chunk) {
            foreach ($chunk as $item_index => $item) {
                $global_index = ($chunk_index * $batch_size) + $item_index;
                
                try {
                    $results[$global_index] = call_user_func($callback, $item, $global_index);
                    $processed++;
                } catch (Exception $e) {
                    $errors[$global_index] = [
                        'item' => $item,
                        'error' => $e->getMessage(),
                        'code' => $e instanceof FLP_Exception ? $e->getErrorCode() : $e->getCode()
                    ];
                    
                    if (!$continue_on_error) {
                        break 2; // 両方のループを抜ける
                    }
                }
            }
            
            // メモリ解放
            if ($chunk_index % 10 === 0) {
                wp_cache_flush();
                
                // 進捗の記録
                set_transient('flp_batch_progress', [
                    'processed' => $processed,
                    'total' => count($items),
                    'errors' => count($errors)
                ], 300);
            }
        }
        
        delete_transient('flp_batch_progress');
        
        return [
            'success' => $results,
            'errors' => $errors,
            'total' => count($items),
            'succeeded' => count($results),
            'failed' => count($errors),
            'completed' => $processed === count($items)
        ];
    }
    
    /**
     * 非同期処理の安全実行
     */
    public static function async_safe_execute($callback, $args = [], $priority = 10) {
        // ユニークなアクション名を生成
        $action_name = 'flp_async_task_' . wp_generate_uuid4();
        
        // 一時的なアクションを登録
        add_action($action_name, function() use ($callback, $args, $action_name) {
            try {
                call_user_func_array($callback, $args);
            } catch (Exception $e) {
                FLP_Error_Handler::instance()->log(
                    sprintf(__('非同期タスクの実行に失敗しました: %s', 'finelive-lp'), $e->getMessage()),
                    FLP_Error_Handler::LEVEL_ERROR,
                    'ASYNC_TASK_FAILED',
                    ['action' => $action_name]
                );
            } finally {
                // アクションを削除
                remove_all_actions($action_name);
            }
        });
        
        // WordPressのクーロンジョブとして登録
        wp_schedule_single_event(time() + 1, $action_name);
        
        return $action_name;
    }
    
    /**
     * タイムアウト付き安全実行
     */
    public static function safe_execute_with_timeout($callback, $timeout = 30) {
        $start_time = microtime(true);
        
        // タイムアウトを設定
        $old_timeout = ini_get('max_execution_time');
        set_time_limit($timeout);
        
        try {
            // タイムアウトチェック用のシャットダウン関数
            register_shutdown_function(function() use ($start_time, $timeout) {
                $execution_time = microtime(true) - $start_time;
                if ($execution_time >= $timeout) {
                    throw new FLP_Exception(
                        __('処理がタイムアウトしました', 'finelive-lp'),
                        'TIMEOUT',
                        ['execution_time' => $execution_time, 'timeout' => $timeout]
                    );
                }
            });
            
            $result = call_user_func($callback);
            
            // タイムアウトを元に戻す
            set_time_limit($old_timeout);
            
            return $result;
            
        } catch (Exception $e) {
            set_time_limit($old_timeout);
            throw $e;
        }
    }
}

/**
 * ===================================
 * 5. エラーログビューア管理画面
 * includes/admin/class-flp-error-log-viewer.php
 * ===================================
 */
class FLP_Error_Log_Viewer {
    
    /**
     * シングルトンインスタンス
     */
    private static $instance = null;
    
    /**
     * エラーハンドラーインスタンス
     */
    private $error_handler;
    
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
        $this->error_handler = FLP_Error_Handler::instance();
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
        add_action('wp_ajax_flp_get_error_chart_data', [$this, 'ajax_get_chart_data']);
        add_action('wp_ajax_flp_resolve_error', [$this, 'ajax_resolve_error']);
    }
    
    /**
     * 管理メニューの追加
     */
    public function add_menu_page() {
        $error_count = $this->get_unresolved_error_count();
        $menu_title = __('エラーログ', 'finelive-lp');
        
        if ($error_count > 0) {
            $menu_title .= ' <span class="update-plugins count-' . $error_count . '"><span class="plugin-count">' . $error_count . '</span></span>';
        }
        
        add_submenu_page(
            'edit.php?post_type=flp_lp',
            __('エラーログ', 'finelive-lp'),
            $menu_title,
            'manage_options',
            'flp_error_logs',
            [$this, 'render_page']
        );
    }
    
    /**
     * スクリプトとスタイルのエンキュー
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'flp_lp_page_flp_error_logs') {
            return;
        }
        
        // Chart.js
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            [],
            '4.4.0'
        );
        
        // DataTables
        wp_enqueue_script(
            'datatables',
            'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js',
            ['jquery'],
            '1.13.6'
        );
        wp_enqueue_style(
            'datatables',
            'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css',
            [],
            '1.13.6'
        );
        
        // カスタムスタイル
        wp_add_inline_style('datatables', $this->get_custom_styles());
        
        // カスタムスクリプト
        wp_add_inline_script('datatables', $this->get_custom_scripts(), 'after');
        
        // ローカライズ
        wp_localize_script('datatables', 'flp_error_log', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('flp_error_log'),
            'strings' => [
                'confirm_clear' => __('すべてのエラーログを削除してもよろしいですか？', 'finelive-lp'),
                'exporting' => __('エクスポート中...', 'finelive-lp'),
                'loading' => __('読み込み中...', 'finelive-lp'),
                'error' => __('エラーが発生しました', 'finelive-lp')
            ]
        ]);
    }
    
    /**
     * カスタムスタイルの取得
     */
    private function get_custom_styles() {
        return '
        .flp-error-log-page {
            max-width: 1400px;
        }
        
        .flp-error-dashboard {
            margin: 20px 0;
        }
        
        .error-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .stat-card.critical {
            border-left: 4px solid #dc3545;
        }
        
        .stat-card.error {
            border-left: 4px solid #fd7e14;
        }
        
        .stat-card.warning {
            border-left: 4px solid #ffc107;
        }
        
        .stat-card.notice {
            border-left: 4px solid #0dcaf0;
        }
        
        .stat-icon {
            font-size: 40px;
            margin-right: 20px;
            opacity: 0.8;
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            line-height: 1;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .error-charts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
        }
        
        .chart-container h3 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 16px;
            color: #333;
        }
        
        .error-level-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .level-critical {
            background: #dc3545;
            color: white;
        }
        
        .level-error {
            background: #fd7e14;
            color: white;
        }
        
        .level-warning {
            background: #ffc107;
            color: #333;
        }
        
        .level-notice {
            background: #0dcaf0;
            color: white;
        }
        
        .level-debug {
            background: #6c757d;
            color: white;
        }
        
        .flp-modal {
            display: none;
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }
        
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            border-radius: 8px;
            position: relative;
            animation: slideIn 0.3s;
        }
        
        .modal-content .close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 28px;
            cursor: pointer;
            color: #999;
        }
        
        .modal-content .close:hover {
            color: #333;
        }
        
        .error-detail-section {
            margin-bottom: 30px;
        }
        
        .error-detail-section h3 {
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        
        .error-message-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            font-family: monospace;
            font-size: 13px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        
        .error-detail-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .frequent-errors table {
            margin-top: 15px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .action-buttons .spinner {
            float: none;
            margin: 0;
        }
        ';
    }
    
    /**
     * カスタムスクリプトの取得
     */
    private function get_custom_scripts() {
        return '
        jQuery(document).ready(function($) {
            // DataTables初期化
            var errorTable = $("#error-log-table").DataTable({
                order: [[1, "desc"]],
                pageLength: 25,
                responsive: true,
                language: {
                    search: "検索:",
                    lengthMenu: "_MENU_ 件表示",
                    info: "_TOTAL_ 件中 _START_ - _END_ を表示",
                    paginate: {
                        first: "最初",
                        last: "最後",
                        next: "次",
                        previous: "前"
                    }
                }
            });
            
            // エラー詳細モーダル
            $(document).on("click", ".view-details", function() {
                var errorId = $(this).data("error-id");
                var $modal = $("#error-details-modal");
                var $content = $("#error-details-content");
                
                $content.html(\'<div class="spinner is-active"></div>\');
                $modal.fadeIn();
                
                $.post(flp_error_log.ajax_url, {
                    action: "flp_get_error_details",
                    error_id: errorId,
                    nonce: flp_error_log.nonce
                }, function(response) {
                    if (response.success) {
                        $content.html(response.data.html);
                    } else {
                        $content.html(\'<p class="error">\' + flp_error_log.strings.error + \'</p>\');
                    }
                });
            });
            
            // モーダルを閉じる
            $(".flp-modal .close, .flp-modal").on("click", function(e) {
                if (e.target === this) {
                    $(this).closest(".flp-modal").fadeOut();
                }
            });
            
            // ログクリア
            $("#flp-clear-logs").on("click", function() {
                if (!confirm(flp_error_log.strings.confirm_clear)) {
                    return;
                }
                
                var $button = $(this);
                $button.prop("disabled", true).text(flp_error_log.strings.loading);
                
                $.post(flp_error_log.ajax_url, {
                    action: "flp_clear_error_logs",
                    nonce: flp_error_log.nonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(flp_error_log.strings.error);
                        $button.prop("disabled", false).text("ログクリア");
                    }
                });
            });
            
            // エクスポート
            $("#flp-export-logs").on("click", function() {
                var $button = $(this);
                $button.prop("disabled", true).text(flp_error_log.strings.exporting);
                
                // ダウンロード用のフォームを作成
                var form = $(\'<form>\', {
                    action: flp_error_log.ajax_url,
                    method: "POST"
                }).append(
                    $(\'<input>\', {name: "action", value: "flp_export_error_logs"}),
                    $(\'<input>\', {name: "nonce", value: flp_error_log.nonce})
                );
                
                form.appendTo("body").submit().remove();
                
                setTimeout(function() {
                    $button.prop("disabled", false).text("エクスポート");
                }, 2000);
            });
            
            // リフレッシュ
            $("#flp-refresh-logs").on("click", function() {
                location.reload();
            });
            
            // エラー通知の非表示
            $(".flp-error-notice.is-dismissible").on("click", ".notice-dismiss", function() {
                var $notice = $(this).closest(".notice");
                var errorId = $notice.data("error-id");
                
                if (errorId) {
                    $.post(flp_error_log.ajax_url, {
                        action: "flp_dismiss_error",
                        error_id: errorId,
                        nonce: flp_error_log.nonce
                    });
                }
            });
            
            // チャートの初期化
            if (typeof Chart !== "undefined" && errorTimelineData && errorLevelData) {
                initializeCharts();
            }
        });
        
        function initializeCharts() {
            // タイムラインチャート
            var timelineCtx = document.getElementById("errorTrendChart");
            if (timelineCtx) {
                new Chart(timelineCtx.getContext("2d"), {
                    type: "line",
                    data: {
                        labels: errorTimelineData.map(item => item.date),
                        datasets: [{
                            label: "エラー数",
                            data: errorTimelineData.map(item => item.total),
                            borderColor: "#007cba",
                            backgroundColor: "rgba(0, 124, 186, 0.1)",
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
            
            // エラーレベル別チャート
            var levelCtx = document.getElementById("errorTypeChart");
            if (levelCtx) {
                var levelData = {
                    labels: [],
                    data: [],
                    colors: []
                };
                
                var colorMap = {
                    critical: "#dc3545",
                    error: "#fd7e14",
                    warning: "#ffc107",
                    notice: "#0dcaf0",
                    debug: "#6c757d"
                };
                
                for (var level in errorLevelData) {
                    if (errorLevelData[level] && errorLevelData[level].count > 0) {
                        levelData.labels.push(level.charAt(0).toUpperCase() + level.slice(1));
                        levelData.data.push(errorLevelData[level].count);
                        levelData.colors.push(colorMap[level] || "#999");
                    }
                }
                
                new Chart(levelCtx.getContext("2d"), {
                    type: "doughnut",
                    data: {
                        labels: levelData.labels,
                        datasets: [{
                            data: levelData.data,
                            backgroundColor: levelData.colors
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: "bottom"
                            }
                        }
                    }
                });
            }
        }
        ';
    }
    
    /**
     * 管理画面の描画
     */
    public function render_page() {
        $stats = $this->error_handler->get_error_stats();
        $recent_errors = $this->error_handler->get_recent_errors(100);
        
        ?>
        <div class="wrap flp-error-log-page">
            <h1>
                <?php _e('FineLive LP エラーログ', 'finelive-lp'); ?>
            </h1>
            
            <div class="action-buttons">
                <button class="button" id="flp-refresh-logs">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('更新', 'finelive-lp'); ?>
                </button>
                <button class="button" id="flp-export-logs">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('エクスポート', 'finelive-lp'); ?>
                </button>
                <button class="button" id="flp-clear-logs">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('ログクリア', 'finelive-lp'); ?>
                </button>
            </div>
            
            <!-- エラー統計ダッシュボード -->
            <div class="flp-error-dashboard">
                <div class="error-stats-grid">
                    <div class="stat-card critical">
                        <div class="stat-icon">&#128680;</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo intval($stats['by_level']->critical->count ?? 0); ?></div>
                            <div class="stat-label"><?php _e('致命的エラー', 'finelive-lp'); ?></div>
                        </div>
                    </div>
                    
                    <div class="stat-card error">
                        <div class="stat-icon">&#10060;</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo intval($stats['by_level']->error->count ?? 0); ?></div>
                            <div class="stat-label"><?php _e('エラー', 'finelive-lp'); ?></div>
                        </div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-icon">&#9888;&#65039;</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo intval($stats['by_level']->warning->count ?? 0); ?></div>
                            <div class="stat-label"><?php _e('警告', 'finelive-lp'); ?></div>
                        </div>
                    </div>
                    
                    <div class="stat-card notice">
                        <div class="stat-icon">&#8505;&#65039;</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo intval($stats['by_level']->notice->count ?? 0); ?></div>
                            <div class="stat-label"><?php _e('通知', 'finelive-lp'); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- エラー発生トレンドチャート -->
                <div class="error-charts">
                    <div class="chart-container">
                        <h3><?php _e('過去30日間のエラー発生状況', 'finelive-lp'); ?></h3>
                        <canvas id="errorTrendChart" height="200"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h3><?php _e('エラータイプ別分布', 'finelive-lp'); ?></h3>
                        <canvas id="errorTypeChart" height="200"></canvas>
                    </div>
                </div>
                
                <!-- 頻出エラー -->
                <?php if (!empty($stats['frequent'])): ?>
                <div class="frequent-errors">
                    <h3><?php _e('頻出エラー TOP10', 'finelive-lp'); ?></h3>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('エラーメッセージ', 'finelive-lp'); ?></th>
                                <th style="width: 150px;"><?php _e('エラーコード', 'finelive-lp'); ?></th>
                                <th style="width: 100px;"><?php _e('発生回数', 'finelive-lp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['frequent'] as $error): ?>
                            <tr>
                                <td>
                                    <?php
                                    $message = wp_kses_post($error->error_message);
                                    echo mb_strlen($message) > 100 ? mb_substr($message, 0, 100) . '...' : $message;
                                    ?>
                                </td>
                                <td>
                                    <?php if ($error->error_code): ?>
                                        <code><?php echo esc_html($error->error_code); ?></code>
                                    <?php else: ?>
                                        &#8212;
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($error->count); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- エラーログテーブル -->
            <div class="flp-error-log-table">
                <h2><?php _e('エラーログ', 'finelive-lp'); ?></h2>
                
                <table id="error-log-table" class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="column-level" style="width: 100px;"><?php _e('レベル', 'finelive-lp'); ?></th>
                            <th class="column-time" style="width: 150px;"><?php _e('発生時刻', 'finelive-lp'); ?></th>
                            <th class="column-message"><?php _e('エラーメッセージ', 'finelive-lp'); ?></th>
                            <th class="column-location" style="width: 200px;"><?php _e('発生場所', 'finelive-lp'); ?></th>
                            <th class="column-user" style="width: 120px;"><?php _e('ユーザー', 'finelive-lp'); ?></th>
                            <th class="column-actions" style="width: 80px;"><?php _e('操作', 'finelive-lp'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_errors as $error): ?>
                        <tr data-error-id="<?php echo esc_attr($error['id']); ?>">
                            <td class="column-level">
                                <span class="error-level-badge level-<?php echo esc_attr($error['error_level']); ?>">
                                    <?php echo esc_html(ucfirst($error['error_level'])); ?>
                                </span>
                            </td>
                            <td class="column-time" data-order="<?php echo esc_attr($error['created_at']); ?>">
                                <?php echo esc_html(human_time_diff(strtotime($error['created_at']))); ?> 前
                                <br>
                                <small><?php echo esc_html($error['created_at']); ?></small>
                            </td>
                            <td class="column-message">
                                <strong>
                                    <?php
                                    $message = wp_kses_post($error['error_message']);
                                    echo mb_strlen($message) > 100 ? mb_substr($message, 0, 100) . '...' : $message;
                                    ?>
                                </strong>
                                <?php if ($error['error_code']): ?>
                                    <br><code><?php echo esc_html($error['error_code']); ?></code>
                                <?php endif; ?>
                            </td>
                            <td class="column-location">
                                <?php if ($error['error_file']): ?>
                                    <code title="<?php echo esc_attr($error['error_file']); ?>">
                                        <?php echo esc_html(basename($error['error_file'])); ?>:<?php echo esc_html($error['error_line']); ?>
                                    </code>
                                <?php else: ?>
                                    &#8212;
                                <?php endif; ?>
                            </td>
                            <td class="column-user">
                                <?php 
                                if ($error['user_id']) {
                                    $user = get_user_by('id', $error['user_id']);
                                    if ($user) {
                                        echo esc_html($user->display_name);
                                    } else {
                                        echo 'ID: ' . esc_html($error['user_id']);
                                    }
                                } else {
                                    echo '&#8212;';
                                }
                                ?>
                            </td>
                            <td class="column-actions">
                                <button class="button button-small view-details" data-error-id="<?php echo esc_attr($error['id']); ?>">
                                    <?php _e('詳細', 'finelive-lp'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- エラー詳細モーダル -->
        <div id="error-details-modal" class="flp-modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2><?php _e('エラー詳細', 'finelive-lp'); ?></h2>
                <div id="error-details-content">
                    <!-- AJAXで内容を読み込み -->
                </div>
            </div>
        </div>
        
        <script>
        // チャートデータの準備
        var errorTimelineData = <?php echo json_encode($stats['timeline']); ?>;
        var errorLevelData = <?php echo json_encode($stats['by_level']); ?>;
        </script>
        <?php
    }
    
    /**
     * エラー詳細の取得（AJAX）
     */
    public function ajax_get_error_details() {
        check_ajax_referer('flp_error_log', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $error_id = intval($_POST['error_id']);
        global $wpdb;
        
        $table = $wpdb->prefix . 'flp_error_logs';
        $error = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $error_id
        ), ARRAY_A);
        
        if (!$error) {
            wp_send_json_error(['message' => __('エラーが見つかりません', 'finelive-lp')]);
        }
        
        // コンテキストをデコード
        $context = json_decode($error['error_context'], true) ?: [];
        
        ob_start();
        ?>
        <div class="error-detail-section">
            <h3><?php _e('基本情報', 'finelive-lp'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><?php _e('レベル', 'finelive-lp'); ?></th>
                    <td>
                        <span class="error-level-badge level-<?php echo esc_attr($error['error_level']); ?>">
                            <?php echo esc_html(ucfirst($error['error_level'])); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('発生時刻', 'finelive-lp'); ?></th>
                    <td><?php echo esc_html($error['created_at']); ?></td>
                </tr>
                <tr>
                    <th><?php _e('エラーコード', 'finelive-lp'); ?></th>
                    <td>
                        <?php if ($error['error_code']): ?>
                            <code><?php echo esc_html($error['error_code']); ?></code>
                        <?php else: ?>
                            &#8212;
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('URL', 'finelive-lp'); ?></th>
                    <td>
                        <?php if ($error['url']): ?>
                            <code><?php echo esc_html($error['url']); ?></code>
                        <?php else: ?>
                            &#8212;
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('ユーザー', 'finelive-lp'); ?></th>
                    <td>
                        <?php 
                        if ($error['user_id']) {
                            $user = get_user_by('id', $error['user_id']);
                            if ($user) {
                                printf(
                                    '<a href="%s">%s</a> (ID: %d)',
                                    get_edit_user_link($error['user_id']),
                                    esc_html($user->display_name),
                                    $error['user_id']
                                );
                            } else {
                                echo 'ID: ' . esc_html($error['user_id']) . ' (削除済み)';
                            }
                        } else {
                            echo __('ゲスト', 'finelive-lp');
                        }
                        ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="error-detail-section">
            <h3><?php _e('エラーメッセージ', 'finelive-lp'); ?></h3>
            <pre class="error-message-box"><?php echo esc_html($error['error_message']); ?></pre>
        </div>
        
        <div class="error-detail-section">
            <h3><?php _e('発生場所', 'finelive-lp'); ?></h3>
            <p>
                <strong><?php _e('ファイル:', 'finelive-lp'); ?></strong> 
                <code><?php echo esc_html($error['error_file'] ?: '不明'); ?></code>
            </p>
            <p>
                <strong><?php _e('行番号:', 'finelive-lp'); ?></strong> 
                <code><?php echo esc_html($error['error_line'] ?: '不明'); ?></code>
            </p>
        </div>
        
        <?php if (!empty($context)): ?>
        <div class="error-detail-section">
            <h3><?php _e('実行環境', 'finelive-lp'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><?php _e('PHPバージョン', 'finelive-lp'); ?></th>
                    <td><?php echo esc_html($context['php_version'] ?? '&#8212;'); ?></td>
                </tr>
                <tr>
                    <th><?php _e('WordPressバージョン', 'finelive-lp'); ?></th>
                    <td><?php echo esc_html($context['wp_version'] ?? '&#8212;'); ?></td>
                </tr>
                <tr>
                    <th><?php _e('プラグインバージョン', 'finelive-lp'); ?></th>
                    <td><?php echo esc_html($context['plugin_version'] ?? '&#8212;'); ?></td>
                </tr>
                <tr>
                    <th><?php _e('テーマ', 'finelive-lp'); ?></th>
                    <td><?php echo esc_html($context['theme'] ?? '&#8212;'); ?></td>
                </tr>
                <tr>
                    <th><?php _e('メモリ使用量', 'finelive-lp'); ?></th>
                    <td>
                        <?php echo size_format($context['memory_usage'] ?? 0); ?> / 
                        <?php echo size_format($context['memory_peak'] ?? 0); ?> (ピーク) / 
                        <?php echo esc_html($context['memory_limit'] ?? '&#8212;'); ?> (上限)
                    </td>
                </tr>
                <tr>
                    <th><?php _e('リクエストタイプ', 'finelive-lp'); ?></th>
                    <td>
                        <?php echo esc_html($context['request_method'] ?? '&#8212;'); ?>
                        <?php if (!empty($context['is_ajax'])): ?>
                            <span class="badge">AJAX</span>
                        <?php endif; ?>
                        <?php if (!empty($context['is_cron'])): ?>
                            <span class="badge">CRON</span>
                        <?php endif; ?>
                        <?php if (!empty($context['is_rest'])): ?>
                            <span class="badge">REST API</span>
                        <?php endif; ?>
                        <?php if (!empty($context['is_admin'])): ?>
                            <span class="badge">管理画面</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('サーバー', 'finelive-lp'); ?></th>
                    <td><?php echo esc_html($context['server_software'] ?? '&#8212;'); ?></td>
                </tr>
                <tr>
                    <th><?php _e('PHP SAPI', 'finelive-lp'); ?></th>
                    <td><?php echo esc_html($context['php_sapi'] ?? '&#8212;'); ?></td>
                </tr>
                <?php if (!empty($context['user_agent'])): ?>
                <tr>
                    <th><?php _e('ユーザーエージェント', 'finelive-lp'); ?></th>
                    <td><small><?php echo esc_html($context['user_agent']); ?></small></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($context['referer'])): ?>
                <tr>
                    <th><?php _e('リファラー', 'finelive-lp'); ?></th>
                    <td><small><?php echo esc_html($context['referer']); ?></small></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="error-detail-actions">
            <button class="button button-primary" onclick="window.open('https://www.google.com/search?q=<?php echo urlencode($error['error_message']); ?>', '_blank')">
                <span class="dashicons dashicons-search"></span>
                <?php _e('解決策を検索', 'finelive-lp'); ?>
            </button>
            <button class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($error['error_message']); ?>'); alert('コピーしました');">
                <span class="dashicons dashicons-clipboard"></span>
                <?php _e('エラーをコピー', 'finelive-lp'); ?>
            </button>
            <?php if ($this->error_handler->is_debug_mode()): ?>
            <button class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js(json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?>'); alert('コピーしました');">
                <span class="dashicons dashicons-code-standards"></span>
                <?php _e('JSON形式でコピー', 'finelive-lp'); ?>
            </button>
            <?php endif; ?>
        </div>
        <?php
        
        wp_send_json_success(['html' => ob_get_clean()]);
    }
    
    /**
     * ログのクリア（AJAX）
     */
    public function ajax_clear_logs() {
        check_ajax_referer('flp_error_log', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'flp_error_logs';
        
        // 7日以上前のログのみ削除（安全のため）
        $deleted = $wpdb->query(
            "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // ファイルログも削除
        $log_dir = WP_CONTENT_DIR . '/flp-logs/';
        if (is_dir($log_dir)) {
            $files = glob($log_dir . '*.log');
            $threshold = strtotime('-7 days');
            
            foreach ($files as $file) {
                if (filemtime($file) < $threshold) {
                    @unlink($file);
                }
            }
        }
        
        wp_send_json_success([
            'message' => sprintf(
                __('%d件の古いログを削除しました。', 'finelive-lp'),
                $deleted
            )
        ]);
    }
    
    /**
     * ログのエクスポート（AJAX）
     */
    public function ajax_export_logs() {
        check_ajax_referer('flp_error_log', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'flp_error_logs';
        
        // フィルター条件
        $level = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        
        $where = [];
        $values = [];
        
        if ($level) {
            $where[] = 'error_level = %s';
            $values[] = $level;
        }
        
        if ($date_from) {
            $where[] = 'created_at >= %s';
            $values[] = $date_from . ' 00:00:00';
        }
        
        if ($date_to) {
            $where[] = 'created_at <= %s';
            $values[] = $date_to . ' 23:59:59';
        }
        
        $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = "SELECT * FROM $table $where_clause ORDER BY created_at DESC";
        if ($values) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $errors = $wpdb->get_results($query, ARRAY_A);
        
        // CSVヘッダー
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=flp-error-logs-' . date('Y-m-d-His') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');
        
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
            'ユーザー名',
            '発生時刻',
            'PHPバージョン',
            'WPバージョン',
            'メモリ使用量'
        ]);
        
        // データ行
        foreach ($errors as $error) {
            $context = json_decode($error['error_context'], true) ?: [];
            $user = $error['user_id'] ? get_user_by('id', $error['user_id']) : null;
            
            fputcsv($output, [
                $error['id'],
                $error['error_level'],
                $error['error_code'],
                $error['error_message'],
                $error['error_file'],
                $error['error_line'],
                $error['url'],
                $error['user_id'],
                $user ? $user->display_name : '',
                $error['created_at'],
                $context['php_version'] ?? '',
                $context['wp_version'] ?? '',
                isset($context['memory_usage']) ? size_format($context['memory_usage']) : ''
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * 未解決エラー数の取得
     */
    private function get_unresolved_error_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'flp_error_logs';
        
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM $table 
             WHERE error_level IN ('critical', 'error') 
             AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
    }
}

/**
 * ===================================
 * 6. エラーページテンプレート
 * templates/error-page.php
 * ===================================
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php _e('エラーが発生しました', 'finelive-lp'); ?> - <?php bloginfo('name'); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .error-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .error-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        h1 {
            margin: 0 0 15px;
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .error-message {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
            line-height: 1.8;
        }
        
        .error-code {
            display: inline-block;
            background: #f8f9fa;
            color: #dc3545;
            padding: 4px 12px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
            margin: 10px 0;
        }
        
        .error-details {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
            text-align: left;
            font-family: monospace;
            font-size: 13px;
            color: #495057;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .error-details::-webkit-scrollbar {
            width: 8px;
        }
        
        .error-details::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .error-details::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        .error-details::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .error-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        
        .button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .button-primary {
            background: linear-gradient(135deg, #007cba 0%, #005a87 100%);
            color: white;
            box-shadow: 0 2px 10px rgba(0, 124, 186, 0.3);
        }
        
        .button-primary:hover {
            background: linear-gradient(135deg, #005a87 0%, #004166 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 124, 186, 0.4);
        }
        
        .button-secondary {
            background: #6c757d;
            color: white;
        }
        
        .button-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .help-text {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }
        
        .help-text a {
            color: #007cba;
            text-decoration: none;
        }
        
        .help-text a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .error-container {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .error-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .button {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">&#9888;&#65039;</div>
        
        <h1><?php _e('申し訳ございません', 'finelive-lp'); ?></h1>
        
        <div class="error-message">
            <?php if ($is_admin && $can_see_details): ?>
                <p><?php echo esc_html($error['message'] ?? __('予期しないエラーが発生しました。', 'finelive-lp')); ?></p>
                <?php if (!empty($error['code'])): ?>
                    <span class="error-code">
                        <?php _e('エラーコード:', 'finelive-lp'); ?> <?php echo esc_html($error['code']); ?>
                    </span>
                <?php endif; ?>
            <?php else: ?>
                <p><?php _e('一時的な問題が発生しています。', 'finelive-lp'); ?></p>
                <p><?php _e('しばらくしてから再度お試しください。', 'finelive-lp'); ?></p>
            <?php endif; ?>
        </div>
        
        <?php if ($can_see_details && $error_handler->is_debug_mode()): ?>
            <div class="error-details">
<?php _e('エラータイプ:', 'finelive-lp'); ?> <?php echo esc_html($error['type'] ?? 'Unknown'); ?>

<?php _e('ファイル:', 'finelive-lp'); ?> <?php echo esc_html($error['file'] ?? 'Unknown'); ?>

<?php _e('行:', 'finelive-lp'); ?> <?php echo esc_html($error['line'] ?? 'Unknown'); ?>

<?php if (!empty($error['trace'])): ?>

<?php _e('スタックトレース:', 'finelive-lp'); ?>
<?php echo esc_html($error['trace']); ?>
<?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="error-actions">
            <?php if ($is_admin): ?>
                <a href="<?php echo admin_url(); ?>" class="button button-primary">
                    <span class="dashicons dashicons-wordpress"></span>
                    <?php _e('管理画面に戻る', 'finelive-lp'); ?>
                </a>
                <a href="<?php echo admin_url('edit.php?post_type=flp_lp&page=flp_error_logs'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php _e('エラーログを確認', 'finelive-lp'); ?>
                </a>
            <?php else: ?>
                <a href="<?php echo home_url(); ?>" class="button button-primary">
                    <span class="dashicons dashicons-admin-home"></span>
                    <?php _e('ホームに戻る', 'finelive-lp'); ?>
                </a>
            <?php endif; ?>
            
            <button onclick="history.back()" class="button button-secondary">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php _e('前のページに戻る', 'finelive-lp'); ?>
            </button>
        </div>
        
        <div class="help-text">
            <?php _e('問題が続く場合は、', 'finelive-lp'); ?>
            <a href="mailto:<?php echo get_option('admin_email'); ?>">
                <?php _e('管理者にお問い合わせください', 'finelive-lp'); ?>
            </a>
        </div>
    </div>
    
    <?php if ($error_handler->is_debug_mode()): ?>
    <script>
        console.error('FineLive LP Error:', <?php echo json_encode($error); ?>);
    </script>
    <?php endif; ?>
</body>
</html>

<?php
/**
 * ===================================
 * 7. グローバルヘルパー関数
 * includes/helpers/error-functions.php
 * ===================================
 */

/**
 * エラーログ記録
 */
function flp_log_error($message, $level = 'error', $code = null, $data = []) {
    return FLP_Error_Handler::instance()->log($message, $level, $code, $data);
}

/**
 * デバッグログ
 */
function flp_debug($message, $data = []) {
    if (FLP_Error_Handler::instance()->is_debug_mode()) {
        return FLP_Error_Handler::instance()->log($message, FLP_Error_Handler::LEVEL_DEBUG, null, $data);
    }
}

/**
 * エラーチェック
 */
function flp_is_error($thing) {
    return $thing instanceof FLP_Error || $thing instanceof WP_Error || $thing instanceof Exception;
}

/**
 * 安全な実行
 */
function flp_safe_execute($callback, $default = null) {
    return FLP_Try_Catch_Handler::safe_execute($callback, null, $default);
}

/**
 * AJAX安全実行
 */
function flp_ajax_safe_execute($callback) {
    return FLP_Try_Catch_Handler::ajax_safe_execute($callback);
}

/**
 * ===================================
 * 8. 初期化
 * includes/class-flp-init.php に追加
 * ===================================
 */

// プラグインのメインファイルまたは初期化ファイルに追加
add_action('plugins_loaded', function() {
    // エラーハンドラーの初期化
    FLP_Error_Handler::instance();
    
    // 管理画面の場合はエラーログビューアも初期化
    if (is_admin()) {
        FLP_Error_Log_Viewer::instance();
    }
}, 1); // 優先度を高く設定

// プラグイン無効化時のクリーンアップ
register_deactivation_hook(FLP_PLUGIN_FILE, function() {
    // スケジュールされたイベントの削除
    wp_clear_scheduled_hook('flp_cleanup_error_logs');
    
    // 一時データの削除
    delete_transient('flp_last_error_notification');
    delete_transient('flp_batch_progress');
});