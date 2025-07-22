<?php
/**
 * フロントエンドメインクラス
 */

if (!defined('ABSPATH')) exit;

class FLP_Frontend {

    /**
     * ショートコード管理インスタンス
     */
    private $shortcode;

    /**
     * アセット管理インスタンス
     */
    private $assets;

    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->init_hooks();
        $this->shortcode = new FLP_Shortcode();
        $this->assets = new FLP_Assets();
    }

    /**
     * 基本フックの初期化
     */
    private function init_hooks() {
        // フロントエンドでのスクリプト・スタイル読み込み
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // <head>にメタ情報を追加
        add_action('wp_head', array($this, 'add_meta_tags'));
        
        // 構造化データの追加
        add_action('wp_head', array($this, 'add_structured_data'));
        
        // フロントエンド用のAJAX URL設定
        add_action('wp_head', array($this, 'add_ajax_variables'));
    }

    /**
     * フロントエンドアセットの読み込み
     */
    public function enqueue_frontend_assets() {
        global $post;
        
        // ショートコードが使用されている場合のみ読み込み
        if (!$this->should_load_assets()) {
            return;
        }

        // CSS読み込み
        wp_enqueue_style(
            'flp-frontend',
            FLP_ASSETS_URL . 'css/frontend.css',
            array(),
            FLP_VERSION
        );

        // JavaScript読み込み
        wp_enqueue_script(
            'flp-frontend',
            FLP_ASSETS_URL . 'js/frontend.js',
            array('jquery'),
            FLP_VERSION,
            true
        );

        // AJAX用のデータをローカライズ
        wp_localize_script('flp-frontend', 'flp_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('flp_frontend_nonce'),
            'strings' => array(
                'loading' => __('読み込み中...', 'finelive-lp'),
                'error' => __('エラーが発生しました。', 'finelive-lp'),
                'success' => __('送信されました。', 'finelive-lp'),
            ),
        ));

        /**
         * フロントエンドアセット読み込み後のアクション
         */
        do_action('flp_frontend_assets_loaded');
    }

    /**
     * アセットを読み込むべきかどうかを判定
     *
     * @return bool
     */
    private function should_load_assets() {
        global $post;

        // 投稿・固定ページでショートコードが使用されている場合
        if ($post && has_shortcode($post->post_content, 'finelive_lp')) {
            return true;
        }

        // ウィジェットでショートコードが使用されている場合の検出
        if (is_active_widget(false, false, 'text')) {
            $widgets = wp_get_sidebars_widgets();
            foreach ($widgets as $sidebar => $widget_list) {
                if (is_array($widget_list)) {
                    foreach ($widget_list as $widget_id) {
                        if (strpos($widget_id, 'text') === 0) {
                            $widget_options = get_option('widget_text');
                            foreach ($widget_options as $instance) {
                                if (isset($instance['text']) && has_shortcode($instance['text'], 'finelive_lp')) {
                                    return true;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Gutenbergブロックでの使用検出
        if ($post && has_blocks($post->post_content)) {
            $blocks = parse_blocks($post->post_content);
            if ($this->has_shortcode_in_blocks($blocks, 'finelive_lp')) {
                return true;
            }
        }

        /**
         * カスタムでアセット読み込みが必要かどうかを判定するフィルター
         *
         * @param bool $should_load 読み込むべきかどうか
         */
        return apply_filters('flp_should_load_assets', false);
    }

    /**
     * ブロック内でショートコードが使用されているかチェック
     *
     * @param array $blocks ブロック配列
     * @param string $shortcode_tag ショートコードタグ
     * @return bool
     */
    private function has_shortcode_in_blocks($blocks, $shortcode_tag) {
        foreach ($blocks as $block) {
            // ショートコードブロック
            if ($block['blockName'] === 'core/shortcode' && isset($block['innerHTML'])) {
                if (has_shortcode($block['innerHTML'], $shortcode_tag)) {
                    return true;
                }
            }
            
            // パラグラフブロックなど、テキストを含むブロック
            if (isset($block['innerHTML']) && has_shortcode($block['innerHTML'], $shortcode_tag)) {
                return true;
            }
            
            // 入れ子ブロックの再帰チェック
            if (isset($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                if ($this->has_shortcode_in_blocks($block['innerBlocks'], $shortcode_tag)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * メタタグの追加
     */
    public function add_meta_tags() {
        if (!$this->should_load_assets()) {
            return;
        }

        // LP用のメタタグを追加
        echo "\n<!-- FineLive Multi LP Display Meta Tags -->\n";
        echo '<meta name="generator" content="FineLive Multi LP Display ' . FLP_VERSION . '">' . "\n";
        
        // パフォーマンス向上のためのプリロード
        echo '<link rel="preconnect" href="' . esc_url(FLP_ASSETS_URL) . '">' . "\n";
    }

    /**
     * 構造化データの追加
     */
    public function add_structured_data() {
        global $post;
        
        if (!$post || !has_shortcode($post->post_content, 'finelive_lp')) {
            return;
        }

        // LPが含まれるページの構造化データを追加
        $structured_data = array(
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => get_the_title($post->ID),
            'description' => get_the_excerpt($post->ID) ?: wp_trim_words(strip_tags($post->post_content), 20),
            'url' => get_permalink($post->ID),
            'mainEntity' => array(
                '@type' => 'CreativeWork',
                'name' => 'Landing Page',
                'creator' => array(
                    '@type' => 'Organization',
                    'name' => get_bloginfo('name')
                )
            )
        );

        /**
         * LP構造化データのフィルター
         *
         * @param array $structured_data 構造化データ
         * @param WP_Post $post 投稿オブジェクト
         */
        $structured_data = apply_filters('flp_structured_data', $structured_data, $post);

        echo '<script type="application/ld+json">';
        echo json_encode($structured_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo '</script>' . "\n";
    }

    /**
     * フロントエンド用のJavaScript変数を追加
     */
    public function add_ajax_variables() {
        if (!$this->should_load_assets()) {
            return;
        }

        ?>
        <script>
        window.flp_vars = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('flp_frontend_nonce'); ?>',
            site_url: '<?php echo site_url(); ?>',
            plugin_url: '<?php echo FLP_PLUGIN_URL; ?>',
            version: '<?php echo FLP_VERSION; ?>',
            debug: <?php echo (defined('WP_DEBUG') && WP_DEBUG) ? 'true' : 'false'; ?>
        };
        </script>
        <?php
    }

    /**
     * LP表示期間のチェック
     *
     * @param int $lp_id LP ID
     * @return bool 表示可能かどうか
     */
    public function is_lp_displayable($lp_id) {
        // 投稿が存在するかチェック
        $post = get_post($lp_id);
        if (!$post || $post->post_type !== 'flp_lp' || $post->post_status !== 'publish') {
            return false;
        }

        // LP設定データの取得
        $data = FLP_Meta_Boxes::get_lp_data($lp_id);
        
        $current_date = current_time('Y-m-d');
        
        // 表示開始日のチェック
        if (!empty($data['display_start_date']) && $current_date < $data['display_start_date']) {
            return false;
        }
        
        // 表示終了日のチェック
        if (!empty($data['display_end_date']) && $current_date > $data['display_end_date']) {
            return false;
        }

        /**
         * LP表示可能性のフィルター
         *
         * @param bool $displayable 表示可能かどうか
         * @param int $lp_id LP ID
         * @param array $data LP設定データ
         */
        return apply_filters('flp_is_lp_displayable', true, $lp_id, $data);
    }

    /**
     * LPのレンダリング
     *
     * @param int $lp_id LP ID
     * @return string レンダリング結果のHTML
     */
    public function render_lp($lp_id) {
        // 表示可能かチェック
        if (!$this->is_lp_displayable($lp_id)) {
            // 管理者には警告を表示
            if (current_user_can('manage_options')) {
                return '<div class="flp-admin-notice" style="padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; border-radius: 4px;">' . 
                       sprintf(__('LP ID %d は表示期間外、または削除されています。', 'finelive-lp'), $lp_id) . 
                       '</div>';
            }
            return '';
        }

        $data = FLP_Meta_Boxes::get_lp_data($lp_id);

        // 静的画像が設定されていない場合
        if (empty($data['static_images'])) {
            if (current_user_can('manage_options')) {
                return '<div class="flp-admin-notice" style="padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; border-radius: 4px;">' . 
                       sprintf(__('LP ID %d に静的画像が設定されていません。', 'finelive-lp'), $lp_id) . 
                       ' <a href="' . get_edit_post_link($lp_id) . '">' . __('編集', 'finelive-lp') . '</a>' .
                       '</div>';
            }
            return '';
        }

        ob_start();
        
        echo '<div class="flp_lp_wrap" data-lp-id="' . esc_attr($lp_id) . '">';

        foreach ($data['static_images'] as $index => $image) {
            $this->render_lp_block($lp_id, $index, $image, $data);
        }

        echo '</div>';

        // 必要なスタイル・スクリプトを出力
        $this->output_inline_styles($data);
        $this->output_inline_scripts($lp_id, $data);

        /**
         * LP出力後のアクション
         *
         * @param int $lp_id LP ID
         * @param array $data LP設定データ
         */
        do_action('flp_after_render_lp', $lp_id, $data);

        return ob_get_clean();
    }

    /**
     * LPブロック（静的画像 + ボタン/スライダー）のレンダリング
     *
     * @param int $lp_id LP ID
     * @param int $index ブロックインデックス
     * @param array $image 画像データ
     * @param array $data 全体設定データ
     */
    private function render_lp_block($lp_id, $index, $image, $data) {
        $image_url = $image['url'] ?? '';
        $show_button = $image['show_button'] ?? 0;
        $show_slider = $image['show_slider'] ?? 0;

        if (empty($image_url)) {
            return;
        }

        echo '<div class="flp_block" data-block-index="' . esc_attr($index) . '">';

        // 静的画像の表示
        printf(
            '<img src="%s" alt="%s" class="flp_static_image" style="width:100%%; height:auto; display:block;" loading="lazy">',
            esc_url($image_url),
            esc_attr(sprintf(__('LP画像 %d', 'finelive-lp'), $index + 1))
        );

        // スライダーの表示
        if ($show_slider && !empty($data['slider_images'])) {
            $this->render_slider($lp_id, $index, $data['slider_images'], $data['slider_interval']);
        }

        // ボタンの表示
        if ($show_button && !empty($data['button_url'])) {
            $this->render_button($lp_id, $index, $data);
        }

        echo '</div>';
    }

    /**
     * スライダーのレンダリング
     *
     * @param int $lp_id LP ID
     * @param int $index ブロックインデックス
     * @param array $slider_images スライダー画像配列
     * @param int $interval 切り替え間隔
     */
    private function render_slider($lp_id, $index, $slider_images, $interval) {
        if (empty($slider_images)) {
            return;
        }

        $slider_id = 'flp_slider_' . $lp_id . '_' . $index;
        
        echo '<div id="' . esc_attr($slider_id) . '" class="flp_slider" data-interval="' . esc_attr($interval) . '" style="position:relative; overflow:hidden; margin-top:10px;">';
        echo '<div class="flp_slides_container" style="display:flex; transition:transform 0.5s ease;">';
        
        foreach ($slider_images as $slide_index => $slide_url) {
            printf(
                '<img src="%s" alt="%s" class="flp_slide_img" style="width:100%%; flex-shrink:0;" loading="lazy">',
                esc_url($slide_url),
                esc_attr(sprintf(__('スライド画像 %d', 'finelive-lp'), $slide_index + 1))
            );
        }
        
        echo '</div>';
        
        // スライダーの操作ボタン（複数画像がある場合）
        if (count($slider_images) > 1) {
            echo '<div class="flp_slider_controls" style="position:absolute; bottom:10px; left:50%; transform:translateX(-50%); display:flex; gap:5px;">';
            foreach ($slider_images as $slide_index => $slide_url) {
                echo '<button class="flp_slider_dot" data-slide="' . $slide_index . '" style="width:12px; height:12px; border-radius:50%; border:none; background:rgba(255,255,255,0.5); cursor:pointer;"></button>';
            }
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * ボタンのレンダリング
     *
     * @param int $lp_id LP ID
     * @param int $index ブロックインデックス
     * @param array $data LP設定データ
     */
    private function render_button($lp_id, $index, $data) {
        $button_id = 'flp_btn_' . $lp_id . '_' . $index;
        
        // ボタンスタイルの生成
        $button_style = sprintf(
            'display:inline-block; text-decoration:none; animation:flp_pulse 1.8s infinite; background-color:%s; color:%s; padding:%spx %spx; border-radius:%spx; border:none; cursor:pointer; font-weight:bold; font-size:16px;',
            esc_attr($data['btn_bg_color'] ?? '#ff4081'),
            esc_attr($data['btn_text_color'] ?? '#ffffff'),
            esc_attr($data['btn_padding_tb'] ?? '15'),
            esc_attr($data['btn_padding_lr'] ?? '30'),
            esc_attr($data['btn_border_radius'] ?? '5')
        );

        echo '<div class="flp_btn_wrap" style="text-align:center; margin:20px 0;">';
        printf(
            '<a href="%s" id="%s" class="flp_btn" data-btn="%s" data-lp-id="%s" style="%s">%s</a>',
            esc_url($data['button_url']),
            esc_attr($button_id),
            esc_attr($button_id),
            esc_attr($lp_id),
            $button_style,
            esc_html($data['button_text'] ?? __('応募はこちら', 'finelive-lp'))
        );
        echo '</div>';
    }

    /**
     * インラインスタイルの出力
     *
     * @param array $data LP設定データ
     */
    private function output_inline_styles($data) {
        ?>
        <style>
        @keyframes flp_pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .flp_slider {
            cursor: pointer;
        }
        .flp_slider_dot.active {
            background: rgba(255,255,255,1) !important;
        }
        .flp_btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
        }
        </style>
        <?php
    }

    /**
     * インラインスクリプトの出力
     *
     * @param int $lp_id LP ID
     * @param array $data LP設定データ
     */
    private function output_inline_scripts($lp_id, $data) {
        ?>
        <script>
        (function() {
            // スライダー機能の初期化
            document.querySelectorAll('.flp_slider').forEach(function(sliderElement) {
                initSlider(sliderElement);
            });

            // ボタンクリック追跡の初期化
            document.querySelectorAll('.flp_btn').forEach(function(buttonElement) {
                initButtonTracking(buttonElement);
            });

            function initSlider(sliderElement) {
                let currentIndex = 0;
                const slidesContainer = sliderElement.querySelector('.flp_slides_container');
                const slides = sliderElement.querySelectorAll('.flp_slide_img');
                const dots = sliderElement.querySelectorAll('.flp_slider_dot');
                const slideCount = slides.length;
                const intervalTime = parseInt(sliderElement.getAttribute('data-interval')) || 4000;
                let autoplayInterval;

                if (slideCount <= 1) return;

                // ドット更新
                function updateDots() {
                    dots.forEach((dot, index) => {
                        dot.classList.toggle('active', index === currentIndex);
                    });
                }

                // スライド移動
                function goToSlide(index) {
                    currentIndex = index;
                    slidesContainer.style.transform = 'translateX(' + (-currentIndex * 100) + '%)';
                    updateDots();
                }

                // 次のスライドへ
                function goToNextSlide() {
                    currentIndex = (currentIndex + 1) % slideCount;
                    goToSlide(currentIndex);
                }

                // オートプレイ開始
                function startAutoplay() {
                    clearInterval(autoplayInterval);
                    autoplayInterval = setInterval(goToNextSlide, intervalTime);
                }

                // ドットクリック処理
                dots.forEach((dot, index) => {
                    dot.addEventListener('click', function(e) {
                        e.stopPropagation();
                        goToSlide(index);
                        startAutoplay();
                    });
                });

                // スライダークリック処理
                sliderElement.addEventListener('click', function() {
                    goToNextSlide();
                    startAutoplay();
                });

                // ホバー時はオートプレイ停止
                sliderElement.addEventListener('mouseenter', () => clearInterval(autoplayInterval));
                sliderElement.addEventListener('mouseleave', startAutoplay);

                // 初期化
                updateDots();
                startAutoplay();
            }

            function initButtonTracking(buttonElement) {
                buttonElement.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const btn = this.getAttribute('data-btn');
                    const lpId = this.getAttribute('data-lp-id');
                    const href = this.href;

                    // クリック追跡の送信
                    fetch(window.flp_vars.ajax_url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                        },
                        body: new URLSearchParams({
                            'action': 'flp_lp_track_click',
                            'btn': btn,
                            'lp_id': lpId,
                            '_wpnonce': window.flp_vars.nonce
                        })
                    })
                    .catch(error => console.error('Click tracking error:', error))
                    .finally(() => {
                        // リンクに移動
                        if (href) {
                            window.location.href = href;
                        }
                    });
                });
            }
        })();
        </script>
        <?php
    }

    /**
     * ショートコード管理インスタンスを取得
     *
     * @return FLP_Shortcode
     */
    public function get_shortcode() {
        return $this->shortcode;
    }

    /**
     * アセット管理インスタンスを取得
     *
     * @return FLP_Assets
     */
    public function get_assets() {
        return $this->assets;
    }
}
