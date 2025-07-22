<?php
/**
 * FineLive Multi LP Display プラグインアンインストール処理
 * 
 * このファイルはプラグインが削除される際に実行されます。
 * プラグインに関連するデータベースのデータ、オプション、ファイルなどをクリーンアップします。
 */

// WordPressからの直接アクセスでない場合は終了
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

// セキュリティチェック
if (!current_user_can('activate_plugins')) {
    return;
}

// プラグインのパスを確認
if (__FILE__ != WP_UNINSTALL_PLUGIN) {
    return;
}

/**
 * カスタム投稿タイプ 'flp_lp' のデータ削除
 */
function flp_delete_all_lp_posts() {
    global $wpdb;
    
    // flp_lp 投稿タイプの全投稿を取得
    $lp_posts = get_posts(array(
        'post_type' => 'flp_lp',
        'post_status' => array('publish', 'draft', 'private', 'trash'),
        'posts_per_page' => -1,
        'fields' => 'ids'
    ));
    
    // 各投稿とそのメタデータを削除
    foreach ($lp_posts as $post_id) {
        // メタデータを削除
        $wpdb->delete(
            $wpdb->postmeta,
            array('post_id' => $post_id),
            array('%d')
        );
        
        // 投稿を削除
        wp_delete_post($post_id, true);
    }
    
    echo "Deleted " . count($lp_posts) . " LP posts and their metadata.\n";
}

/**
 * プラグイン関連のオプションを削除
 */
function flp_delete_options() {
    $options_to_delete = array(
        'flp_version',
        'flp_activated_time',
        'flp_settings',
        'flp_lp_click_data',
    );
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
        delete_site_option($option); // マルチサイト対応
    }
    
    // プレフィックスで始まるオプションを削除
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'flp_%'");
    
    if (is_multisite()) {
        $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE 'flp_%'");
    }
    
    echo "Deleted plugin options.\n";
}

/**
 * カスタム権限を削除
 */
function flp_remove_capabilities() {
    $roles = array('administrator', 'editor');
    
    $capabilities = array(
        'edit_flp_lp',
        'read_flp_lp',
        'delete_flp_lp',
        'edit_flp_lps',
        'edit_others_flp_lps',
        'delete_flp_lps',
        'publish_flp_lps',
        'read_private_flp_lps',
        'delete_private_flp_lps',
        'delete_published_flp_lps',
        'delete_others_flp_lps',
        'edit_private_flp_lps',
        'edit_published_flp_lps',
        'create_flp_lps',
    );

    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($capabilities as $capability) {
                $role->remove_cap($capability);
            }
        }
    }
    
    echo "Removed custom capabilities.\n";
}

/**
 * スケジュールされたタスクを削除
 */
function flp_clear_scheduled_tasks() {
    // クリーンアップタスクをクリア
    wp_clear_scheduled_hook('flp_cleanup_click_data');
    
    // 他のスケジュールされたタスクもクリア
    $scheduled_hooks = array(
        'flp_daily_cleanup',
        'flp_weekly_report',
        'flp_monthly_maintenance',
    );
    
    foreach ($scheduled_hooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }
    
    echo "Cleared scheduled tasks.\n";
}

/**
 * カスタムテーブルを削除（将来的に追加される可能性）
 */
function flp_drop_custom_tables() {
    global $wpdb;
    
    $custom_tables = array(
        $wpdb->prefix . 'flp_click_logs',
        $wpdb->prefix . 'flp_analytics',
        $wpdb->prefix . 'flp_ab_tests',
    );
    
    foreach ($custom_tables as $table_name) {
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
    
    echo "Dropped custom tables.\n";
}

/**
 * トランジェントデータを削除
 */
function flp_delete_transients() {
    global $wpdb;
    
    // プラグイン関連のトランジェントを削除
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_flp_%' 
         OR option_name LIKE '_transient_timeout_flp_%'"
    );
    
    if (is_multisite()) {
        $wpdb->query(
            "DELETE FROM {$wpdb->sitemeta} 
             WHERE meta_key LIKE '_site_transient_flp_%' 
             OR meta_key LIKE '_site_transient_timeout_flp_%'"
        );
    }
    
    echo "Deleted transient data.\n";
}

/**
 * ユーザーメタデータを削除
 */
