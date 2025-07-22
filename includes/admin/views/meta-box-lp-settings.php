<?php
/**
 * LP設定メタボックスのテンプレート（修正版）
 * includes/admin/views/meta-box-lp-settings.php
 */

if (!defined('ABSPATH')) exit;
?>

<div class="flp-meta-box-content">
    
    <!-- 共通設定セクション -->
    <div class="flp-section">
        <h3><?php _e('共通設定', 'finelive-lp'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="flp_button_text"><?php _e('ボタンテキスト', 'finelive-lp'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="flp_button_text" 
                           name="flp_lp_data[button_text]" 
                           value="<?php echo esc_attr($data['button_text']); ?>" 
                           class="regular-text" 
                           placeholder="<?php esc_attr_e('例: 今すぐ申し込む', 'finelive-lp'); ?>">
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="flp_button_url"><?php _e('ボタンURL', 'finelive-lp'); ?></label>
                </th>
                <td>
                    <input type="url" 
                           id="flp_button_url" 
                           name="flp_lp_data[button_url]" 
                           value="<?php echo esc_attr($data['button_url']); ?>" 
                           class="large-text" 
                           placeholder="https://example.com">
                </td>
            </tr>
        </table>
    </div>

    <!-- ボタンデザイン設定セクション -->
    <div class="flp-section">
        <h3><?php _e('ボタンデザイン設定', 'finelive-lp'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('背景色', 'finelive-lp'); ?></th>
                <td>
                    <input type="text" 
                           name="flp_lp_data[btn_bg_color]" 
                           value="<?php echo esc_attr($data['btn_bg_color']); ?>" 
                           class="flp-color-picker" 
                           data-default-color="#ff4081">
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('文字色', 'finelive-lp'); ?></th>
                <td>
                    <input type="text" 
                           name="flp_lp_data[btn_text_color]" 
                           value="<?php echo esc_attr($data['btn_text_color']); ?>" 
                           class="flp-color-picker" 
                           data-default-color="#ffffff">
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('パディング（上下）', 'finelive-lp'); ?></th>
                <td>
                    <input type="number" 
                           name="flp_lp_data[btn_padding_tb]" 
                           value="<?php echo esc_attr($data['btn_padding_tb']); ?>" 
                           min="0" 
                           max="50" 
                           class="small-text"> px
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('パディング（左右）', 'finelive-lp'); ?></th>
                <td>
                    <input type="number" 
                           name="flp_lp_data[btn_padding_lr]" 
                           value="<?php echo esc_attr($data['btn_padding_lr']); ?>" 
                           min="0" 
                           max="100" 
                           class="small-text"> px
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('角の丸み', 'finelive-lp'); ?></th>
                <td>
                    <input type="number" 
                           name="flp_lp_data[btn_border_radius]" 
                           value="<?php echo esc_attr($data['btn_border_radius']); ?>" 
                           min="0" 
                           max="50" 
                           class="small-text"> px
                </td>
            </tr>
        </table>
    </div>

    <!-- LP表示期間設定セクション -->
    <div class="flp-section">
        <h3><?php _e('LP表示期間設定', 'finelive-lp'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="flp_display_start_date"><?php _e('表示開始日', 'finelive-lp'); ?></label>
                </th>
                <td>
                    <input type="date" 
                           id="flp_display_start_date" 
                           name="flp_lp_data[display_start_date]" 
                           value="<?php echo esc_attr($data['display_start_date']); ?>" 
                           class="flp-datepicker">
                    <p class="description"><?php _e('空欄の場合は即時表示', 'finelive-lp'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="flp_display_end_date"><?php _e('表示終了日', 'finelive-lp'); ?></label>
                </th>
                <td>
                    <input type="date" 
                           id="flp_display_end_date" 
                           name="flp_lp_data[display_end_date]" 
                           value="<?php echo esc_attr($data['display_end_date']); ?>" 
                           class="flp-datepicker">
                    <p class="description"><?php _e('空欄の場合は無期限表示', 'finelive-lp'); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <!-- スライダー設定セクション -->
    <div class="flp-section">
        <h3><?php _e('スライダー設定', 'finelive-lp'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="flp_slider_interval"><?php _e('スライダー切り替え時間', 'finelive-lp'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="flp_slider_interval" 
                           name="flp_lp_data[slider_interval]" 
                           value="<?php echo esc_attr($data['slider_interval']); ?>" 
                           min="1000" 
                           max="10000" 
                           step="100" 
                           class="regular-text"> 
                    <?php _e('ミリ秒', 'finelive-lp'); ?>
                </td>
            </tr>
        </table>

        <div class="flp-slider-images-container">
            <h4><?php _e('スライダー画像', 'finelive-lp'); ?></h4>
            <div id="flp_lp_slider" class="flp-image-items">
                <?php 
                // 初期データがない場合は空の配列をセット
                $slider_images = !empty($data['slider_images']) ? $data['slider_images'] : array('');
                foreach ($slider_images as $url): 
                ?>
                <div class="item">
                    <input type="hidden" name="flp_lp_data[slider_url][]" class="url" value="<?php echo esc_url($url); ?>">
                    <img src="<?php echo esc_url($url); ?>" style="max-width:120px; display:<?php echo $url ? 'block' : 'none'; ?>;">
                    <p>
                        <button type="button" class="button select_slider"><?php _e('選択', 'finelive-lp'); ?></button> 
                        <button type="button" class="button remove_slider"><?php _e('削除', 'finelive-lp'); ?></button>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
            <p>
                <button type="button" id="add_slider" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span>
                    <?php _e('スライド画像を追加', 'finelive-lp'); ?>
                </button>
            </p>
        </div>
    </div>

    <!-- 静的画像設定セクション -->
    <div class="flp-section">
        <h3><?php _e('静的画像設定 (縦表示)', 'finelive-lp'); ?></h3>
        
        <div id="flp_lp_static" class="flp-image-items">
            <?php 
            // 初期データがない場合は1つの空アイテムを表示
            $static_images = !empty($data['static_images']) ? $data['static_images'] : array(array('url' => '', 'show_button' => 0, 'show_slider' => 0));
            foreach ($static_images as $i => $img): 
                $url = $img['url'] ?? '';
                $show_button = $img['show_button'] ?? 0;
                $show_slider = $img['show_slider'] ?? 0;
            ?>
            <div class="item">
                <input type="hidden" name="flp_lp_data[static_url][]" class="url" value="<?php echo esc_url($url); ?>">
                <img src="<?php echo esc_url($url); ?>" style="max-width:120px; display:<?php echo $url ? 'block' : 'none'; ?>;">
                
                <div class="flp-image-options">
                    <p>
                        <label>
                            <input type="checkbox" 
                                   name="flp_lp_data[show_button][<?php echo $i; ?>]" 
                                   value="1" 
                                   <?php checked($show_button, 1); ?>>
                            <?php _e('この画像の下にボタンを表示', 'finelive-lp'); ?>
                        </label>
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" 
                                   name="flp_lp_data[show_slider][<?php echo $i; ?>]" 
                                   value="1" 
                                   <?php checked($show_slider, 1); ?>>
                            <?php _e('この画像の下にスライダーを表示', 'finelive-lp'); ?>
                        </label>
                    </p>
                </div>
                
                <p class="flp-image-actions">
                    <button type="button" class="button select"><?php _e('画像選択', 'finelive-lp'); ?></button> 
                    <button type="button" class="button remove"><?php _e('削除', 'finelive-lp'); ?></button>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
        
        <p>
            <button type="button" id="add_static" class="button button-secondary">
                <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span>
                <?php _e('静的画像を追加', 'finelive-lp'); ?>
            </button>
        </p>
    </div>

</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // メディアアップローダー
    var frame;
    
    function openMediaUploader(callback) {
        if (frame) {
            frame.off('select');
            frame.on('select', callback);
            frame.open();
            return;
        }
        
        frame = wp.media({
            title: '<?php echo esc_js(__('画像を選択', 'finelive-lp')); ?>',
            button: {
                text: '<?php echo esc_js(__('画像を使用', 'finelive-lp')); ?>'
            },
            library: {
                type: 'image'
            },
            multiple: false
        });
        
        frame.on('select', callback);
        frame.open();
    }
    
    // 静的画像の選択
    $(document).on('click', '.select', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $item = $button.closest('.item');
        
        openMediaUploader(function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $item.find('.url').val(attachment.url);
            $item.find('img').attr('src', attachment.url).show();
        });
    });
    
    // スライダー画像の選択
    $(document).on('click', '.select_slider', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $item = $button.closest('.item');
        
        openMediaUploader(function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $item.find('.url').val(attachment.url);
            $item.find('img').attr('src', attachment.url).show();
        });
    });
    
    // 静的画像の削除
    $(document).on('click', '.remove', function(e) {
        e.preventDefault();
        if (confirm('<?php echo esc_js(__('この画像を削除しますか？', 'finelive-lp')); ?>')) {
            $(this).closest('.item').remove();
            updateStaticImageIndexes();
        }
    });
    
    // スライダー画像の削除
    $(document).on('click', '.remove_slider', function(e) {
        e.preventDefault();
        if (confirm('<?php echo esc_js(__('この画像を削除しますか？', 'finelive-lp')); ?>')) {
            $(this).closest('.item').remove();
        }
    });
    
    // 静的画像の追加
    $('#add_static').on('click', function(e) {
        e.preventDefault();
        var index = $('#flp_lp_static .item').length;
        var html = '<div class="item">' +
            '<input type="hidden" name="flp_lp_data[static_url][]" class="url" value="">' +
            '<img src="" style="max-width:120px; display:none;">' +
            '<div class="flp-image-options">' +
                '<p><label><input type="checkbox" name="flp_lp_data[show_button][' + index + ']" value="1"> <?php echo esc_js(__('この画像の下にボタンを表示', 'finelive-lp')); ?></label></p>' +
                '<p><label><input type="checkbox" name="flp_lp_data[show_slider][' + index + ']" value="1"> <?php echo esc_js(__('この画像の下にスライダーを表示', 'finelive-lp')); ?></label></p>' +
            '</div>' +
            '<p class="flp-image-actions">' +
                '<button type="button" class="button select"><?php echo esc_js(__('画像選択', 'finelive-lp')); ?></button> ' +
                '<button type="button" class="button remove"><?php echo esc_js(__('削除', 'finelive-lp')); ?></button>' +
            '</p>' +
        '</div>';
        
        $('#flp_lp_static').append(html);
    });
    
    // スライダー画像の追加
    $('#add_slider').on('click', function(e) {
        e.preventDefault();
        var html = '<div class="item">' +
            '<input type="hidden" name="flp_lp_data[slider_url][]" class="url" value="">' +
            '<img src="" style="max-width:120px; display:none;">' +
            '<p>' +
                '<button type="button" class="button select_slider"><?php echo esc_js(__('選択', 'finelive-lp')); ?></button> ' +
                '<button type="button" class="button remove_slider"><?php echo esc_js(__('削除', 'finelive-lp')); ?></button>' +
            '</p>' +
        '</div>';
        
        $('#flp_lp_slider').append(html);
    });
    
    // 静的画像のインデックスを更新
    function updateStaticImageIndexes() {
        $('#flp_lp_static .item').each(function(index) {
            $(this).find('input[name^="flp_lp_data[show_button]"]').attr('name', 'flp_lp_data[show_button][' + index + ']');
            $(this).find('input[name^="flp_lp_data[show_slider]"]').attr('name', 'flp_lp_data[show_slider][' + index + ']');
        });
    }
    
    // カラーピッカーの初期化
    $('.flp-color-picker').wpColorPicker();
    
    // 日付ピッカーの初期化（jQuery UIが読み込まれている場合）
    if ($.fn.datepicker) {
        $('.flp-datepicker').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true
        });
    }
    
});
</script>