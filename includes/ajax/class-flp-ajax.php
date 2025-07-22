<?php
/**
 * AJAX処理メインクラス
 */

if (!defined('ABSPATH')) exit;

class FLP_Ajax {

    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->init_ajax_actions();
        
        // クリック追跡専用クラスを初期化
        new FLP_Click_Tracking();
    }

    /**
     * AJAX アクションの初期化
     */
    private function init_ajax_actions() {
        // プレビュー関連
        add_action('wp_ajax_flp_preview_lp', array($this, 'handle_preview_lp'));
        
        // 管理画面専用AJAX
        add_action('wp_ajax_flp_save_builder_data', array($this, 'handle_save_builder_data'));
        add_action('wp_ajax_flp_duplicate_lp', array($this, 'handle_duplicate_lp'));
        add_action('wp_ajax_flp_delete_click_data', array($this, 'handle_delete_click_data'));
        add_action('wp_ajax_flp_export_click_data', array($this, 'handle_export_click_data'));
        
        // フロントエンド用AJAX（nopriv版も必要）
        add_action('wp_ajax_flp_lp_track_click', array($this, 'handle_track_click'));
        add_action('wp_ajax_nopriv_flp_lp_track_click', array($this, 'handle_track_click'));
        
        // ユーティリティAJAX
        add_action('wp_ajax_flp_clear_cache', array($this, 'handle_clear_cache'));
        add_action('wp_ajax_flp_get_lp_stats', array($this, 'handle_get_lp_stats'));
    }

    /**
     * LPプレビューの処理
     */
    public function handle_preview_lp() {
        // nonce チェック
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'flp_preview')) {
            wp_send_json_error(array('message' => __('セキュリティチェックに失敗しました。', 'finelive-lp')));
        }

        $lp_id = intval($_POST['lp_id'] ?? 0);
        
        // 権限チェック
        if (!$lp_id || !current_user_can('edit_flp_lp', $lp_id)) {
            wp_send_json_error(array('message' => __('権限がありません。', 'finelive-lp')));
        }

        try {
            // 一時的な設定データの取得（未保存の可能性あり）
            $temp_data = $_POST['components'] ?? null;
            
            if ($temp_data) {
                // 一時データを使用してプレビュー生成
                $preview_html = $this->generate_preview_from_data($lp_id, $temp_data);
            } else {
                // 保存済みデータでプレビュー生成
                $frontend = FLP()->frontend();
                $preview_html = $frontend->render_lp($lp_id);
            }

            // プレビュー用のCSSを追加
            $preview_html = $this->wrap_preview_html($preview_html, $lp_id);

            wp_send_json_success(array(
                'html' => $preview_html,
                'lp_id' => $lp_id
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('プレビュー生成中にエラーが発生しました。', 'finelive-lp'),
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * ビルダーデータの保存処理
     */
    public function handle_save_builder_data() {
        // nonce チェック
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'flp_save_builder')) {
            wp_send_json_error(array('message' => __('セキュリティチェックに失敗しました。', 'finelive-lp')));
        }

        $lp_id = intval($_POST['lp_id'] ?? 0);
        $components_data = $_POST['components'] ?? array();

        // 権限チェック
        if (!$lp_id || !current_user_can('edit_flp_lp', $lp_id)) {
            wp_send_json_error(array('message' => __('権限がありません。', 'finelive-lp')));
        }

        try {
            // コンポーネントデータのサニタイズ
            $sanitized_components = $this->sanitize_components_data($components_data);
            
            // 既存データを取得してコンポーネント部分のみ更新
            $existing_data = get_post_meta($lp_id, 'flp_lp_data', true) ?: array();
            $existing_data['components'] = $sanitized_components;

            // メタデータを更新
            update_post_meta($lp_id, 'flp_lp_data', $existing_data);

            // キャッシュクリア
            FLP_Shortcode::clear_lp_cache($lp_id);

            wp_send_json_success(array(
                'message' => __('データが保存されました。', 'finelive-lp'),
                'lp_id' => $lp_id,
                'components_count' => count($sanitized_components)
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('保存中にエラーが発生しました。', 'finelive-lp'),
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * LP複製の処理
     */
    public function handle_duplicate_lp() {
        // nonce チェック
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'flp_duplicate')) {
            wp_send_json_error(array('message' => __('セキュリティチェックに失敗しました。', 'finelive-lp')));
        }

        $source_lp_id = intval($_POST['source_id'] ?? 0);
        
        // 権限チェック
        if (!$source_lp_id || !current_user_can('edit_flp_lp', $source_lp_id)) {
            wp_send_json_error(array('message' => __('権限がありません。', 'finelive-lp')));
        }

        try {
            $duplicator = new FLP_LP_Duplicator();
            $new_lp_id = $duplicator->duplicate_lp($source_lp_id);

            wp_send_json_success(array(
                'message' => __('LPが複製されました。', 'finelive-lp'),
                'new_lp_id' => $new_lp_id,
                'edit_url' => get_edit_post_link($new_lp_id),
                'source_id' => $source_lp_id
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('複製中にエラーが発生しました。', 'finelive-lp'),
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * クリックトラッキングの処理（FLP_Click_Trackingに委譲）
     */
    public function handle_track_click() {
        // クリック追跡専用クラスで処理
        $click_tracker = new FLP_Click_Tracking();
        $click_tracker->handle_track_click();
    }

    /**
     * クリックデータ削除の処理
     */
    public function handle_delete_click_data() {
        // nonce チェック
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'flp_admin_nonce')) {
            wp_send_json_error(array('message' => __('セキュリティチェックに失敗しました。', 'finelive-lp')));
        }

        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('権限がありません。', 'finelive-lp')));
        }

        $lp_id = intval($_POST['lp_id'] ?? 0);
        $date = sanitize_text_field($_POST['date'] ?? '');

        try {
            $click_data = get_option('flp_lp_click_data', array());

            if ($lp_id && $date) {
                // 特定のLPの特定日のデータを削除
                unset($click_data[$lp_id][$date]);
                if (empty($click_data[$lp_id])) {
                    unset($click_data[$lp_id]);
                }
            } elseif ($lp_id) {
                // 特定のLPの全データを削除
                unset($click_data[$lp_id]);
            } else {
                // 全データを削除
                $click_data = array();
            }

            update_option('flp_lp_click_data', $click_data);

            wp_send_json_success(array(
                'message' => __('クリックデータが削除されました。', 'finelive-lp')
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('削除中にエラーが発生しました。', 'finelive-lp'),
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * クリックデータエクスポートの処理
     */
    public function handle_export_click_data() {
        // nonce チェック
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'flp_export_nonce')) {
            wp_die(__('セキュリティチェックに失敗しました。', 'finelive-lp'));
        }

        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_die(__('権限がありません。', 'finelive-lp'));
        }

        try {
            $this->export_click_data_csv();
        } catch (Exception $e) {
            wp_die(__('エクスポート中にエラーが発生しました: ', 'finelive-lp') . $e->getMessage());
        }
    }

    /**
     * キャッシュクリアの処理
     */
    public function handle_clear_cache() {
        // nonce チェック
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'flp_admin_nonce')) {
            wp_send_json_error(array('message' => __('セキュリティチェックに失敗しました。', 'finelive-lp')));
        }

        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('権限がありません。', 'finelive-lp')));
        }

        $lp_id = intval($_POST['lp_id'] ?? 0);

        try {
            if ($lp_id) {
                FLP_Shortcode::clear_lp_cache($lp_id);
                $message = sprintf(__('LP ID %d のキャッシュがクリアされました。', 'finelive-lp'), $lp_id);
            } else {
                FLP_Shortcode::clear_all_cache();
                $message = __('全てのキャッシュがクリアされました。', 'finelive-lp');
            }

            wp_send_json_success(array('message' => $message));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('キャッシュクリア中にエラーが発生しました。', 'finelive-lp'),
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * LP統計取得の処理
     */
    public function handle_get_lp_stats() {
        // nonce チェック
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'flp_admin_nonce')) {
            wp_send_json_error(array('message' => __('セキュリティチェックに失敗しました。', 'finelive-lp')));
        }

        $lp_id = intval($_POST['lp_id'] ?? 0);

        // 権限チェック
        if (!$lp_id || !current_user_can('edit_flp_lp', $lp_id)) {
            wp_send_json_error(array('message' => __('権限がありません。', 'finelive-lp')));
        }

        try {
            $stats = $this->get_lp_statistics($lp_id);
            wp_send_json_success($stats);

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('統計取得中にエラーが発生しました。', 'finelive-lp'),
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * プレビューHTMLをラップ
     *
     * @param string $html プレビューHTML
     * @param int $lp_id LP ID
     * @return string ラップされたHTML
     */
    private function wrap_preview_html($html, $lp_id) {
        $css = '<style>
            body { margin: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .flp-preview-header {
                background: #0073aa;
                color: white;
                padding: 15px;
                margin: -20px -20px 20px -20px;
                font-size: 16px;
                font-weight: bold;
            }
            .flp_lp_wrap { max-width: 600px; margin: 0 auto; }
        </style>';

        $header = '<div class="flp-preview-header">
            ? LP プレビュー - ID: ' . $lp_id . '
            <span style="float: right; font-weight: normal; font-size: 14px;">' . current_time('Y/m/d H:i') . '</span>
        </div>';

        return $css . $header . $html;
    }

    /**
     * 一時データからプレビューを生成
     *
     * @param int $lp_id LP ID
     * @param mixed $temp_data 一時データ
     * @return string プレビューHTML
     */
    private function generate_preview_from_data($lp_id, $temp_data) {
        // 一時データの解析とプレビュー生成のロジック
        // 将来的にビジュアルビルダー用に使用
        
        if (is_string($temp_data)) {
            $temp_data = json_decode($temp_data, true);
        }

        // 暫定的に通常のレンダリングを使用
        $frontend = FLP()->frontend();
        return $frontend->render_lp($lp_id);
    }

    /**
     * コンポーネントデータのサニタイズ
     *
     * @param mixed $components_data コンポーネントデータ
     * @return array サニタイズされたデータ
     */
    private function sanitize_components_data($components_data) {
        if (!is_array($components_data)) {
            if (is_string($components_data)) {
                $components_data = json_decode($components_data, true);
            }
            if (!is_array($components_data)) {
                return array();
            }
        }

        $sanitized = array();
        
        foreach ($components_data as $component) {
            if (!is_array($component) || !isset($component['type'])) {
                continue;
            }

            $sanitized_component = array(
                'id' => sanitize_text_field($component['id'] ?? uniqid('comp_')),
                'type' => sanitize_text_field($component['type']),
                'config' => $this->sanitize_component_config($component['config'] ?? array())
            );

            $sanitized[] = $sanitized_component;
        }

        return $sanitized;
    }

    /**
     * コンポーネント設定のサニタイズ
     *
     * @param array $config 設定データ
     * @return array サニタイズされた設定
     */
    private function sanitize_component_config($config) {
        if (!is_array($config)) {
            return array();
        }

        $sanitized = array();
        
        foreach ($config as $key => $value) {
            $key = sanitize_key($key);
            
            if (is_string($value)) {
                // URLの場合
                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    $sanitized[$key] = esc_url_raw($value);
                }
                // カラーコードの場合
                elseif (preg_match('/^#[a-f0-9]{6}$/i', $value)) {
                    $sanitized[$key] = sanitize_hex_color($value);
                }
                // テキストの場合
                else {
                    $sanitized[$key] = sanitize_text_field($value);
                }
            } elseif (is_numeric($value)) {
                $sanitized[$key] = floatval($value);
            } elseif (is_bool($value)) {
                $sanitized[$key] = (bool) $value;
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitize_component_config($value);
            }
        }

        return $sanitized;
    }

    /**
     * LP統計情報を取得
     *
     * @param int $lp_id LP ID
     * @return array 統計データ
     */
    private function get_lp_statistics($lp_id) {
        $click_data = get_option('flp_lp_click_data', array());
        $lp_clicks = $click_data[$lp_id] ?? array();

        $stats = array(
            'total_clicks' => 0,
            'today_clicks' => 0,
            'week_clicks' => 0,
            'month_clicks' => 0,
            'daily_stats' => array(),
            'button_stats' => array()
        );

        $current_date = date('Y-m-d');
        $week_ago = date('Y-m-d', strtotime('-7 days'));
        $month_ago = date('Y-m-d', strtotime('-30 days'));

        foreach ($lp_clicks as $date => $date_data) {
            foreach ($date_data as $button_id => $clicks) {
                $clicks = intval($clicks);
                $stats['total_clicks'] += $clicks;

                // 日別集計
                if (!isset($stats['daily_stats'][$date])) {
                    $stats['daily_stats'][$date] = 0;
                }
                $stats['daily_stats'][$date] += $clicks;

                // ボタン別集計
                if (!isset($stats['button_stats'][$button_id])) {
                    $stats['button_stats'][$button_id] = 0;
                }
                $stats['button_stats'][$button_id] += $clicks;

                // 期間別集計
                if ($date === $current_date) {
                    $stats['today_clicks'] += $clicks;
                }
                if ($date >= $week_ago) {
                    $stats['week_clicks'] += $clicks;
                }
                if ($date >= $month_ago) {
                    $stats['month_clicks'] += $clicks;
                }
            }
        }

        // 日別統計を日付順にソート
        krsort($stats['daily_stats']);

        return $stats;
    }

    /**
     * クリックデータのCSVエクスポート
     */
    private function export_click_data_csv() {
        $click_data = get_option('flp_lp_click_data', array());
        
        // ファイル名の生成
        $filename = 'lp_click_data_' . date('Y-m-d_H-i-s') . '.csv';

        // HTTPヘッダーの設定
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // UTF-8 BOM を追加（Excel で文字化けを防ぐ）
        echo "\xEF\xBB\xBF";

        // CSV出力の開始
        $output = fopen('php://output', 'w');
        
        // ヘッダー行
        fputcsv($output, array(
            'LP ID',
            'LP タイトル',
            '日付',
            'ボタンID',
            'クリック数'
        ));

        // データ行
        foreach ($click_data as $lp_id => $lp_data) {
            $lp_title = get_the_title($lp_id) ?: sprintf(__('LP (ID: %d) - 削除済み', 'finelive-lp'), $lp_id);
            
            foreach ($lp_data as $date => $date_data) {
                foreach ($date_data as $button_id => $clicks) {
                    fputcsv($output, array(
                        $lp_id,
                        $lp_title,
                        $date,
                        $button_id,
                        $clicks
                    ));
                }
            }
        }

        fclose($output);
        exit;
    }

    /**
     * セキュリティチェック用のnonceを生成
     *
     * @param string $action アクション名
     * @return string nonce値
     */
    public static function create_ajax_nonce($action) {
        return wp_create_nonce('flp_ajax_' . $action);
    }

    /**
     * セキュリティチェック用のnonceを検証
     *
     * @param string $nonce nonce値
     * @param string $action アクション名
     * @return bool 検証結果
     */
    public static function verify_ajax_nonce($nonce, $action) {
        return wp_verify_nonce($nonce, 'flp_ajax_' . $action);
    }
}
