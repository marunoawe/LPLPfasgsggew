<?php
/**
 * バリデーター（検証）クラス
 */

if (!defined('ABSPATH')) exit;

class FLP_Validator {

    /**
     * エラーメッセージ
     */
    private $errors = array();

    /**
     * 検証ルール
     */
    private $rules = array();

    /**
     * カスタムメッセージ
     */
    private $messages = array();

    /**
     * コンストラクタ
     *
     * @param array $rules 検証ルール
     * @param array $messages カスタムメッセージ
     */
    public function __construct($rules = array(), $messages = array()) {
        $this->rules = $rules;
        $this->messages = array_merge($this->get_default_messages(), $messages);
    }

    /**
     * データの検証実行
     *
     * @param array $data 検証するデータ
     * @return bool 検証結果
     */
    public function validate($data) {
        $this->errors = array();

        foreach ($this->rules as $field => $rule_string) {
            $rules = explode('|', $rule_string);
            $value = FLP_Helper::array_get($data, $field);

            foreach ($rules as $rule) {
                $this->apply_rule($field, $value, $rule, $data);
            }
        }

        return empty($this->errors);
    }

    /**
     * 単一ルールの適用
     *
     * @param string $field フィールド名
     * @param mixed $value 値
     * @param string $rule ルール
     * @param array $data 全データ
     */
    private function apply_rule($field, $value, $rule, $data) {
        $rule_parts = explode(':', $rule);
        $rule_name = $rule_parts[0];
        $rule_params = isset($rule_parts[1]) ? explode(',', $rule_parts[1]) : array();

        switch ($rule_name) {
            case 'required':
                if (!$this->validate_required($value)) {
                    $this->add_error($field, 'required');
                }
                break;

            case 'email':
                if (!empty($value) && !$this->validate_email($value)) {
                    $this->add_error($field, 'email');
                }
                break;

            case 'url':
                if (!empty($value) && !$this->validate_url($value)) {
                    $this->add_error($field, 'url');
                }
                break;

            case 'numeric':
                if (!empty($value) && !$this->validate_numeric($value)) {
                    $this->add_error($field, 'numeric');
                }
                break;

            case 'integer':
                if (!empty($value) && !$this->validate_integer($value)) {
                    $this->add_error($field, 'integer');
                }
                break;

            case 'min':
                $min_value = $rule_params[0] ?? 0;
                if (!empty($value) && !$this->validate_min($value, $min_value)) {
                    $this->add_error($field, 'min', array('min' => $min_value));
                }
                break;

            case 'max':
                $max_value = $rule_params[0] ?? PHP_INT_MAX;
                if (!empty($value) && !$this->validate_max($value, $max_value)) {
                    $this->add_error($field, 'max', array('max' => $max_value));
                }
                break;

            case 'min_length':
                $min_length = $rule_params[0] ?? 0;
                if (!empty($value) && !$this->validate_min_length($value, $min_length)) {
                    $this->add_error($field, 'min_length', array('min' => $min_length));
                }
                break;

            case 'max_length':
                $max_length = $rule_params[0] ?? PHP_INT_MAX;
                if (!empty($value) && !$this->validate_max_length($value, $max_length)) {
                    $this->add_error($field, 'max_length', array('max' => $max_length));
                }
                break;

            case 'in':
                if (!empty($value) && !$this->validate_in($value, $rule_params)) {
                    $this->add_error($field, 'in', array('values' => implode(', ', $rule_params)));
                }
                break;

            case 'color':
                if (!empty($value) && !$this->validate_color($value)) {
                    $this->add_error($field, 'color');
                }
                break;

            case 'date':
                if (!empty($value) && !$this->validate_date($value)) {
                    $this->add_error($field, 'date');
                }
                break;

            case 'image_url':
                if (!empty($value) && !$this->validate_image_url($value)) {
                    $this->add_error($field, 'image_url');
                }
                break;

            case 'array':
                if (!empty($value) && !$this->validate_array($value)) {
                    $this->add_error($field, 'array');
                }
                break;

            case 'confirmed':
                $confirmation_field = $field . '_confirmation';
                $confirmation_value = FLP_Helper::array_get($data, $confirmation_field);
                if (!$this->validate_confirmed($value, $confirmation_value)) {
                    $this->add_error($field, 'confirmed');
                }
                break;

            case 'regex':
                $pattern = $rule_params[0] ?? '';
                if (!empty($value) && !$this->validate_regex($value, $pattern)) {
                    $this->add_error($field, 'regex');
                }
                break;

            case 'json':
                if (!empty($value) && !$this->validate_json($value)) {
                    $this->add_error($field, 'json');
                }
                break;

            default:
                // カスタムバリデーションルール
                $custom_method = 'validate_' . $rule_name;
                if (method_exists($this, $custom_method)) {
                    if (!$this->$custom_method($value, $rule_params, $data)) {
                        $this->add_error($field, $rule_name);
                    }
                }
                break;
        }
    }

