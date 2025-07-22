<?php
/**
 * クリックレポート管理クラス
 */

if (!defined('ABSPATH')) exit;

class FLP_Click_Reports {

    /**
     * クリック追跡インスタンス
     */
    private $click_tracker;

    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->click_tracker = new FLP_Click_Tracking();
    }

    /**
     * レポートページの描画
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('このページにアクセスする権限がありません。', 'finelive-lp'));
        }

        // フィルター値の取得
        $selected_lp = isset($_GET['lp_id']) ? intval($_GET['lp_id']) : 0;
        $date_range = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : '30';

        ?>
        <div class="wrap">
            <h1><?php _e('LPクリック測定レポート', 'finelive-lp'); ?></h1>
            <p><?php _e('LPごとのボタンクリック数と詳細統計を表示します。', 'finelive-lp'); ?></p>

            <?php $this->render_filter_form($selected_lp, $date_range); ?>
            <?php $this->render_summary_stats($selected_lp, $date_range); ?>
            <?php $this->render_detailed_reports($selected_lp, $date_range); ?>
        </div>

        <style>
        .flp-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .flp-stat-card {
            background: white;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            text-align: center;
        }
        .flp-stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
            display: block;
            margin-bottom: 5px;
        }
        .flp-stat-label {
            color: #666;
            font-size: 14px;
        }
        .flp-chart-container {
            background: white;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            margin: 20px 0;
        }
        </style>
        <?php
    }

    /**
     * フィルターフォームの描画
     *
     * @param int $selected_lp 選択されたLP ID
     * @param string $date_range 日付範囲
     */
    private function render_filter_form($selected_lp, $date_range) {
        // LP一覧の取得
        $lps = get_posts(array(
            'post_type' => 'flp_lp',
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        ?>
        <div class="flp-filter-form" style="background: white; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
            <form method="get" action="">
                <input type="hidden" name="page" value="flp_lp_clicks_report">
                
                <div style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                    <div>
                        <label for="lp_id"><?php _e('LP選択:', 'finelive-lp'); ?></label><br>
                        <select name="lp_id" id="lp_id">
                            <option value="0"><?php _e('全てのLP', 'finelive-lp'); ?></option>
                            <?php foreach ($lps as $lp): ?>
                            <option value="<?php echo $lp->ID; ?>" <?php selected($selected_lp, $lp->ID); ?>>
                                <?php echo esc_html($lp->post_title); ?> (ID: <?php echo $lp->ID; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="date_range"><?php _e('期間:', 'finelive-lp'); ?></label><br>
                        <select name="date_range" id="date_range">
                            <option value="7" <?php selected($date_range, '7'); ?>><?php _e('過去7日間', 'finelive-lp'); ?></option>
                            <option value="30" <?php selected($date_range, '30'); ?>><?php _e('過去30日間', 'finelive-lp'); ?></option>
                            <option value="90" <?php selected($date_range, '90'); ?>><?php _e('過去90日間', 'finelive-lp'); ?></option>
                            <option value="365" <?php selected($date_range, '365'); ?>><?php _e('過去1年間', 'finelive-lp'); ?></option>
                            <option value="all" <?php selected($date_range, 'all'); ?>><?php _e('全期間', 'finelive-lp'); ?></option>
                        </select>
                    </div>
                    
                    <div>
                        <?php submit_button(__('フィルター適用', 'finelive-lp'), 'primary', 'submit', false); ?>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * サマリー統計の描画
     *
     * @param int $selected_lp 選択されたLP ID
     * @param string $date_range 日付範囲
     */
    private function render_summary_stats($selected_lp, $date_range) {
        if ($selected_lp > 0) {
            $days = ($date_range === 'all') ? 365 : intval($date_range);
            $stats = $this->click_tracker->get_lp_click_stats($selected_lp, $days);
        } else {
            $stats = $this->click_tracker->get_all_lp_stats_summary();
        }

        // 日付範囲に基づく統計の調整
        if ($date_range !== 'all' && !$selected_lp) {
            $days = intval($date_range);
            $stats = $this->filter_stats_by_date_range($stats, $days);
        }

        ?>
        <div class="flp-stats-grid">
            <div class="flp-stat-card">
                <span class="flp-stat-number"><?php echo number_format($stats['total_clicks'] ?? 0); ?></span>
                <span class="flp-stat-label"><?php _e('総クリック数', 'finelive-lp'); ?></span>
            </div>
            
            <div class="flp-stat-card">
                <span class="flp-stat-number"><?php echo number_format($stats['today_clicks'] ?? 0); ?></span>
                <span class="flp-stat-label"><?php _e('今日のクリック', 'finelive-lp'); ?></span>
            </div>
            
            <div class="flp-stat-card">
                <span class="flp-stat-number"><?php echo number_format($stats['week_clicks'] ?? 0); ?></span>
                <span class="flp-stat-label"><?php _e('過去7日間', 'finelive-lp'); ?></span>
            </div>
            
            <div class="flp-stat-card">
                <span class="flp-stat-number"><?php echo number_format($stats['month_clicks'] ?? 0); ?></span>
                <span class="flp-stat-label"><?php _e('過去30日間', 'finelive-lp'); ?></span>
            </div>
        </div>
        <?php
    }

    /**
     * 詳細レポートの描画
     *
     * @param int $selected_lp 選択されたLP ID
     * @param string $date_range 日付範囲
     */
    private function render_detailed_reports($selected_lp, $date_range) {
        $click_data = get_option('flp_lp_click_data', array());
        
        // データのフィルタリング
        if ($selected_lp > 0) {
            $click_data = array($selected_lp => $click_data[$selected_lp] ?? array());
        }

        $filtered_data = $this->filter_click_data_by_date_range($click_data, $date_range);

        if (empty($filtered_data)) {
            echo '<div class="notice notice-info"><p>' . __('表示するデータがありません。', 'finelive-lp') . '</p></div>';
            return;
        }

        // 日別チャートの表示
        if ($selected_lp > 0) {
            $this->render_daily_chart($filtered_data, $selected_lp);
        }

        // 詳細テーブルの表示
        $this->render_detailed_table($filtered_data);
    }

    /**
     * 日別チャートの描画
     *
     * @param array $click_data フィルタ済みクリックデータ
     * @param int $lp_id LP ID
     */
    private function render_daily_chart($click_data, $lp_id) {
        $lp_title = get_the_title($lp_id) ?: sprintf(__('LP ID: %d', 'finelive-lp'), $lp_id);
        $daily_data = array();

        if (isset($click_data[$lp_id])) {
            foreach ($click_data[$lp_id] as $date => $date_data) {
                $daily_total = 0;
                foreach ($date_data as $button_clicks) {
                    $daily_total += intval($button_clicks);
                }
                $daily_data[$date] = $daily_total;
            }
        }

        // データを日付順にソート
        ksort($daily_data);

        ?>
        <div class="flp-chart-container">
            <h3><?php echo esc_html($lp_title); ?> - <?php _e('日別クリック数推移', 'finelive-lp'); ?></h3>
            
            <div style="overflow-x: auto;">
                <table class="wp-list-table widefat fixed striped" style="min-width: 600px;">
                    <thead>
                        <tr>
                            <th><?php _e('日付', 'finelive-lp'); ?></th>
                            <th><?php _e('クリック数', 'finelive-lp'); ?></th>
                            <th><?php _e('グラフ', 'finelive-lp'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $max_clicks = max(array_values($daily_data)) ?: 1;
                        foreach ($daily_data as $date => $clicks): 
                            $percentage = ($clicks / $max_clicks) * 100;
                        ?>
                        <tr>
                            <td><?php echo esc_html($date); ?></td>
                            <td><strong><?php echo number_format($clicks); ?></strong></td>
                            <td>
                                <div style="background: #f0f0f0; height: 20px; border-radius: 3px; position: relative;">
                                    <div style="background: #0073aa; height: 100%; width: <?php echo $percentage; ?>%; border-radius: 3px;"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * 詳細テーブルの描画
     *
     * @param array $click_data フィルタ済みクリックデータ
     */
    private function render_detailed_table($click_data) {
        ?>
        <div class="flp-chart-container">
            <h3><?php _e('詳細クリックレポート', 'finelive-lp'); ?></h3>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=flp_export_click_data'), 'flp_export_nonce'); ?>" 
                       class="button">
                        <?php _e('CSVエクスポート', 'finelive-lp'); ?>
                    </a>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php _e('LP ID', 'finelive-lp'); ?></th>
                        <th><?php _e('LPタイトル', 'finelive-lp'); ?></th>
                        <th style="width: 100px;"><?php _e('日付', 'finelive-lp'); ?></th>
                        <th style="width: 150px;"><?php _e('ボタンID', 'finelive-lp'); ?></th>
                        <th style="width: 80px;"><?php _e('クリック数', 'finelive-lp'); ?></th>
                        <th style="width: 100px;"><?php _e('操作', 'finelive-lp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_displayed = 0;
                    
                    // データを日付の降順でソート
                    foreach ($click_data as $lp_id => $lp_data) {
                        krsort($lp_data);
                        foreach ($lp_data as $date => $date_data) {
                            arsort($date_data); // クリック数の多い順
                            foreach ($date_data as $button_id => $clicks) {
                                $total_displayed++;
                                $lp_title = get_the_title($lp_id) ?: sprintf(__('LP (ID: %d) - 削除済み', 'finelive-lp'), $lp_id);
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($lp_id); ?></strong></td>
                                    <td>
                                        <?php if (get_post($lp_id)): ?>
                                            <a href="<?php echo get_edit_post_link($lp_id); ?>" target="_blank">
                                                <?php echo esc_html($lp_title); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #666;"><?php echo esc_html($lp_title); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($date); ?></td>
                                    <td><code><?php echo esc_html($button_id); ?></code></td>
                                    <td><strong><?php echo number_format($clicks); ?></strong></td>
                                    <td>
                                        <button class="button button-small flp-delete-data" 
                                                data-lp-id="<?php echo $lp_id; ?>" 
                                                data-date="<?php echo $date; ?>"
                                                data-button="<?php echo esc_attr($button_id); ?>">
                                            <?php _e('削除', 'finelive-lp'); ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                    }
                    
                    if ($total_displayed === 0): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #666; font-style: italic;">
                                <?php _e('データがありません', 'finelive-lp'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($total_displayed > 0): ?>
            <p class="description">
                <?php printf(__('表示件数: %d件', 'finelive-lp'), $total_displayed); ?>
            </p>
            <?php endif; ?>
        </div>

        <script>
        jQuery(function($) {
            $('.flp-delete-data').click(function() {
                if (!confirm('<?php esc_js_e('このデータを削除しますか？この操作は取り消せません。', 'finelive-lp'); ?>')) {
                    return false;
                }
                
                const button = $(this);
                const row = button.closest('tr');
                const lpId = button.data('lp-id');
                const date = button.data('date');
                const buttonId = button.data('button');
                
                button.prop('disabled', true).text('<?php esc_js_e('削除中...', 'finelive-lp'); ?>');
                
                $.post(ajaxurl, {
                    action: 'flp_delete_click_data',
                    lp_id: lpId,
                    date: date,
                    button_id: buttonId,
                    nonce: '<?php echo wp_create_nonce('flp_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        row.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert('<?php esc_js_e('削除に失敗しました。', 'finelive-lp'); ?>');
                        button.prop('disabled', false).text('<?php esc_js_e('削除', 'finelive-lp'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * 日付範囲でクリックデータをフィルタリング
     *
     * @param array $click_data 元のクリックデータ
     * @param string $date_range 日付範囲
     * @return array フィルタ済みデータ
     */
    private function filter_click_data_by_date_range($click_data, $date_range) {
        if ($date_range === 'all') {
            return $click_data;
        }

        $days = intval($date_range);
        $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
        $filtered_data = array();

        foreach ($click_data as $lp_id => $lp_data) {
            foreach ($lp_data as $date => $date_data) {
                if ($date >= $cutoff_date) {
                    if (!isset($filtered_data[$lp_id])) {
                        $filtered_data[$lp_id] = array();
                    }
                    $filtered_data[$lp_id][$date] = $date_data;
                }
            }
        }

        return $filtered_data;
    }

    /**
     * 統計データを日付範囲でフィルタリング
     *
     * @param array $stats 統計データ
     * @param int $days 日数
     * @return array フィルタ済み統計
     */
    private function filter_stats_by_date_range($stats, $days) {
        // 簡易的な実装
        // 実際の実装では、クリックデータから再計算する必要がある
        return $stats;
    }

    /**
     * レポートデータの取得（API用）
     *
     * @param array $params パラメータ
     * @return array レポートデータ
     */
    public function get_report_data($params = array()) {
        $lp_id = $params['lp_id'] ?? 0;
        $date_range = $params['date_range'] ?? '30';
        
        if ($lp_id > 0) {
            $days = ($date_range === 'all') ? 365 : intval($date_range);
            return $this->click_tracker->get_lp_click_stats($lp_id, $days);
        } else {
            return $this->click_tracker->get_all_lp_stats_summary();
        }
    }

    /**
     * チャート用JSONデータの生成
     *
     * @param int $lp_id LP ID
     * @param int $days 日数
     * @return string JSON データ
     */
    public function get_chart_json_data($lp_id, $days = 30) {
        $stats = $this->click_tracker->get_lp_click_stats($lp_id, $days);
        $chart_data = array();

        if (isset($stats['daily_clicks'])) {
            foreach ($stats['daily_clicks'] as $date => $clicks) {
                $chart_data[] = array(
                    'date' => $date,
                    'clicks' => intval($clicks)
                );
            }
        }

        return json_encode($chart_data);
    }

    /**
     * トップパフォーマーLPの取得
     *
     * @param int $limit 取得件数
     * @param int $days 日数
     * @return array トップパフォーマーLPのデータ
     */
    public function get_top_performing_lps($limit = 10, $days = 30) {
        $click_data = get_option('flp_lp_click_data', array());
        $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
        $lp_totals = array();

        foreach ($click_data as $lp_id => $lp_data) {
            $total_clicks = 0;
            foreach ($lp_data as $date => $date_data) {
                if ($date >= $cutoff_date) {
                    foreach ($date_data as $button_clicks) {
                        $total_clicks += intval($button_clicks);
                    }
                }
            }
            
            if ($total_clicks > 0) {
                $lp_totals[$lp_id] = array(
                    'lp_id' => $lp_id,
                    'title' => get_the_title($lp_id) ?: sprintf(__('LP ID: %d', 'finelive-lp'), $lp_id),
                    'clicks' => $total_clicks
                );
            }
        }

        // クリック数で降順ソート
        usort($lp_totals, function($a, $b) {
            return $b['clicks'] - $a['clicks'];
        });

        return array_slice($lp_totals, 0, $limit);
    }
}
