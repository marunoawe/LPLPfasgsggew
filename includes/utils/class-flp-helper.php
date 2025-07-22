<?php
/**
 * ヘルパー関数クラス
 */

if (!defined('ABSPATH')) exit;

class FLP_Helper {

    /**
     * 日付文字列をフォーマット
     *
     * @param string $date 日付文字列
     * @param string $format フォーマット
     * @return string フォーマット済み日付
     */
    public static function format_date($date, $format = null) {
        if (empty($date)) {
            return '';
        }

        if ($format === null) {
            $format = get_option('date_format');
        }

        $timestamp = is_numeric($date) ? $date : strtotime($date);
        
        if ($timestamp === false) {
            return $date; // 変換できない場合は元の値を返す
        }

        return date_i18n($format, $timestamp);
    }

    /**
     * 相対時間の表示
     *
     * @param string|int $date 日付
     * @return string 相対時間
     */
    public static function time_ago($date) {
        $timestamp = is_numeric($date) ? $date : strtotime($date);
        
        if ($timestamp === false) {
            return '';
        }

        $time_diff = current_time('timestamp') - $timestamp;

        if ($time_diff < MINUTE_IN_SECONDS) {
            return __('今', 'finelive-lp');
        } elseif ($time_diff < HOUR_IN_SECONDS) {
            $minutes = floor($time_diff / MINUTE_IN_SECONDS);
            return sprintf(_n('%d分前', '%d分前', $minutes, 'finelive-lp'), $minutes);
        } elseif ($time_diff < DAY_IN_SECONDS) {
            $hours = floor($time_diff / HOUR_IN_SECONDS);
            return sprintf(_n('%d時間前', '%d時間前', $hours, 'finelive-lp'), $hours);
        } elseif ($time_diff < WEEK_IN_SECONDS) {
            $days = floor($time_diff / DAY_IN_SECONDS);
            return sprintf(_n('%d日前', '%d日前', $days, 'finelive-lp'), $days);
        } else {
            return self::format_date($timestamp);
        }
    }

    /**
     * 数値をフォーマット
     *
     * @param int|float $number 数値
     * @param int $decimals 小数点桁数
     * @return string フォーマット済み数値
     */
    public static function format_number($number, $decimals = 0) {
        return number_format_i18n($number, $decimals);
    }

    /**
     * ファイルサイズを人間が読みやすい形式に変換
     *
     * @param int $bytes バイト数
     * @return string 読みやすいファイルサイズ
     */
    public static function format_bytes($bytes) {
        return size_format($bytes);
    }

    /**
     * カラーコードの検証とサニタイズ
     *
     * @param string $color カラーコード
     * @param string $default デフォルトカラー
     * @return string サニタイズされたカラーコード
     */
    public static function sanitize_color($color, $default = '#ffffff') {
        if (empty($color)) {
            return $default;
        }

        // HEXカラーコードの検証
        if (preg_match('/^#([a-f0-9]{3}){1,2}$/i', $color)) {
            return strtolower($color);
        }

        // RGB形式の場合
        if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/', $color, $matches)) {
            $r = min(255, max(0, intval($matches[1])));
            $g = min(255, max(0, intval($matches[2])));
            $b = min(255, max(0, intval($matches[3])));
            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }

