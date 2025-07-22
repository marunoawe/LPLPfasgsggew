/**
 * FineLive Multi LP Display - Admin JavaScript
 */

(function($) {
    'use strict';

    // グローバル変数
    var FLP_Admin = {
        mediaFrame: null,
        autosaveTimer: null,
        isLoading: false
    };

    /**
     * DOM読み込み完了時の初期化
     */
    $(document).ready(function() {
        FLP_Admin.init();
    });

    /**
     * 初期化処理
     */
    FLP_Admin.init = function() {
        this.initColorPickers();
        this.initDatePickers();
        this.initMediaUploader();
        this.initImageManagement();
        this.initButtonPreview();
        this.initFormValidation();
        this.initAutosave();
        this.initTooltips();
        this.initConfirmDialogs();
        
        console.log('FLP Admin initialized');
    };

    /**
     * カラーピッカーの初期化
     */
    FLP_Admin.initColorPickers = function() {
        if (typeof $.fn.wpColorPicker === 'function') {
            $('.flp-color-picker').wpColorPicker({
                change: function(event, ui) {
                    FLP_Admin.updateButtonPreview();
                    FLP_Admin.triggerAutosave();
                },
                clear: function() {
                    FLP_Admin.updateButtonPreview();
                    FLP_Admin.triggerAutosave();
                }
            });
        }
    };

    /**
     * 日付ピッカーの初期化
     */
    FLP_Admin.initDatePickers = function() {
        if (typeof $.fn.datepicker === 'function') {
            $('.flp-datepicker').datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true,
                yearRange: '-1:+10',
                onSelect: function() {
                    FLP_Admin.triggerAutosave();
                }
            });
        }
    };

    /**
     * メディアアップローダーの初期化
     */
    FLP_Admin.initMediaUploader = function() {
        var self = this;

        /**
         * メディアフレームを開く
         */
        function openMediaFrame(callback, options) {
            options = options || {};
            
            if (self.mediaFrame) {
                self.mediaFrame.off('select');
            }

            self.mediaFrame = wp.media({
                title: options.title || flp_admin.strings.image_select,
                library: { type: 'image' },
                multiple: options.multiple || false,
                button: {
                    text: options.buttonText || flp_admin.strings.image_select
                }
            });

            self.mediaFrame.on('select', callback);
            self.mediaFrame.open();
        }

        // 静的画像選択
        $(document).on('click', '.flp-select-image, .select', function(e) {
            e.preventDefault();
            
            var $item = $(this).closest('.item');
            var $button = $(this);
            
            $button.prop('disabled', true).text('選択中...');

            openMediaFrame(function() {
                var attachment = self.mediaFrame.state().get('selection').first().toJSON();
                
                $item.find('.url').val(attachment.url);
                $item.find('img').attr('src', attachment.url).show();
                
                $button.prop('disabled', false).text($button.hasClass('select') ? '画像選択' : '選択');
                
                FLP_Admin.triggerAutosave();
            });
        });

        // スライダー画像選択
        $(document).on('click', '.select_slider', function(e) {
            e.preventDefault();
            
            var $item = $(this).closest('.item');
            var $button = $(this);
            
            $button.prop('disabled', true).text('選択中...');

            openMediaFrame(function() {
                var attachment = self.mediaFrame.state().get('selection').first().toJSON();
                
                $item.find('.url').val(attachment.url);
                $item.find('img').attr('src', attachment.url).show();
                
                $button.prop('disabled', false).text('選択');
                
                FLP_Admin.triggerAutosave();
            });
        });
    };

    /**
     * 画像管理機能の初期化
     */
    FLP_Admin.initImageManagement = function() {
        var self = this;

        // 画像削除
        $(document).on('click', '.flp-remove-image, .remove, .remove_slider', function(e) {
            e.preventDefault();
            
            if (!confirm(flp_admin.strings.confirm_delete)) {
                return;
            }

            var $item = $(this).closest('.item');
            $item.fadeOut(300, function() {
                $item.remove();
                self.reindexStaticImages();
                self.triggerAutosave();
            });
        });

        // 静的画像追加
        $(document).on('click', '#add_static, .flp-add-image', function(e) {
            e.preventDefault();
            
            var index = $('#flp_lp_static .item').length;
            var html = self.generateStaticImageHTML(index);
            
            var $newItem = $(html).hide();
            $('#flp_lp_static').append($newItem);
            $newItem.fadeIn(300);
        });

        // スライダー画像追加
        $(document).on('click', '#add_slider', function(e) {
            e.preventDefault();
            
            var html = self.generateSliderImageHTML();
            var $newItem = $(html).hide();
            $('#flp_lp_slider').append($newItem);
            $newItem.fadeIn(300);
        });

        // 画像のドラッグ&ドロップ並び替え（jQueryUI Sortableが利用可能な場合）
        if (typeof $.fn.sortable === 'function') {
            $('#flp_lp_static, #flp_lp_slider').sortable({
                items: '.item',
                placeholder: 'flp-sortable-placeholder',
                tolerance: 'pointer',
                update: function() {
                    self.reindexStaticImages();
                    self.triggerAutosave();
                }
            });
        }
    };

    /**
     * 静的画像のHTMLを生成
     */
    FLP_Admin.generateStaticImageHTML = function(index) {
        return `
            <div class="item">
                <input type="hidden" name="flp_lp_data[static_url][]" class="url" value="">
                <img src="" style="max-width:120px;display:none;">
                <div class="flp-image-options">
                    <p><label><input type="checkbox" name="flp_lp_data[show_button][${index}]" value="1"> この画像の下にボタンを表示</label></p>
                    <p><label><input type="checkbox" name="flp_lp_data[show_slider][${index}]" value="1"> この画像の下にスライダーを表示</label></p>
                </div>
                <p class="flp-image-actions">
                    <button type="button" class="button select">画像選択</button> 
                    <button type="button" class="button remove">削除</button>
                </p>
            </div>
        `;
    };

    /**
     * スライダー画像のHTMLを生成
     */
    FLP_Admin.generateSliderImageHTML = function() {
        return `
            <div class="item">
                <input type="hidden" name="flp_lp_data[slider_url][]" class="url" value="">
                <img src="" style="max-width:120px;display:none;">
                <p>
                    <button type="button" class="button select_slider">選択</button> 
                    <button type="button" class="button remove_slider">削除</button>
                </p>
            </div>
        `;
    };

    /**
     * 静的画像のインデックスを再調整
     */
    FLP_Admin.reindexStaticImages = function() {
        $('#flp_lp_static .item').each(function(i) {
            $(this).find('input[name^="flp_lp_data[show_button]"]')
                   .attr('name', `flp_lp_data[show_button][${i}]`);
            $(this).find('input[name^="flp_lp_data[show_slider]"]')
                   .attr('name', `flp_lp_data[show_slider][${i}]`);
        });
    };

    /**
     * ボタンプレビューの初期化と更新
     */
    FLP_Admin.initButtonPreview = function() {
        this.updateButtonPreview();
        
        // 各入力フィールドの変更時にプレビューを更新
        $(document).on('input change', [
            'input[name="flp_lp_data[button_text]"]',
            'input[name="flp_lp_data[btn_bg_color]"]',
            'input[name="flp_lp_data[btn_text_color]"]',
            'input[name="flp_lp_data[btn_padding_tb]"]',
            'input[name="flp_lp_data[btn_padding_lr]"]',
            'input[name="flp_lp_data[btn_border_radius]"]'
        ].join(', '), function() {
            FLP_Admin.updateButtonPreview();
            FLP_Admin.triggerAutosave();
        });
    };

    /**
     * ボタンプレビューの更新
     */
    FLP_Admin.updateButtonPreview = function() {
        var $preview = $('#flp-button-preview');
        
        if ($preview.length === 0) {
            return;
        }

        var buttonText = $('input[name="flp_lp_data[button_text]"]').val() || 'ボタンテキスト';
        var bgColor = $('input[name="flp_lp_data[btn_bg_color]"]').val() || '#ff4081';
        var textColor = $('input[name="flp_lp_data[btn_text_color]"]').val() || '#ffffff';
        var paddingTB = $('input[name="flp_lp_data[btn_padding_tb]"]').val() || '15';
        var paddingLR = $('input[name="flp_lp_data[btn_padding_lr]"]').val() || '30';
        var borderRadius = $('input[name="flp_lp_data[btn_border_radius]"]').val() || '5';

        $preview.css({
            'background-color': bgColor,
            'color': textColor,
            'padding': `${paddingTB}px ${paddingLR}px`,
            'border-radius': `${borderRadius}px`
        }).text(buttonText);
    };

    /**
     * フォームバリデーションの初期化
     */
    FLP_Admin.initFormValidation = function() {
        // リアルタイムバリデーション
        $(document).on('blur', 'input[name="flp_lp_data[button_url]"]', function() {
            FLP_Admin.validateURL($(this));
        });

        $(document).on('blur', 'input[name^="flp_lp_data[btn_padding"], input[name*="border_radius"]', function() {
            FLP_Admin.validateNumeric($(this));
        });

        // フォーム送信時のバリデーション
        $('form').on('submit', function(e) {
            if (!FLP_Admin.validateForm()) {
                e.preventDefault();
                FLP_Admin.showNotification('入力内容に問題があります。赤く表示された項目を確認してください。', 'error');
            }
        });
    };

    /**
     * URL validation
     */
    FLP_Admin.validateURL = function($input) {
        var url = $input.val();
        var isValid = true;
        
        if (url && !url.match(/^https?:\/\/.+/)) {
            isValid = false;
        }

        this.toggleFieldError($input, !isValid, 'URLの形式が正しくありません');
        return isValid;
    };

    /**
     * Numeric validation
     */
    FLP_Admin.validateNumeric = function($input) {
        var value = $input.val();
        var isValid = value === '' || (!isNaN(value) && parseFloat(value) >= 0);

        this.toggleFieldError($input, !isValid, '0以上の数値を入力してください');
        return isValid;
    };

    /**
     * フィールドのエラー表示切り替え
     */
    FLP_Admin.toggleFieldError = function($input, hasError, message) {
        var $wrapper = $input.closest('.form-table, .flp-form-group, p');
        
        if (hasError) {
            $input.addClass('flp-field-error').css('border-color', '#d63638');
            
            if (!$wrapper.find('.flp-field-error-message').length) {
                $wrapper.append(`<div class="flp-field-error-message" style="color: #d63638; font-size: 12px; margin-top: 5px;">${message}</div>`);
            }
        } else {
            $input.removeClass('flp-field-error').css('border-color', '');
            $wrapper.find('.flp-field-error-message').remove();
        }
    };

    /**
     * フォーム全体のバリデーション
     */
    FLP_Admin.validateForm = function() {
        var isValid = true;
        
        // 必須フィールドのチェック
        $('input[required]').each(function() {
            if (!$(this).val().trim()) {
                FLP_Admin.toggleFieldError($(this), true, 'この項目は必須です');
                isValid = false;
            }
        });

        // URL フィールドのチェック
        $('input[name="flp_lp_data[button_url]"]').each(function() {
            if (!FLP_Admin.validateURL($(this))) {
                isValid = false;
            }
        });

        // 数値フィールドのチェック
        $('input[name^="flp_lp_data[btn_padding"], input[name*="border_radius"]').each(function() {
            if (!FLP_Admin.validateNumeric($(this))) {
                isValid = false;
            }
        });

        return isValid;
    };

    /**
     * オートセーブ機能の初期化
     */
    FLP_Admin.initAutosave = function() {
        if (!flp_admin.settings.autosave_enabled) {
            return;
        }

        $(document).on('input change', 'input, select, textarea', function() {
            FLP_Admin.triggerAutosave();
        });
    };

    /**
     * オートセーブのトリガー
     */
    FLP_Admin.triggerAutosave = function() {
        if (this.autosaveTimer) {
            clearTimeout(this.autosaveTimer);
        }

        this.autosaveTimer = setTimeout(function() {
            FLP_Admin.performAutosave();
        }, flp_admin.settings.autosave_interval || 30000);
    };

    /**
     * オートセーブの実行
     */
    FLP_Admin.performAutosave = function() {
        if (this.isLoading) {
            return;
        }

        var $form = $('#post');
        if ($form.length === 0) {
            return;
        }

        var formData = $form.serialize();
        
        this.isLoading = true;
        this.showNotification('自動保存中...', 'info', 2000);

        $.post(flp_admin.ajax_url, {
            action: 'flp_autosave',
            nonce: flp_admin.nonce,
            data: formData
        })
        .done(function(response) {
            if (response.success) {
                FLP_Admin.showNotification('自動保存完了', 'success', 1000);
            }
        })
        .fail(function() {
            console.warn('Autosave failed');
        })
        .always(function() {
            FLP_Admin.isLoading = false;
        });
    };

    /**
     * ツールチップの初期化
     */
    FLP_Admin.initTooltips = function() {
        $(document).on('mouseenter', '[data-tooltip]', function() {
            var $this = $(this);
            var tooltipText = $this.attr('data-tooltip');
            
            if (!tooltipText) return;

            var $tooltip = $('<div class="flp-tooltip-content">')
                .text(tooltipText)
                .appendTo('body');

            var offset = $this.offset();
            $tooltip.css({
                position: 'absolute',
                top: offset.top - $tooltip.outerHeight() - 10,
                left: offset.left + ($this.outerWidth() / 2) - ($tooltip.outerWidth() / 2),
                zIndex: 1000
            });
        });

        $(document).on('mouseleave', '[data-tooltip]', function() {
            $('.flp-tooltip-content').remove();
        });
    };

    /**
     * 確認ダイアログの初期化
     */
    FLP_Admin.initConfirmDialogs = function() {
        $(document).on('click', '[data-confirm]', function(e) {
            var message = $(this).attr('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    };

    /**
     * 通知メッセージの表示
     */
    FLP_Admin.showNotification = function(message, type, duration) {
        type = type || 'info';
        duration = duration || 5000;

        var $notification = $(`
            <div class="flp-notification flp-notification-${type}" style="
                position: fixed;
                top: 30px;
                right: 30px;
                background: white;
                padding: 15px 20px;
                border-radius: 4px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease;
            ">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="flp-notification-icon">
                        ${FLP_Admin.getNotificationIcon(type)}
                    </div>
                    <div class="flp-notification-message">${message}</div>
                </div>
            </div>
        `);

        $('body').append($notification);

        // アニメーション
        setTimeout(function() {
            $notification.css({
                opacity: 1,
                transform: 'translateX(0)'
            });
        }, 100);

        // 自動削除
        setTimeout(function() {
            $notification.css({
                opacity: 0,
                transform: 'translateX(100%)'
            });
            
            setTimeout(function() {
                $notification.remove();
            }, 300);
        }, duration);

        // クリックで削除
        $notification.on('click', function() {
            $(this).css({
                opacity: 0,
                transform: 'translateX(100%)'
            });
            
            setTimeout(function() {
                $notification.remove();
            }, 300);
        });
    };

    /**
     * 通知アイコンの取得
     */
    FLP_Admin.getNotificationIcon = function(type) {
        var icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };
        
        return icons[type] || icons.info;
    };

    /**
     * AJAX リクエストのヘルパー
     */
    FLP_Admin.ajaxRequest = function(action, data, options) {
        options = options || {};
        
        var requestData = {
            action: 'flp_' + action,
            nonce: flp_admin.nonce
        };

        if (typeof data === 'object') {
            $.extend(requestData, data);
        }

        return $.ajax({
            url: flp_admin.ajax_url,
            method: 'POST',
            data: requestData,
            beforeSend: function() {
                if (options.showLoading) {
                    FLP_Admin.showNotification(flp_admin.strings.saving, 'info');
                }
            }
        })
        .done(function(response) {
            if (options.showSuccess && response.success) {
                FLP_Admin.showNotification(response.data?.message || flp_admin.strings.saved, 'success');
            } else if (!response.success) {
                FLP_Admin.showNotification(response.data?.message || flp_admin.strings.error, 'error');
            }
        })
        .fail(function() {
            FLP_Admin.showNotification(flp_admin.strings.error, 'error');
        });
    };

    /**
     * プレビュー機能
     */
    FLP_Admin.openPreview = function() {
        var postId = $('#post_ID').val();
        if (!postId) {
            FLP_Admin.showNotification('まず投稿を保存してください', 'warning');
            return;
        }

        var previewWindow = window.open('', 'flp_preview', 'width=400,height=600,scrollbars=yes');
        previewWindow.document.write('<div style="padding: 20px; text-align: center;">プレビューを読み込み中...</div>');

        FLP_Admin.ajaxRequest('preview_lp', {
            lp_id: postId,
            components: JSON.stringify([]) // 今後のビジュアルビルダー用
        })
        .done(function(response) {
            if (response.success) {
                previewWindow.document.open();
                previewWindow.document.write(response.data.html);
                previewWindow.document.close();
            } else {
                previewWindow.document.write('<div style="padding: 20px; color: red;">プレビューの生成に失敗しました</div>');
            }
        });
    };

    // グローバルアクセス用
    window.FLP_Admin = FLP_Admin;

    // 画像の遅延読み込み
    FLP_Admin.initLazyLoading = function() {
        var imageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        $('.flp-image-items img[data-src]').each(function() {
            imageObserver.observe(this);
        });
    };

})(jQuery);

/**
 * ページ離脱前の確認
 */
window.addEventListener('beforeunload', function(e) {
    if (jQuery('input, select, textarea').filter('.changed').length > 0) {
        e.preventDefault();
        e.returnValue = '';
        return '';
    }
});

/**
 * フォーム変更の追跡
 */
jQuery(document).ready(function($) {
    var originalFormData = $('form').serialize();
    
    $('input, select, textarea').on('change', function() {
        $(this).addClass('changed');
    });
    
    $('form').on('submit', function() {
        $('input, select, textarea').removeClass('changed');
    });
});