function flp_delete_user_meta() {
    global $wpdb;
    
    // プラグイン関連のユーザーメタを削除
    $wpdb->query(
        "DELETE FROM {$wpdb->usermeta} 
         WHERE meta_key LIKE 'flp_%'"
    );
    
    echo "Deleted user metadata.\n";
}

/**
 * 一時ファイルとログファイルを削除
 */
function flp_delete_temp_files() {
    $upload_dir = wp_upload_dir();
    $flp_dir = $upload_dir['basedir'] . '/flp-temp/';
    
    if (is_dir($flp_dir)) {
        flp_delete_directory($flp_dir);
        echo "Deleted temporary files.\n";
    }
    
    // ログファイルも削除
    $log_files = glob(WP_CONTENT_DIR . '/debug-flp*.log');
    foreach ($log_files as $log_file) {
        if (is_file($log_file)) {
            unlink($log_file);
        }
    }
}

/**
 * ディレクトリを再帰的に削除
 */
function flp_delete_directory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($path)) {
            flp_delete_directory($path);
        } else {
            unlink($path);
        }
    }
    
    rmdir($dir);
}

/**
 * バックアップの作成（オプション）
 */
function flp_create_backup() {
    $backup_data = array(
        'version' => get_option('flp_version'),
        'settings' => get_option('flp_settings'),
        'click_data' => get_option('flp_lp_click_data'),
        'timestamp' => current_time('mysql'),
    );
    
    $upload_dir = wp_upload_dir();
    $backup_file = $upload_dir['basedir'] . '/flp-backup-' . date('Y-m-d-H-i-s') . '.json';
    
    file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));
    
    echo "Created backup file: " . basename($backup_file) . "\n";
}

/**
 * データベースの最適化
 */
function flp_optimize_database() {
    global $wpdb;
    
    // 関連テーブルを最適化
    $tables = array(
        $wpdb->posts,
        $wpdb->postmeta,
        $wpdb->options,
        $wpdb->usermeta,
    );
    
    foreach ($tables as $table) {
        $wpdb->query("OPTIMIZE TABLE {$table}");
    }
    
    echo "Optimized database tables.\n";
}

/**
 * アンインストール確認ログの記録
 */
function flp_log_uninstall() {
    $log_data = array(
        'plugin' => 'FineLive Multi LP Display',
        'version' => get_option('flp_version', 'unknown'),
        'uninstalled_at' => current_time('mysql'),
        'uninstalled_by' => get_current_user_id(),
        'site_url' => get_site_url(),
    );
    
    error_log('FLP Plugin Uninstalled: ' . json_encode($log_data));
}

/**
 * メイン処理の実行
 */
try {
    echo "Starting FineLive Multi LP Display plugin uninstallation...\n";
    
    // アンインストール確認ログ
    flp_log_uninstall();
    
    // バックアップ作成（オプション、管理者が設定した場合のみ）
    if (get_option('flp_create_backup_on_uninstall', false)) {
        flp_create_backup();
    }
    
    // データ削除処理
    flp_delete_all_lp_posts();
    flp_delete_options();
    flp_remove_capabilities();
    flp_clear_scheduled_tasks();
    flp_drop_custom_tables();
    flp_delete_transients();
    flp_delete_user_meta();
    flp_delete_temp_files();
    
    // データベース最適化
    flp_optimize_database();
    
    // リライトルールをフラッシュ
    flush_rewrite_rules();
    
    echo "FineLive Multi LP Display plugin uninstallation completed successfully.\n";
    
} catch (Exception $e) {
    error_log('FLP Uninstall Error: ' . $e->getMessage());
    echo "Error during uninstallation: " . $e->getMessage() . "\n";
}

/**
 * 最終クリーンアップ
 * 念のため、残存している可能性のある関連データを再度チェック
 */
function flp_final_cleanup() {
    global $wpdb;
    
    // 投稿タイプの残存チェック
    $remaining_posts = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'flp_lp'"
    );
    
    if ($remaining_posts > 0) {
        echo "Warning: {$remaining_posts} LP posts may still exist.\n";
    }
    
    // オプションの残存チェック
    $remaining_options = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'flp_%'"
    );
    
    if ($remaining_options > 0) {
        echo "Warning: {$remaining_options} plugin options may still exist.\n";
    }
}

// 最終クリーンアップの実行
flp_final_cleanup();

// アンインストール完了をマーク
update_option('flp_uninstalled', current_time('mysql'), false);
delete_option('flp_uninstalled'); // 即座に削除（ログ目的のみ）
