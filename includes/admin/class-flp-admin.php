<?php
/**
 * 管理画面メインクラス
 */

if (!defined('ABSPATH')) exit;

class FLP_Admin {

    /**
     * メタボックス管理インスタンス
     */
    private $meta_boxes;

    /**
     * 管理メニュー管理インスタンス
     */
    private $admin_menu;

    /**
     * LP複製管理インスタンス
     */
    private $lp_duplicator;

    /**
     * コンストラクタ
     */
    public function __construct() {
        // 基本的なフックの設定
        $this->init_hooks();

        // サブクラスの初期化
        $this->meta_boxes = new FLP_Meta_Boxes();
        $this->admin_menu = new FLP_Admin_Menu();
        $this->lp_duplicator = new FLP_LP_Duplicator();
    }

    /**
     * 基本フックの初期化
     */
    private function init_hooks() {
        // 管理画面でのスクリプト・スタイル読み込み
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // 管理画面の通知
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // 投稿一覧のカスタムカラム
        add_filter('manage_flp_lp_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_flp_lp_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        add_filter('manage_edit-flp_lp_sortable_columns', array($this, 'set_sortable_columns'));
        
        // 投稿一覧の行アクション
        add_filter('post_row_actions', array($this, 'add_row_actions'), 10, 2);
        
        // 投稿一覧のフィルター
        add_action('restrict_manage_posts', array($this, 'add_admin_filters'));
        add_filter('parse_query', array($this, 'filter_posts_by_meta'));
    }

    /**
     * 管理画面でのスクリプト・スタイル読み込み
     *
     * @param string $hook 現在のページフック
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;

        // LP編集画面でのみ読み込み
        if ($post_type === 'flp_lp' && in_array($hook, array('post.php', 'post-new.php'))) {
            // WordPress標準のメディア、jQuery UI等を読み込み
            wp_enqueue_media();
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-css', '//ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css', array(), '1.12.1');
            
            // カラーピッカー
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            
            // プラグイン専用の管理画面スタイル・スクリプト
            wp_enqueue_style(
                'flp-admin-css',
                FLP_ASSETS_URL . 'css/admin.css',
                array('wp-color-picker'),
                FLP_VERSION
            );
            
            wp_enqueue_script(
                'flp-admin-js',
                FLP_ASSETS_URL . 'js/admin.js',
                array('jquery', 'jquery-ui-datepicker', 'wp-color-picker'),
                FLP_VERSION,
                true
            );
            
            // AJAX用のデータを渡す
            wp_localize_script('flp-admin-js', 'flp_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('flp_admin_nonce'),
                'strings' => array(
                    'confirm_delete' => __('本当に削除しますか？', 'finelive-lp'),
                    'image_select' => __('画像を選択', 'finelive-lp'),
                    'image_remove' => __('画像を削除', 'finelive-lp'),
                ),
            ));
        }

        // クリックレポート画面
        if ($hook === 'toplevel_page_flp_lp_clicks_report') {
            wp_enqueue_script('jquery');
            wp_enqueue_style('flp-admin-css', FLP_ASSETS_URL . 'css/admin.css', array(), FLP_VERSION);
        }

        // 使い方ガイド画面
        if (isset($_GET['page']) && $_GET['page'] === 'flp-lp-usage') {
            wp_enqueue_style('flp-admin-css', FLP_ASSETS_URL . 'css/admin.css', array(), FLP_VERSION);
        }
    }

    /**
     * 管理画面の通知
     */
    public function admin_notices() {
        // 初回有効化時のウェルカムメッセージ
        if (get_transient('flp_activation_notice')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php _e('FineLive Multi LP Display', 'finelive-lp'); ?></strong>
                    <?php _e('が有効化されました！', 'finelive-lp'); ?>
                    <a href="<?php echo admin_url('edit.php?post_type=flp_lp'); ?>"><?php _e('最初のLPを作成', 'finelive-lp'); ?></a>
                    <?php _e('するか、', 'finelive-lp'); ?>
                    <a href="<?php echo admin_url('edit.php?post_type=flp_lp&page=flp-lp-usage'); ?>" target="_blank"><?php _e('使い方ガイド', 'finelive-lp'); ?></a>
                    <?php _e('をご覧ください。', 'finelive-lp'); ?>
                </p>
            </div>
            <?php
            delete_transient('flp_activation_notice');
        }

        // バージョンアップ通知
        $current_version = get_option('flp_version');
        if (version_compare($current_version, FLP_VERSION, '<')) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong><?php _e('FineLive Multi LP Display', 'finelive-lp'); ?></strong>
                    <?php printf(__('がバージョン %s にアップデートされました。', 'finelive-lp'), FLP_VERSION); ?>
                    <a href="<?php echo admin_url('edit.php?post_type=flp_lp&page=flp-lp-usage'); ?>" target="_blank"><?php _e('新機能を確認', 'finelive-lp'); ?></a>
                </p>
            </div>
            <?php
            update_option('flp_version', FLP_VERSION);
        }
    }

