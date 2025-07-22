<?php
/**
 * クリック追跡専用クラス
 */

if (!defined('ABSPATH')) exit;

class FLP_Click_Tracking {

    /**
     * クリックデータのオプション名
     */
    const CLICK_DATA_OPTION = 'flp_lp_click_data';

    /**
     * 詳細ログのテーブル名（将来の拡張用）
     */
    private $table_name;

    /**
     * コンストラクタ
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'flp_click_logs';
        
        // AJAX アクションは FLP_Ajax クラスで登録済み
    }

    /**
     * クリック追跡の処理
     */
    public function handle_track_click() {
        // nonce チェック（フロントエンド用）
        $nonce = $_POST['_wpnonce'] ?? $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'flp_frontend_nonce')) {
            wp_send_json_error(array(
                'message' => __('セキュリティチェックに失敗しました。', 'finelive-lp'),
                'code' => 'invalid_nonce'
            ));
        }

        // 必要なデータの取得と検証
        $button_id = sanitize_text_field($_POST['btn'] ?? '');
        $lp_id = intval($_POST['lp_id'] ?? 0);

        if (empty($button_id) || empty($lp_id)) {
            wp_send_json_error(array(
                'message' => __('必要なデータが不足しています。', 'finelive-lp'),
                'code' => 'missing_data'
            ));
        }

        // LP の存在確認
        if (get_post_type($lp_id) !== 'flp_lp') {
            wp_send_json_error(array(
                'message' => __('無効なLP IDです。', 'finelive-lp'),
                'code' => 'invalid_lp_id'
            ));
        }

        try {
            // クリックデータの記録
            $this->record_click($lp_id, $button_id);

            // 詳細ログの記録（オプション）
            if (apply_filters('flp_enable_detailed_click_logging', false)) {
                $this->record_detailed_click_log($lp_id, $button_id);
            }

            wp_send_json_success(array(
                'message' => __('クリックが記録されました。', 'finelive-lp'),
                'lp_id' => $lp_id,
                'button_id' => $button_id
            ));

        } catch (Exception $e) {
            error_log('FLP Click Tracking Error: ' . $e->getMessage());
            
            wp_send_json_error(array(
                'message' => __('クリック記録中にエラーが発生しました。', 'finelive-lp'),
                'code' => 'recording_error'
            ));
        }
    }

    /**
     * シンプルなクリックデータの記録
     *
     * @param int $lp_id LP ID
     * @param string $button_id ボタンID
     */
    private function record_click($lp_id, $button_id) {
        $click_data = get_option(self::CLICK_DATA_OPTION, array());
        $current_date = current_time('Y-m-d');

        // データ構造: [lp_id][date][button_id] = count
        if (!isset($click_data[$lp_id])) {
            $click_data[$lp_id] = array();
        }
        
        if (!isset($click_data[$lp_id][$current_date])) {
            $click_data[$lp_id][$current_date] = array();
        }
        
        if (!isset($click_data[$lp_id][$current_date][$button_id])) {
            $click_data[$lp_id][$current_date][$button_id] = 0;
        }

        $click_data[$lp_id][$current_date][$button_id]++;

        // データをオプションに保存
        update_option(self::CLICK_DATA_OPTION, $click_data);

        /**
         * クリック記録後のアクション
         *
         * @param int $lp_id LP ID
         * @param string $button_id ボタンID
         * @param string $current_date 現在の日付
         * @param int $new_count 新しいクリック数
         */
        do_action('flp_click_recorded', $lp_id, $button_id, $current_date, $click_data[$lp_id][$current_date][$button_id]);
    }

    /**
     * 詳細なクリックログの記録
     *
     * @param int $lp_id LP ID
     * @param string $button_id ボタンID
     */
    private function record_detailed_click_log($lp_id, $button_id) {
        global $wpdb;

        // ユーザー情報の取得（プライバシーに配慮）
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $ip_address = $this->get_anonymized_ip();

        // セッション情報（簡易）
        $session_id = $this->generate_session_id();

        $log_data = array(
            'lp_id' => $lp_id,
            'button_id' => $button_id,
            'click_time' => current_time('mysql'),
            'user_agent' => substr($user_agent, 0, 255), // 長さ制限
            'referer' => esc_url_raw($referer),
            'ip_hash' => $ip_address, // ハッシュ化済み
            'session_id' => $session_id,
            'user_id' => get_current_user_id() ?: null,
        );

        // カスタムテーブルが存在する場合に記録
        if ($this->detailed_log_table_exists()) {
            $wpdb->insert(
                $this->table_name,
                $log_data,
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
            );
        }
    }

    /**
     * 匿名化されたIPアドレスのハッシュを取得
     *
     * @return string IPアドレスのハッシュ
     */
    private function get_anonymized_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // IPv4の場合、最後のオクテットを0にする
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip_parts = explode('.', $ip);
            $ip_parts[3] = '0';
            $ip = implode('.', $ip_parts);
        }
        // IPv6の場合、下位64ビットを0にする
        elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ip_parts = explode(':', $ip);
            if (count($ip_parts) >= 4) {
                $ip = implode(':', array_slice($ip_parts, 0, 4)) . '::';
            }
        }

        return hash('sha256', $ip . wp_salt());
    }

    /**
     * セッションIDの生成
     *
     * @return string セッション ID
     */
    private function generate_session_id() {
        if (!session_id()) {
            return wp_generate_uuid4();
        }
        return hash('sha256', session_id());
    }

    /**
     * 詳細ログテーブルが存在するかチェック
     *
     * @return bool テーブルの存在状況
     */
    private function detailed_log_table_exists() {
        global $wpdb;
        
        $table_name = $this->table_name;
        $query = $wpdb->prepare("SHOW TABLES LIKE %s", $table_name);
        
        return $wpdb->get_var($query) === $table_name;
    }

    /**
     * 詳細ログテーブルの作成
     */
    public function create_detailed_log_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lp_id bigint(20) unsigned NOT NULL,
            button_id varchar(100) NOT NULL,
            click_time datetime NOT NULL,
            user_agent varchar(255) DEFAULT '',
            referer varchar(500) DEFAULT '',
            ip_hash varchar(64) DEFAULT '',
            session_id varchar(64) DEFAULT '',
            user_id bigint(20) unsigned NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY lp_id (lp_id),
            KEY button_id (button_id),
            KEY click_time (click_time),
            KEY session_id (session_id)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * 指定されたLPのクリック統計を取得
     *
     * @param int $lp_id LP ID
     * @param int $days 取得する日数（デフォルト30日）
     * @return array クリック統計データ
     */
    public function get_lp_click_stats($lp_id, $days = 30) {
        $click_data = get_option(self::CLICK_DATA_OPTION, array());
        $lp_clicks = $click_data[$lp_id] ?? array();

        $stats = array(
            'total_clicks' => 0,
            'daily_clicks' => array(),
            'button_clicks' => array(),
            'period_start' => date('Y-m-d', strtotime("-{$days} days")),
            'period_end' => current_time('Y-m-d')
        );

        $start_date = $stats['period_start'];

        foreach ($lp_clicks as $date => $date_data) {
            // 指定期間内のデータのみ処理
            if ($date < $start_date) {
                continue;
            }

            $daily_total = 0;
            foreach ($date_data as $button_id => $clicks) {
                $clicks = intval($clicks);
                $stats['total_clicks'] += $clicks;
                $daily_total += $clicks;

                // ボタン別統計
                if (!isset($stats['button_clicks'][$button_id])) {
                    $stats['button_clicks'][$button_id] = 0;
                }
                $stats['button_clicks'][$button_id] += $clicks;
            }

            // 日別統計
            $stats['daily_clicks'][$date] = $daily_total;
        }

        // 日付順でソート
        ksort($stats['daily_clicks']);

        return $stats;
    }

    /**
     * 全LPのクリック統計サマリーを取得
     *
     * @return array 統計サマリー
     */
    public function get_all_lp_stats_summary() {
        $click_data = get_option(self::CLICK_DATA_OPTION, array());
        
        $summary = array(
            'total_lps' => 0,
            'total_clicks' => 0,
            'today_clicks' => 0,
            'week_clicks' => 0,
            'month_clicks' => 0,
            'top_lps' => array()
        );

        $current_date = current_time('Y-m-d');
        $week_ago = date('Y-m-d', strtotime('-7 days'));
        $month_ago = date('Y-m-d', strtotime('-30 days'));

        $lp_totals = array();

        foreach ($click_data as $lp_id => $lp_data) {
            $summary['total_lps']++;
            $lp_total = 0;

            foreach ($lp_data as $date => $date_data) {
                foreach ($date_data as $button_id => $clicks) {
                    $clicks = intval($clicks);
                    $summary['total_clicks'] += $clicks;
                    $lp_total += $clicks;

                    // 期間別集計
                    if ($date === $current_date) {
                        $summary['today_clicks'] += $clicks;
                    }
                    if ($date >= $week_ago) {
                        $summary['week_clicks'] += $clicks;
                    }
                    if ($date >= $month_ago) {
                        $summary['month_clicks'] += $clicks;
                    }
                }
            }

            $lp_totals[$lp_id] = $lp_total;
        }

        // トップLPを取得（上位5つ）
        arsort($lp_totals);
        $top_lps = array_slice($lp_totals, 0, 5, true);
        
        foreach ($top_lps as $lp_id => $clicks) {
            $summary['top_lps'][] = array(
                'lp_id' => $lp_id,
                'title' => get_the_title($lp_id) ?: sprintf(__('LP ID: %d', 'finelive-lp'), $lp_id),
                'clicks' => $clicks
            );
        }

        return $summary;
    }

    /**
     * 古いクリックデータの削除（クリーンアップ）
     *
     * @param int $keep_days 保持する日数（デフォルト365日）
     */
    public function cleanup_old_click_data($keep_days = 365) {
        $click_data = get_option(self::CLICK_DATA_OPTION, array());
        $cutoff_date = date('Y-m-d', strtotime("-{$keep_days} days"));
        
        $cleaned = false;

        foreach ($click_data as $lp_id => $lp_data) {
            foreach ($lp_data as $date => $date_data) {
                if ($date < $cutoff_date) {
                    unset($click_data[$lp_id][$date]);
                    $cleaned = true;
                }
            }
            
            // LPのデータが空になった場合は削除
            if (empty($click_data[$lp_id])) {
                unset($click_data[$lp_id]);
            }
        }

        // データが変更された場合は保存
        if ($cleaned) {
            update_option(self::CLICK_DATA_OPTION, $click_data);

            /**
             * クリックデータクリーンアップ後のアクション
             *
             * @param string $cutoff_date カットオフ日
             * @param int $keep_days 保持日数
             */
            do_action('flp_click_data_cleaned_up', $cutoff_date, $keep_days);
        }

        return $cleaned;
    }

    /**
     * 特定のLPのクリックデータを削除
     *
     * @param int $lp_id LP ID
     * @param string $date 削除する日付（空文字の場合は全データ削除）
     */
    public function delete_lp_click_data($lp_id, $date = '') {
        $click_data = get_option(self::CLICK_DATA_OPTION, array());

        if (empty($date)) {
            // LP の全データを削除
            unset($click_data[$lp_id]);
        } else {
            // 特定日のデータを削除
            unset($click_data[$lp_id][$date]);
            
            // LPのデータが空になった場合は削除
            if (empty($click_data[$lp_id])) {
                unset($click_data[$lp_id]);
            }
        }

        update_option(self::CLICK_DATA_OPTION, $click_data);

        /**
         * LPクリックデータ削除後のアクション
         *
         * @param int $lp_id LP ID
         * @param string $date 削除した日付
         */
        do_action('flp_lp_click_data_deleted', $lp_id, $date);
    }

    /**
     * 定期的なクリーンアップの設定
     */
    public static function setup_cleanup_schedule() {
        // 既存のスケジュールがない場合は追加
        if (!wp_next_scheduled('flp_cleanup_click_data')) {
            wp_schedule_event(time(), 'weekly', 'flp_cleanup_click_data');
        }
    }

    /**
     * クリーンアップスケジュールの削除
     */
    public static function clear_cleanup_schedule() {
        wp_clear_scheduled_hook('flp_cleanup_click_data');
    }

    /**
     * スケジュールされたクリーンアップの実行
     */
    public static function scheduled_cleanup() {
        $instance = new self();
        $keep_days = apply_filters('flp_click_data_retention_days', 365);
        $instance->cleanup_old_click_data($keep_days);
    }
}

// スケジュールされたクリーンアップの実行
add_action('flp_cleanup_click_data', array('FLP_Click_Tracking', 'scheduled_cleanup'));