    /**
     * required バリデーション
     */
    private function validate_required($value) {
        if (is_null($value)) return false;
        if (is_string($value) && trim($value) === '') return false;
        if (is_array($value) && empty($value)) return false;
        return true;
    }

    /**
     * email バリデーション
     */
    private function validate_email($value) {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * url バリデーション
     */
    private function validate_url($value) {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * numeric バリデーション
     */
    private function validate_numeric($value) {
        return is_numeric($value);
    }

    /**
     * integer バリデーション
     */
    private function validate_integer($value) {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * min バリデーション（数値）
     */
    private function validate_min($value, $min) {
        return is_numeric($value) && floatval($value) >= floatval($min);
    }

    /**
     * max バリデーション（数値）
     */
    private function validate_max($value, $max) {
        return is_numeric($value) && floatval($value) <= floatval($max);
    }

    /**
     * min_length バリデーション（文字列長）
     */
    private function validate_min_length($value, $min) {
        return mb_strlen($value, 'UTF-8') >= intval($min);
    }

    /**
     * max_length バリデーション（文字列長）
     */
    private function validate_max_length($value, $max) {
        return mb_strlen($value, 'UTF-8') <= intval($max);
    }

    /**
     * in バリデーション（選択肢）
     */
    private function validate_in($value, $options) {
        return in_array($value, $options, true);
    }

    /**
     * color バリデーション（HEXカラーコード）
     */
    private function validate_color($value) {
        return preg_match('/^#([a-f0-9]{3}){1,2}$/i', $value);
    }

    /**
     * date バリデーション（Y-m-d形式）
     */
    private function validate_date($value) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        
        $timestamp = strtotime($value);
        return $timestamp !== false && date('Y-m-d', $timestamp) === $value;
    }

    /**
     * image_url バリデーション（画像URL）
     */
    private function validate_image_url($value) {
        if (!$this->validate_url($value)) {
            return false;
        }

        $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg');
        $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));
        
        return in_array($extension, $image_extensions);
    }

    /**
     * array バリデーション
     */
    private function validate_array($value) {
        return is_array($value);
    }

    /**
     * confirmed バリデーション（確認フィールド）
     */
    private function validate_confirmed($value, $confirmation) {
        return $value === $confirmation;
    }

    /**
     * regex バリデーション（正規表現）
     */
    private function validate_regex($value, $pattern) {
        return preg_match($pattern, $value);
    }