        return $default;
    }

    /**
     * URLの検証とサニタイズ
     *
     * @param string $url URL
     * @param string $default デフォルトURL
     * @return string サニタイズされたURL
     */
    public static function sanitize_url($url, $default = '') {
        if (empty($url)) {
            return $default;
        }

        $url = esc_url_raw($url);
        
        if (empty($url)) {
            return $default;
        }

        return $url;
    }

    /**
     * 配列から特定のキーの値を安全に取得
     *
     * @param array $array 配列
     * @param string $key キー
     * @param mixed $default デフォルト値
     * @return mixed
     */
    public static function array_get($array, $key, $default = null) {
        if (!is_array($array)) {
            return $default;
        }

        return isset($array[$key]) ? $array[$key] : $default;
    }

    /**
     * ネストした配列から値を取得
     *
     * @param array $array 配列
     * @param string $path ドット記法のパス (例: 'user.profile.name')
     * @param mixed $default デフォルト値
     * @return mixed
     */
    public static function array_get_nested($array, $path, $default = null) {
        if (!is_array($array)) {
            return $default;
        }

        $keys = explode('.', $path);
        $current = $array;

        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return $default;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * 配列をフラット化
     *
     * @param array $array 多次元配列
     * @param string $prefix プレフィックス
     * @return array フラット化された配列
     */
    public static function array_flatten($array, $prefix = '') {
        $result = array();

        foreach ($array as $key => $value) {
            $new_key = $prefix === '' ? $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $result = array_merge($result, self::array_flatten($value, $new_key));
            } else {
                $result[$new_key] = $value;
            }
        }

        return $result;
    }

    /**
     * HTML属性文字列を生成
     *
     * @param array $attributes 属性の配列
     * @return string HTML属性文字列
     */
    public static function build_attributes($attributes) {
        $attr_strings = array();

        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            $key = sanitize_key($key);

            if ($value === true) {
                $attr_strings[] = $key;
            } else {
                $attr_strings[] = $key . '="' . esc_attr($value) . '"';
            }
        }

        return implode(' ', $attr_strings);
    }

    /**
     * CSSスタイル文字列を生成
     *
     * @param array $styles スタイルの配列
     * @return string CSSスタイル文字列
     */
    public static function build_styles($styles) {
        $style_strings = array();

        foreach ($styles as $property => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $property = sanitize_key(str_replace('_', '-', $property));
            $style_strings[] = $property . ': ' . esc_attr($value);
        }

        return implode('; ', $style_strings);
    }

    /**
     * JSONレスポンスを送信（AJAX用）
     *
     * @param bool $success 成功フラグ
     * @param mixed $data データ
     * @param string $message メッセージ
     */
    public static function json_response($success, $data = null, $message = '') {
        $response = array(
            'success' => $success,
            'message' => $message,
        );

        if ($data !== null) {
            $response['data'] = $data;
        }

        wp_send_json($response);
    }

    /**
     * ランダム文字列の生成
     *
     * @param int $length 長さ
     * @param string $chars 使用する文字
     * @return string ランダム文字列
     */
    public static function generate_random_string($length = 10, $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789') {
        $chars_length = strlen($chars);
        $random_string = '';

        for ($i = 0; $i < $length; $i++) {
            $random_string .= $chars[rand(0, $chars_length - 1)];
        }

        return $random_string;
    }

    /**
     * 一意なIDの生成
     *
     * @param string $prefix プレフィックス
     * @return string 一意なID
     */
    public static function generate_unique_id($prefix = 'flp') {
        return $prefix . '_' . uniqid() . '_' . rand(1000, 9999);
    }

    /**
     * 画像URLから情報を取得
     *
     * @param string $image_url 画像URL
     * @return array|false 画像情報
     */
    public static function get_image_info($image_url) {
        if (empty($image_url)) {
            return false;
        }

        // WordPress添付ファイルIDを取得
        $attachment_id = attachment_url_to_postid($image_url);
        
        if ($attachment_id) {
            $metadata = wp_get_attachment_metadata($attachment_id);
            
            return array(
                'id' => $attachment_id,
                'url' => $image_url,
                'width' => $metadata['width'] ?? 0,
                'height' => $metadata['height'] ?? 0,
                'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
                'title' => get_the_title($attachment_id),
                'caption' => wp_get_attachment_caption($attachment_id),
            );
        }

        // 外部画像の場合
        $image_size = @getimagesize($image_url);
        
        if ($image_size) {
            return array(
                'id' => 0,
                'url' => $image_url,
                'width' => $image_size[0],
                'height' => $image_size[1],
                'alt' => '',
                'title' => basename($image_url),
                'caption' => '',
            );
        }

        return false;
    }

    /**
     * レスポンシブ画像のsrcsetを生成
     *
     * @param string $image_url 画像URL
     * @param array $sizes サイズの配列
     * @return string srcset文字列
     */
    public static function generate_srcset($image_url, $sizes = array()) {
        $attachment_id = attachment_url_to_postid($image_url);
        
        if (!$attachment_id) {
            return '';
        }

        return wp_get_attachment_image_srcset($attachment_id, $sizes);
    }

    /**
     * デバイスの判定
     *
     * @return string デバイスタイプ (mobile, tablet, desktop)
     */
    public static function get_device_type() {
        if (wp_is_mobile()) {
            // より詳細な判定が必要な場合
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            if (preg_match('/tablet|ipad/i', $user_agent)) {
                return 'tablet';
            }
            
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * ユーザーエージェント情報の取得
     *
     * @return array ユーザーエージェント情報
     */
    public static function get_user_agent_info() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $info = array(
            'raw' => $user_agent,
            'is_mobile' => wp_is_mobile(),
            'device_type' => self::get_device_type(),
            'browser' => 'unknown',
            'os' => 'unknown',
        );

        // ブラウザの判定
        if (strpos($user_agent, 'Chrome') !== false) {
            $info['browser'] = 'Chrome';
        } elseif (strpos($user_agent, 'Firefox') !== false) {
            $info['browser'] = 'Firefox';
        } elseif (strpos($user_agent, 'Safari') !== false) {
            $info['browser'] = 'Safari';
        } elseif (strpos($user_agent, 'Edge') !== false) {
            $info['browser'] = 'Edge';
        } elseif (strpos($user_agent, 'Opera') !== false) {
            $info['browser'] = 'Opera';
        }

        // OSの判定
        if (strpos($user_agent, 'Windows') !== false) {
            $info['os'] = 'Windows';
        } elseif (strpos($user_agent, 'Macintosh') !== false) {
            $info['os'] = 'macOS';
        } elseif (strpos($user_agent, 'Linux') !== false) {
            $info['os'] = 'Linux';
        } elseif (strpos($user_agent, 'iPhone') !== false) {
            $info['os'] = 'iOS';
        } elseif (strpos($user_agent, 'Android') !== false) {
            $info['os'] = 'Android';
        }

        return $info;
    }

    /**
     * IPアドレスの取得（プロキシ対応）
     *
     * @return string IPアドレス
     */
    public static function get_client_ip() {
        $ip_keys = array(
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        );

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * リファラー情報の取得とパース
     *
     * @return array リファラー情報
     */
    public static function get_referer_info() {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        if (empty($referer)) {
            return array(
                'url' => '',
                'domain' => '',
                'is_search' => false,
                'search_engine' => '',
                'search_query' => '',
            );
        }

        $parsed = parse_url($referer);
        $domain = $parsed['host'] ?? '';
        
        $info = array(
            'url' => $referer,
            'domain' => $domain,
            'is_search' => false,
            'search_engine' => '',
            'search_query' => '',
        );

        // 検索エンジンの判定
        $search_engines = array(
            'google' => array('google.com', 'google.co.jp'),
            'yahoo' => array('yahoo.com', 'yahoo.co.jp'),
            'bing' => array('bing.com'),
            'duckduckgo' => array('duckduckgo.com'),
        );

        foreach ($search_engines as $engine => $domains) {
            foreach ($domains as $search_domain) {
                if (strpos($domain, $search_domain) !== false) {
                    $info['is_search'] = true;
                    $info['search_engine'] = $engine;
                    
                    // 検索クエリの取得
                    if (isset($parsed['query'])) {
                        parse_str($parsed['query'], $query_params);
                        $query_keys = array('q', 'query', 'search', 'p');
                        
                        foreach ($query_keys as $key) {
                            if (!empty($query_params[$key])) {
                                $info['search_query'] = $query_params[$key];
                                break;
                            }
                        }
                    }
                    break 2;
                }
            }
        }

        return $info;
    }

    /**
     * パフォーマンス測定用のマイクロタイム取得
     *
     * @return float マイクロタイム
     */
    public static function get_microtime() {
        return microtime(true);
    }

    /**
     * パフォーマンス測定の計算
     *
     * @param float $start_time 開始時刻
     * @param int $precision 精度（小数点桁数）
     * @return string 実行時間
     */
    public static function calculate_execution_time($start_time, $precision = 4) {
        $end_time = self::get_microtime();
        $execution_time = $end_time - $start_time;
        
        return number_format($execution_time, $precision) . '秒';
    }

    /**
     * メモリ使用量の取得
     *
     * @param bool $real_usage 実メモリ使用量フラグ
     * @return string メモリ使用量
     */
    public static function get_memory_usage($real_usage = false) {
        return size_format(memory_get_usage($real_usage));
    }

    /**
     * ピークメモリ使用量の取得
     *
     * @param bool $real_usage 実メモリ使用量フラグ
     * @return string ピークメモリ使用量
     */
    public static function get_peak_memory_usage($real_usage = false) {
        return size_format(memory_get_peak_usage($real_usage));
    }

    /**
     * デバッグ情報の出力
     *
     * @param mixed $data デバッグするデータ
     * @param string $label ラベル
     * @param bool $die 処理を停止するか
     */
    public static function debug($data, $label = 'Debug', $die = false) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        echo '<pre style="background: #000; color: #0f0; padding: 10px; margin: 10px; font-family: monospace; font-size: 12px; white-space: pre-wrap;">';
        echo '<strong>' . esc_html($label) . ':</strong>' . "\n";
        
        if (is_array($data) || is_object($data)) {
            print_r($data);
        } else {
            var_dump($data);
        }
        
        echo '</pre>';

        if ($die) {
            die();
        }
    }

    /**
     * ログファイルへの書き込み
     *
     * @param mixed $message ログメッセージ
     * @param string $level ログレベル
     * @param string $context コンテキスト
     */
    public static function log($message, $level = 'info', $context = 'flp') {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }

        $log_entry = sprintf(
            '[%s] [%s] [%s] %s',
            current_time('Y-m-d H:i:s'),
            strtoupper($level),
            $context,
            $message
        );

        error_log($log_entry);
    }
}