    /**
     * 投稿一覧のカスタムカラム設定
     *
     * @param array $columns 既存のカラム
     * @return array
     */
    public function set_custom_columns($columns) {
        $new_columns = array();
        
        // チェックボックスとタイトルを維持
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        
        // カスタムカラム追加
        $new_columns['lp_id'] = __('ID', 'finelive-lp');
        $new_columns['shortcode'] = __('ショートコード', 'finelive-lp');
        $new_columns['display_period'] = __('表示期間', 'finelive-lp');
        $new_columns['button_clicks'] = __('ボタンクリック数', 'finelive-lp');
        $new_columns['last_modified'] = __('最終更新', 'finelive-lp');
        
        // 日付カラムを維持
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }

    /**
     * カスタムカラムの内容出力
     *
     * @param string $column カラム名
     * @param int $post_id 投稿ID
     */
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'lp_id':
                echo '<strong style="font-family: monospace;">' . $post_id . '</strong>';
                break;
                
            case 'shortcode':
                $shortcode = '[finelive_lp id="' . $post_id . '"]';
                printf(
                    '<input type="text" value="%s" readonly onclick="this.select()" style="width:100%%; font-family:monospace; font-size:11px;" title="%s">',
                    esc_attr($shortcode),
                    esc_attr__('クリックして選択してください', 'finelive-lp')
                );
                break;
                
            case 'display_period':
                $this->display_period_status($post_id);
                break;
                
            case 'button_clicks':
                $this->display_click_statistics($post_id);
                break;
                
