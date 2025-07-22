<?php
/**
 * 管理メニュー管理クラス
 */

if (!defined('ABSPATH')) exit;

class FLP_Admin_Menu {

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menus'));
    }

    /**
     * 管理メニューの追加
     */
    public function add_admin_menus() {
        // クリック測定レポートページ
        add_menu_page(
            __('LPクリック測定', 'finelive-lp'),
            __('LPクリック測定', 'finelive-lp'),
            'manage_options',
            'flp_lp_clicks_report',
            array($this, 'render_click_reports_page'),
            'dashicons-chart-bar',
            30
        );

        // LP管理のサブメニューに使い方ガイドを追加
        add_submenu_page(
            'edit.php?post_type=flp_lp',
            __('使い方ガイド', 'finelive-lp'),
            __('使い方ガイド', 'finelive-lp'),
            'edit_posts',
            'flp-lp-usage',
            array($this, 'render_usage_guide_page')
        );

        // LP管理のサブメニューに設定ページを追加
        add_submenu_page(
            'edit.php?post_type=flp_lp',
            __('設定', 'finelive-lp'),
            __('設定', 'finelive-lp'),
            'manage_options',
            'flp-lp-settings',
            array($this, 'render_settings_page')
        );

        // LP管理のサブメニューにツールページを追加
        add_submenu_page(
            'edit.php?post_type=flp_lp',
            __('ツール', 'finelive-lp'),
            __('ツール', 'finelive-lp'),
            'manage_options',
            'flp-lp-tools',
            array($this, 'render_tools_page')
        );
    }

    /**
     * クリック測定レポートページの描画
     */
    public function render_click_reports_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('このページにアクセスする権限がありません。', 'finelive-lp'));
        }

        $click_reports = new FLP_Click_Reports();
        $click_reports->render_page();
    }

    /**
     * 使い方ガイドページの描画
     */
    public function render_usage_guide_page() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('このページにアクセスする権限がありません。', 'finelive-lp'));
        }

        $usage_guide = new FLP_Usage_Guide();
        $usage_guide->render_page();
    }

    /**
     * 設定ページの描画
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('このページにアクセスする権限がありません。', 'finelive-lp'));
        }

        // 設定の保存処理
        if (isset($_POST['flp_settings_submit'])) {
            $this->save_settings();
        }

        $this->display_settings_form();
    }

    /**
     * ツールページの描画
     */
    public function render_tools_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('このページにアクセスする権限がありません。', 'finelive-lp'));
        }

        // ツールの実行処理
        if (isset($_POST['tool_action'])) {
            $this->handle_tool_action($_POST['tool_action']);
        }

        $this->display_tools_form();
    }

    /**
     * 設定の保存処理
     */
    private function save_settings() {
        // nonce チェック
        if (!wp_verify_nonce($_POST['flp_settings_nonce'], 'flp_settings_save')) {
            add_settings_error('flp_settings', 'invalid_nonce', __('セキュリティチェックに失敗しました。', 'finelive-lp'));
            return;
        }

        $settings = array(
            'enable_click_tracking' => isset($_POST['enable_click_tracking']) ? 1 : 0,
            'click_data_retention_days' => intval($_POST['click_data_retention_days']),
            'enable_cache' => isset($_POST['enable_cache']) ? 1 : 0,
            'cache_duration' => intval($_POST['cache_duration']),
            'enable_detailed_logging' => isset($_POST['enable_detailed_logging']) ? 1 : 0,
            'default_button_text' => sanitize_text_field($_POST['default_button_text']),
            'default_button_bg_color' => sanitize_hex_color($_POST['default_button_bg_color']),
            'default_button_text_color' => sanitize_hex_color($_POST['default_button_text_color']),
            'enable_preview_mode' => isset($_POST['enable_preview_mode']) ? 1 : 0,
            'load_assets_conditionally' => isset($_POST['load_assets_conditionally']) ? 1 : 0,
        );

        // 設定の検証
        if ($settings['click_data_retention_days'] < 1) {
            $settings['click_data_retention_days'] = 365;
        }

        if ($settings['cache_duration'] < 300) {
            $settings['cache_duration'] = 3600;
        }

        if (empty($settings['default_button_text'])) {
            $settings['default_button_text'] = __('応募はこちら', 'finelive-lp');
        }

        // データベースに保存
        update_option('flp_settings', $settings);

        add_settings_error('flp_settings', 'settings_saved', __('設定が保存されました。', 'finelive-lp'), 'updated');
    }

    /**
     * 設定フォームの表示
     */
    private function display_settings_form() {
        $settings = get_option('flp_settings', $this->get_default_settings());
        ?>
        <div class="wrap">
            <h1><?php _e('FineLive Multi LP Display - 設定', 'finelive-lp'); ?></h1>

            <?php settings_errors('flp_settings'); ?>

            <form method="post" action="">
                <?php wp_nonce_field('flp_settings_save', 'flp_settings_nonce'); ?>

                <table class="form-table">
                    <tbody>
                        <!-- クリック追跡設定 -->
                        <tr>
                            <th scope="row"><?php _e('クリック追跡', 'finelive-lp'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="enable_click_tracking" value="1" <?php checked($settings['enable_click_tracking'], 1); ?>>
                                        <?php _e('クリック追跡を有効にする', 'finelive-lp'); ?>
                                    </label>
                                    <p class="description"><?php _e('LPボタンのクリック数を自動で測定・記録します。', 'finelive-lp'); ?></p>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('データ保持期間', 'finelive-lp'); ?></th>
                            <td>
                                <input type="number" name="click_data_retention_days" value="<?php echo esc_attr($settings['click_data_retention_days']); ?>" min="1" max="3650" class="regular-text"> <?php _e('日', 'finelive-lp'); ?>
                                <p class="description"><?php _e('クリックデータを保持する日数を設定します。古いデータは自動的に削除されます。', 'finelive-lp'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('詳細ログ', 'finelive-lp'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="enable_detailed_logging" value="1" <?php checked($settings['enable_detailed_logging'], 1); ?>>
                                        <?php _e('詳細ログを有効にする', 'finelive-lp'); ?>
                                    </label>
                                    <p class="description"><?php _e('クリックの詳細情報（リファラー、ユーザーエージェントなど）を記録します。プライバシーに配慮した形で保存されます。', 'finelive-lp'); ?></p>
                                </fieldset>
                            </td>
                        </tr>

                        <!-- キャッシュ設定 -->
                        <tr>
                            <th scope="row"><?php _e('キャッシュ', 'finelive-lp'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="enable_cache" value="1" <?php checked($settings['enable_cache'], 1); ?>>
                                        <?php _e('キャッシュを有効にする', 'finelive-lp'); ?>
                                    </label>
                                    <p class="description"><?php _e('LP出力をキャッシュしてページ表示速度を向上させます。', 'finelive-lp'); ?></p>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('キャッシュ時間', 'finelive-lp'); ?></th>
                            <td>
                                <input type="number" name="cache_duration" value="<?php echo esc_attr($settings['cache_duration']); ?>" min="300" max="86400" class="regular-text"> <?php _e('秒', 'finelive-lp'); ?>
                                <p class="description"><?php _e('キャッシュの有効時間を秒単位で設定します。デフォルトは3600秒（1時間）です。', 'finelive-lp'); ?></p>
                            </td>
                        </tr>

                        <!-- デフォルト値設定 -->
                        <tr>
                            <th scope="row"><?php _e('デフォルトボタンテキスト', 'finelive-lp'); ?></th>
                            <td>
                                <input type="text" name="default_button_text" value="<?php echo esc_attr($settings['default_button_text']); ?>" class="regular-text">
                                <p class="description"><?php _e('新しいLPで使用されるデフォルトのボタンテキストです。', 'finelive-lp'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('デフォルトボタン色', 'finelive-lp'); ?></th>
                            <td>
                                <label>
                                    <?php _e('背景色:', 'finelive-lp'); ?> 
                                    <input type="text" name="default_button_bg_color" value="<?php echo esc_attr($settings['default_button_bg_color']); ?>" class="color-picker" data-default-color="#ff4081">
                                </label>
                                <br><br>
                                <label>
                                    <?php _e('文字色:', 'finelive-lp'); ?> 
                                    <input type="text" name="default_button_text_color" value="<?php echo esc_attr($settings['default_button_text_color']); ?>" class="color-picker" data-default-color="#ffffff">
                                </label>
                                <p class="description"><?php _e('新しいLPで使用されるデフォルトのボタン色です。', 'finelive-lp'); ?></p>
                            </td>
                        </tr>

                        <!-- パフォーマンス設定 -->
                        <tr>
                            <th scope="row"><?php _e('アセット読み込み最適化', 'finelive-lp'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="load_assets_conditionally" value="1" <?php checked($settings['load_assets_conditionally'], 1); ?>>
                                        <?php _e('条件付きアセット読み込みを有効にする', 'finelive-lp'); ?>
                                    </label>
                                    <p class="description"><?php _e('LPが使用されているページでのみCSS・JavaScriptを読み込みます。', 'finelive-lp'); ?></p>
                                </fieldset>
                            </td>
                        </tr>

                        <!-- 開発者設定 -->
                        <tr>
                            <th scope="row"><?php _e('プレビューモード', 'finelive-lp'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="enable_preview_mode" value="1" <?php checked($settings['enable_preview_mode'], 1); ?>>
                                        <?php _e('管理者向けプレビューモードを有効にする', 'finelive-lp'); ?>
                                    </label>
                                    <p class="description"><?php _e('管理者には表示期間外のLPもプレビューとして表示されます。', 'finelive-lp'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('設定を保存', 'finelive-lp'), 'primary', 'flp_settings_submit'); ?>
            </form>

            <script>
            jQuery(function($) {
                $('.color-picker').wpColorPicker();
            });
            </script>
        </div>
        <?php
    }

    /**
     * ツールフォームの表示
     */
    private function display_tools_form() {
        $click_tracker = new FLP_Click_Tracking();
        $stats = $click_tracker->get_all_lp_stats_summary();
        ?>
        <div class="wrap">
            <h1><?php _e('FineLive Multi LP Display - ツール', 'finelive-lp'); ?></h1>

            <?php settings_errors('flp_tools'); ?>

            <!-- 統計サマリー -->
            <div class="flp-stats-summary" style="display: flex; gap: 20px; margin-bottom: 30px;">
                <div class="flp-stat-box" style="background: white; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1;">
                    <h3 style="margin-top: 0;"><?php _e('統計サマリー', 'finelive-lp'); ?></h3>
                    <p><?php printf(__('登録LP数: %d', 'finelive-lp'), '<strong>' . $stats['total_lps'] . '</strong>'); ?></p>
                    <p><?php printf(__('総クリック数: %d', 'finelive-lp'), '<strong>' . number_format($stats['total_clicks']) . '</strong>'); ?></p>
                    <p><?php printf(__('今日のクリック: %d', 'finelive-lp'), '<strong>' . number_format($stats['today_clicks']) . '</strong>'); ?></p>
                    <p><?php printf(__('今月のクリック: %d', 'finelive-lp'), '<strong>' . number_format($stats['month_clicks']) . '</strong>'); ?></p>
                </div>
                
                <div class="flp-stat-box" style="background: white; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1;">
                    <h3 style="margin-top: 0;"><?php _e('人気LP TOP5', 'finelive-lp'); ?></h3>
                    <?php if (!empty($stats['top_lps'])): ?>
                        <ol style="margin: 0; padding-left: 20px;">
                            <?php foreach ($stats['top_lps'] as $lp): ?>
                            <li style="margin-bottom: 5px;">
                                <a href="<?php echo get_edit_post_link($lp['lp_id']); ?>"><?php echo esc_html($lp['title']); ?></a>
                                <span style="color: #666;">(<?php echo number_format($lp['clicks']); ?> クリック)</span>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <p style="color: #666; font-style: italic;"><?php _e('まだデータがありません', 'finelive-lp'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- データ管理ツール -->
            <div class="flp-tools-section">
                <h2><?php _e('データ管理ツール', 'finelive-lp'); ?></h2>
                
                <form method="post" action="" onsubmit="return confirm('<?php esc_attr_e('この操作は取り消せません。続行しますか？', 'finelive-lp'); ?>');">
                    <?php wp_nonce_field('flp_tools_action', 'flp_tools_nonce'); ?>
                    
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><?php _e('キャッシュクリア', 'finelive-lp'); ?></th>
                                <td>
                                    <button type="submit" name="tool_action" value="clear_cache" class="button">
                                        <?php _e('全キャッシュをクリア', 'finelive-lp'); ?>
                                    </button>
                                    <p class="description"><?php _e('全てのLPキャッシュを削除します。LP変更後にキャッシュが更新されない場合にお使いください。', 'finelive-lp'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php _e('古いクリックデータの削除', 'finelive-lp'); ?></th>
                                <td>
                                    <button type="submit" name="tool_action" value="cleanup_click_data" class="button">
                                        <?php _e('古いデータを削除', 'finelive-lp'); ?>
                                    </button>
                                    <p class="description"><?php _e('設定で指定した保持期間を過ぎたクリックデータを削除します。', 'finelive-lp'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php _e('データベース最適化', 'finelive-lp'); ?></th>
                                <td>
                                    <button type="submit" name="tool_action" value="optimize_database" class="button">
                                        <?php _e('データベースを最適化', 'finelive-lp'); ?>
                                    </button>
                                    <p class="description"><?php _e('プラグイン関連のデータベーステーブルを最適化します。', 'finelive-lp'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </form>
            </div>

            <!-- データエクスポート -->
            <div class="flp-tools-section">
                <h2><?php _e('データエクスポート', 'finelive-lp'); ?></h2>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php _e('クリックデータのエクスポート', 'finelive-lp'); ?></th>
                            <td>
                                <a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=flp_export_click_data'), 'flp_export_nonce'); ?>" 
                                   class="button">
                                    <?php _e('CSVでエクスポート', 'finelive-lp'); ?>
                                </a>
                                <p class="description"><?php _e('全てのクリックデータをCSV形式でダウンロードします。', 'finelive-lp'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- システム情報 -->
            <div class="flp-tools-section">
                <h2><?php _e('システム情報', 'finelive-lp'); ?></h2>
                <?php $this->display_system_info(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * ツールアクションの処理
     *
     * @param string $action 実行するアクション
     */
    private function handle_tool_action($action) {
        // nonce チェック
        if (!wp_verify_nonce($_POST['flp_tools_nonce'], 'flp_tools_action')) {
            add_settings_error('flp_tools', 'invalid_nonce', __('セキュリティチェックに失敗しました。', 'finelive-lp'));
            return;
        }

        switch ($action) {
            case 'clear_cache':
                FLP_Shortcode::clear_all_cache();
                add_settings_error('flp_tools', 'cache_cleared', __('キャッシュをクリアしました。', 'finelive-lp'), 'updated');
                break;

            case 'cleanup_click_data':
                $click_tracker = new FLP_Click_Tracking();
                $settings = get_option('flp_settings', $this->get_default_settings());
                $cleaned = $click_tracker->cleanup_old_click_data($settings['click_data_retention_days']);
                
                if ($cleaned) {
                    add_settings_error('flp_tools', 'data_cleaned', __('古いクリックデータを削除しました。', 'finelive-lp'), 'updated');
                } else {
                    add_settings_error('flp_tools', 'no_data_to_clean', __('削除する古いデータはありませんでした。', 'finelive-lp'), 'updated');
                }
                break;

            case 'optimize_database':
                $this->optimize_database_tables();
                add_settings_error('flp_tools', 'database_optimized', __('データベースを最適化しました。', 'finelive-lp'), 'updated');
                break;

            default:
                add_settings_error('flp_tools', 'invalid_action', __('無効なアクションです。', 'finelive-lp'));
                break;
        }
    }

    /**
     * システム情報の表示
     */
    private function display_system_info() {
        global $wpdb;
        
        $info = array(
            'プラグインバージョン' => FLP_VERSION,
            'WordPress バージョン' => get_bloginfo('version'),
            'PHP バージョン' => PHP_VERSION,
            'データベースバージョン' => $wpdb->db_version(),
            'テーマ' => wp_get_theme()->get('Name') . ' ' . wp_get_theme()->get('Version'),
            'アクティブプラグイン数' => count(get_option('active_plugins', array())),
            'メモリ制限' => ini_get('memory_limit'),
            'アップロード最大サイズ' => size_format(wp_max_upload_size()),
        );

        echo '<div style="background: white; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">';
        echo '<table class="widefat" style="border: none;">';
        
        foreach ($info as $label => $value) {
            echo '<tr>';
            echo '<td style="width: 200px; font-weight: bold;">' . esc_html($label) . ':</td>';
            echo '<td>' . esc_html($value) . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</div>';
    }

    /**
     * データベーステーブルの最適化
     */
    private function optimize_database_tables() {
        global $wpdb;
        
        // プラグイン関連のテーブルを最適化
        $tables = array(
            $wpdb->posts,
            $wpdb->postmeta,
            $wpdb->options,
        );

        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE {$table}");
        }

        // プラグイン専用テーブルがある場合は最適化
        $custom_table = $wpdb->prefix . 'flp_click_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$custom_table}'") === $custom_table) {
            $wpdb->query("OPTIMIZE TABLE {$custom_table}");
        }
    }

    /**
     * デフォルト設定の取得
     *
     * @return array デフォルト設定
     */
    private function get_default_settings() {
        return array(
            'enable_click_tracking' => 1,
            'click_data_retention_days' => 365,
            'enable_cache' => 1,
            'cache_duration' => 3600,
            'enable_detailed_logging' => 0,
            'default_button_text' => __('応募はこちら', 'finelive-lp'),
            'default_button_bg_color' => '#ff4081',
            'default_button_text_color' => '#ffffff',
            'enable_preview_mode' => 1,
            'load_assets_conditionally' => 1,
        );
    }

    /**
     * プラグイン設定を取得
     *
     * @param string $key 設定キー（オプション）
     * @param mixed $default デフォルト値
     * @return mixed 設定値
     */
    public static function get_setting($key = null, $default = null) {
        $instance = new self();
        $settings = get_option('flp_settings', $instance->get_default_settings());

        if ($key === null) {
            return $settings;
        }

        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * プラグイン設定を更新
     *
     * @param string $key 設定キー
     * @param mixed $value 設定値
     */
    public static function update_setting($key, $value) {
        $instance = new self();
        $settings = get_option('flp_settings', $instance->get_default_settings());
        $settings[$key] = $value;
        update_option('flp_settings', $settings);
    }
}
