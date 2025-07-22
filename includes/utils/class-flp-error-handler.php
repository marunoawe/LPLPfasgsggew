<?php
/**
 * エラーページテンプレート（修正版）
 * templates/error-page.php
 */

// 変数が定義されているか確認し、未定義の場合はデフォルト値を設定
if (!isset($is_admin)) {
    $is_admin = is_admin();
}

if (!isset($can_see_details)) {
    $can_see_details = current_user_can('manage_options');
}

if (!isset($error_handler)) {
    // エラーハンドラーのインスタンスを取得
    if (class_exists('FLP_Error_Handler')) {
        $error_handler = FLP_Error_Handler::instance();
    } else {
        $error_handler = null;
    }
}

// エラー配列が未定義の場合はデフォルト値を設定
if (!isset($error)) {
    $error = array(
        'message' => __('予期しないエラーが発生しました。', 'finelive-lp'),
        'code' => null,
        'file' => null,
        'line' => null,
        'type' => null,
        'trace' => null,
    );
}
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .error-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
            padding: 50px 40px;
            text-align: center;
        }
        
        .error-icon {
            font-size: 72px;
            margin-bottom: 20px;
            line-height: 1;
            opacity: 0.8;
        }
        
        h1 {
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
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
        
        .dashicons {
            line-height: 1;
            font-size: 20px;
            width: 20px;
            height: 20px;
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
    <?php if (function_exists('wp_head')): ?>
        <link rel="stylesheet" href="<?php echo includes_url('css/dashicons.min.css'); ?>">
    <?php endif; ?>
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
        
        <?php if ($can_see_details && $error_handler && method_exists($error_handler, 'is_debug_mode') && $error_handler->is_debug_mode()): ?>
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
    
    <?php if ($error_handler && method_exists($error_handler, 'is_debug_mode') && $error_handler->is_debug_mode()): ?>
    <script>
        console.error('FineLive LP Error:', <?php echo json_encode($error); ?>);
    </script>
    <?php endif; ?>
</body>
</html>