            case 'last_modified':
                $modified_time = get_post_modified_time('U', false, $post_id);
                if ($modified_time) {
                    $time_diff = current_time('timestamp') - $modified_time;
                    
                    if ($time_diff < DAY_IN_SECONDS) {
                        echo human_time_diff($modified_time) . __('前', 'finelive-lp');
                    } else {
                        echo date_i18n(get_option('date_format'), $modified_time);
                    }
                } else {
                    echo '—';
                }
                break;
        }
    }

    /**
     * 表示期間ステータスの表示
     *
     * @param int $post_id 投稿ID
     */
    private function display_period_status($post_id) {
        $data = get_post_meta($post_id, 'flp_lp_data', true);
        $start_date = isset($data['display_start_date']) ? $data['display_start_date'] : '';
        $end_date = isset($data['display_end_date']) ? $data['display_end_date'] : '';
        
        if (empty($start_date) && empty($end_date)) {
            echo '<span style="color: #666;" title="' . esc_attr__('表示期間の制限なし', 'finelive-lp') . '">∞ ' . __('制限なし', 'finelive-lp') . '</span>';
            return;
        }
        
        $current_date = current_time('Y-m-d');
        $status_class = '';
        $status_text = '';
        $icon = '';
        
        if (!empty($start_date) && $current_date < $start_date) {
            $status_class = 'color: #d63638;';
            $status_text = __('未開始', 'finelive-lp');
            $icon = '⏳';
        } elseif (!empty($end_date) && $current_date > $end_date) {
            $status_class = 'color: #d63638;';
            $status_text = __('終了', 'finelive-lp');
            $icon = '🚫';
        } else {
            $status_class = 'color: #00a32a; font-weight: bold;';
            $status_text = __('表示中', 'finelive-lp');
            $icon = '✅';
        }
        
        echo '<div style="' . $status_class . '">' . $icon . ' ' . $status_text . '</div>';
        
        if (!empty($start_date) || !empty($end_date)) {
            echo '<small style="color: #666;">';
            if (!empty($start_date)) {
                echo __('開始:', 'finelive-lp') . ' ' . $start_date;
            }
            if (!empty($start_date) && !empty($end_date)) {
                echo '<br>';
            }
            if (!empty($end_date)) {
                echo __('終了:', 'finelive-lp') . ' ' . $end_date;
            }
            echo '</small>';
        }
    }

    /**
     * クリック統計の表示
     *
     * @param int $post_id 投稿ID
     */
    private function display_click_statistics($post_id) {
        $click_data = get_option('flp_lp_click_data', array());
        $total_clicks = 0;
        $today_clicks = 0;
        $current_date = date('Y-m-d');
        
        if (isset($click_data[$post_id])) {
            foreach ($click_data[$post_id] as $date => $date_data) {
                foreach ($date_data as $button_clicks) {
                    $total_clicks += intval($button_clicks);
                    if ($date === $current_date) {
                        $today_clicks += intval($button_clicks);
                    }
                }
            }
        }
        
        if ($total_clicks > 0) {
            echo '<div style="font-weight: bold;">' . number_format($total_clicks) . ' ' . __('総クリック', 'finelive-lp') . '</div>';
            if ($today_clicks > 0) {
                echo '<small style="color: #00a32a;">今日: ' . number_format($today_clicks) . '</small><br>';
            }
            echo '<small><a href="' . admin_url('admin.php?page=flp_lp_clicks_report&lp_id=' . $post_id) . '">' . __('詳細レポート', 'finelive-lp') . '</a></small>';
        } else {
            echo '<span style="color: #666;">0</span>';
        }
    }

    /**
     * ソート可能カラムの設定
     *
     * @param array $columns ソート可能カラム配列
     * @return array
     */
    public function set_sortable_columns($columns) {
        $columns['lp_id'] = 'ID';
        $columns['last_modified'] = 'modified';
        return $columns;
    }

    /**
     * 投稿一覧の行アクションを追加
     *
     * @param array $actions 既存のアクション
     * @param WP_Post $post 投稿オブジェクト
     * @return array
     */
    public function add_row_actions($actions, $post) {
        if ($post->post_type === 'flp_lp') {
            // プレビューリンク（管理者のみ）
            if (current_user_can('manage_options')) {
                $preview_link = add_query_arg(array(
                    'action' => 'flp_preview',
                    'post_id' => $post->ID,
                    'nonce' => wp_create_nonce('flp_preview_' . $post->ID),
                ), admin_url('admin-ajax.php'));
                
                $actions['preview'] = sprintf(
                    '<a href="%s" target="_blank" rel="noopener">%s</a>',
                    esc_url($preview_link),
                    __('プレビュー', 'finelive-lp')
                );
            }
            
            // ショートコードのコピー
            $shortcode = '[finelive_lp id="' . $post->ID . '"]';
            $actions['copy_shortcode'] = sprintf(
                '<a href="#" onclick="navigator.clipboard.writeText(\'%s\'); alert(\'ショートコードをコピーしました！\'); return false;" title="%s">%s</a>',
                esc_js($shortcode),
                esc_attr__('ショートコードをクリップボードにコピー', 'finelive-lp'),
                __('ショートコードをコピー', 'finelive-lp')
            );
        }
        
        return $actions;
    }

    /**
     * 管理画面でのフィルターを追加
     *
     * @param string $post_type 投稿タイプ
     */
    public function add_admin_filters($post_type) {
        if ($post_type !== 'flp_lp') {
            return;
        }
        
        // 表示ステータスフィルター
        $current_status = isset($_GET['display_status']) ? $_GET['display_status'] : '';
        ?>
        <select name="display_status">
            <option value=""><?php _e('全ての表示ステータス', 'finelive-lp'); ?></option>
            <option value="active" <?php selected($current_status, 'active'); ?>><?php _e('表示中', 'finelive-lp'); ?></option>
            <option value="scheduled" <?php selected($current_status, 'scheduled'); ?>><?php _e('予約中', 'finelive-lp'); ?></option>
            <option value="expired" <?php selected($current_status, 'expired'); ?>><?php _e('期限切れ', 'finelive-lp'); ?></option>
            <option value="unlimited" <?php selected($current_status, 'unlimited'); ?>><?php _e('期間制限なし', 'finelive-lp'); ?></option>
        </select>
        <?php
    }

    /**
     * メタ情報によるフィルタリング
     *
     * @param WP_Query $query WP_Queryオブジェクト
     */
    public function filter_posts_by_meta($query) {
        global $pagenow;
        
        if (!is_admin() || $pagenow !== 'edit.php' || !isset($_GET['post_type']) || $_GET['post_type'] !== 'flp_lp') {
            return;
        }
        
        if (isset($_GET['display_status']) && !empty($_GET['display_status'])) {
            $status = $_GET['display_status'];
            $current_date = current_time('Y-m-d');
            
            switch ($status) {
                case 'active':
                    $query->set('meta_query', array(
                        'relation' => 'AND',
                        array(
                            'relation' => 'OR',
                            array(
                                'key' => 'flp_lp_data',
                                'value' => '"display_start_date";s:0:""',
                                'compare' => 'LIKE'
                            ),
                            array(
                                'key' => 'flp_lp_data',
                                'value' => '"display_start_date";s:10:"' . $current_date . '"',
                                'compare' => '<='
                            )
                        ),
                        array(
                            'relation' => 'OR',
                            array(
                                'key' => 'flp_lp_data',
                                'value' => '"display_end_date";s:0:""',
                                'compare' => 'LIKE'
                            ),
                            array(
                                'key' => 'flp_lp_data',
                                'value' => '"display_end_date";s:10:"' . $current_date . '"',
                                'compare' => '>='
                            )
                        )
                    ));
                    break;
            }
        }
    }

    /**
     * メタボックス管理インスタンスを取得
     *
     * @return FLP_Meta_Boxes
     */
    public function get_meta_boxes() {
        return $this->meta_boxes;
    }

    /**
     * 管理メニュー管理インスタンスを取得
     *
     * @return FLP_Admin_Menu
     */
    public function get_admin_menu() {
        return $this->admin_menu;
    }

    /**
     * LP複製管理インスタンスを取得
     *
     * @return FLP_LP_Duplicator
     */
    public function get_lp_duplicator() {
        return $this->lp_duplicator;
    }
}