    /**
     * json バリデーション
     */
    private function validate_json($value) {
        if (!is_string($value)) {
            return false;
        }

        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * LP固有のバリデーション: ボタンテキスト
     */
    private function validate_button_text($value) {
        if (empty($value)) return false;
        if (mb_strlen($value, 'UTF-8') > 50) return false;
        
        // 不適切な文字列をチェック
        $forbidden_words = array('spam', 'scam', '詐欺');
        foreach ($forbidden_words as $word) {
            if (stripos($value, $word) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * LP固有のバリデーション: スライダー間隔
     */
    private function validate_slider_interval($value) {
        if (!is_numeric($value)) return false;
        
        $interval = intval($value);
        return $interval >= 1000 && $interval <= 30000; // 1秒から30秒まで
    }

    /**
     * LP固有のバリデーション: 表示期間
     */
    private function validate_display_period($value, $params, $data) {
        $start_date = FLP_Helper::array_get($data, 'display_start_date');
        $end_date = FLP_Helper::array_get($data, 'display_end_date');

        if (empty($start_date) || empty($end_date)) {
            return true; // どちらか空の場合は有効
        }

        return strtotime($start_date) <= strtotime($end_date);
    }

    /**
     * エラーの追加
     *
     * @param string $field フィールド名
     * @param string $rule ルール名
     * @param array $replacements 置換値
     */
    private function add_error($field, $rule, $replacements = array()) {
        $message = $this->get_error_message($field, $rule, $replacements);
        
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = array();
        }
        
        $this->errors[$field][] = $message;
    }

    /**
     * エラーメッセージの取得
     *
     * @param string $field フィールド名
     * @param string $rule ルール名
     * @param array $replacements 置換値
     * @return string エラーメッセージ
     */
    private function get_error_message($field, $rule, $replacements = array()) {
        $key = $field . '.' . $rule;
        
        if (isset($this->messages[$key])) {
            $message = $this->messages[$key];
        } elseif (isset($this->messages[$rule])) {
            $message = $this->messages[$rule];
        } else {
            $message = $this->messages['default'];
        }

        // 置換処理
        $replacements['field'] = $this->get_field_name($field);
        
        foreach ($replacements as $key => $value) {
            $message = str_replace(':' . $key, $value, $message);
        }

        return $message;
    }

    /**
     * フィールド名の取得（日本語名）
     *
     * @param string $field フィールド名
     * @return string 表示用フィールド名
     */
    private function get_field_name($field) {
        $field_names = array(
            'button_text' => 'ボタンテキスト',
            'button_url' => 'ボタンURL',
            'btn_bg_color' => 'ボタン背景色',
            'btn_text_color' => 'ボタン文字色',
            'btn_padding_tb' => 'ボタン縦パディング',
            'btn_padding_lr' => 'ボタン横パディング',
            'btn_border_radius' => 'ボタン角丸み',
            'slider_interval' => 'スライダー間隔',
            'display_start_date' => '表示開始日',
            'display_end_date' => '表示終了日',
            'static_images' => '静的画像',
            'slider_images' => 'スライダー画像',
        );

        return isset($field_names[$field]) ? $field_names[$field] : $field;
    }

    /**
     * デフォルトエラーメッセージ
     *
     * @return array デフォルトメッセージ
     */
    private function get_default_messages() {
        return array(
            'required' => ':fieldは必須です。',
            'email' => ':fieldは有効なメールアドレスを入力してください。',
            'url' => ':fieldは有効なURLを入力してください。',
            'numeric' => ':fieldは数値を入力してください。',
            'integer' => ':fieldは整数を入力してください。',
            'min' => ':fieldは:min以上の値を入力してください。',
            'max' => ':fieldは:max以下の値を入力してください。',
            'min_length' => ':fieldは:min文字以上で入力してください。',
            'max_length' => ':fieldは:max文字以下で入力してください。',
            'in' => ':fieldは次のうちいずれかを選択してください: :values',
            'color' => ':fieldは有効なカラーコード（#ffffff形式）を入力してください。',
            'date' => ':fieldは有効な日付（YYYY-MM-DD形式）を入力してください。',
            'image_url' => ':fieldは有効な画像URLを入力してください。',
            'array' => ':fieldは配列である必要があります。',
            'confirmed' => ':fieldと確認用フィールドが一致しません。',
            'regex' => ':fieldの形式が正しくありません。',
            'json' => ':fieldは有効なJSON形式で入力してください。',
            'button_text' => 'ボタンテキストは1-50文字で入力し、不適切な言葉を含まないようにしてください。',
            'slider_interval' => 'スライダー間隔は1000-30000ミリ秒（1-30秒）の間で設定してください。',
            'display_period' => '表示終了日は表示開始日以降の日付を設定してください。',
            'default' => ':fieldが無効です。',
        );
    }

    /**
     * エラーの取得
     *
     * @param string|null $field 特定のフィールドのエラー（nullで全て）
     * @return array|string エラー配列またはエラー文字列
     */
    public function errors($field = null) {
        if ($field === null) {
            return $this->errors;
        }

        return isset($this->errors[$field]) ? $this->errors[$field] : array();
    }

    /**
     * 最初のエラーメッセージを取得
     *
     * @param string|null $field フィールド名
     * @return string 最初のエラーメッセージ
     */
    public function first_error($field = null) {
        if ($field !== null) {
            $field_errors = $this->errors($field);
            return !empty($field_errors) ? $field_errors[0] : '';
        }

        foreach ($this->errors as $field_errors) {
            if (!empty($field_errors)) {
                return $field_errors[0];
            }
        }

        return '';
    }

    /**
     * エラーの存在チェック
     *
     * @param string|null $field フィールド名
     * @return bool エラーの有無
     */
    public function has_errors($field = null) {
        if ($field !== null) {
            return isset($this->errors[$field]) && !empty($this->errors[$field]);
        }

        return !empty($this->errors);
    }

    /**
     * 全エラーを文字列として取得
     *
     * @param string $separator 区切り文字
     * @return string エラー文字列
     */
    public function errors_as_string($separator = "\n") {
        $all_errors = array();

        foreach ($this->errors as $field_errors) {
            $all_errors = array_merge($all_errors, $field_errors);
        }

        return implode($separator, $all_errors);
    }

    /**
     * 静的メソッド: 単一値の簡単検証
     *
     * @param mixed $value 値
     * @param string $rules ルール文字列
     * @param array $messages カスタムメッセージ
     * @return bool|string 成功時true、失敗時エラーメッセージ
     */
    public static function quick_validate($value, $rules, $messages = array()) {
        $validator = new self(array('value' => $rules), $messages);
        $is_valid = $validator->validate(array('value' => $value));

        if ($is_valid) {
            return true;
        }

        return $validator->first_error('value');
    }

    /**
     * LP設定データの包括的バリデーション
     *
     * @param array $data LP設定データ
     * @return FLP_Validator バリデーターインスタンス
     */
    public static function validate_lp_data($data) {
        $rules = array(
            'button_text' => 'required|button_text',
            'button_url' => 'required|url',
            'btn_bg_color' => 'color',
            'btn_text_color' => 'color',
            'btn_padding_tb' => 'numeric|min:0|max:100',
            'btn_padding_lr' => 'numeric|min:0|max:200',
            'btn_border_radius' => 'numeric|min:0|max:50',
            'slider_interval' => 'slider_interval',
            'display_start_date' => 'date',
            'display_end_date' => 'date|display_period',
            'static_images' => 'required|array',
            'slider_images' => 'array',
        );

        $validator = new self($rules);
        $validator->validate($data);

        return $validator;
    }
}
