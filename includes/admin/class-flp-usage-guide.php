<?php
/**
 * 使い方ガイド管理クラス
 */

if (!defined('ABSPATH')) exit;

class FLP_Usage_Guide {

    /**
     * ページの描画
     */
    public function render_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('FineLive Multi LP Display 使い方ガイド', 'finelive-lp'); ?></h1>
            
            <div class="flp-usage-content" style="max-width: 1000px;">
                <?php $this->render_introduction(); ?>
                <?php $this->render_basic_usage(); ?>
                <?php $this->render_settings_guide(); ?>
                <?php $this->render_shortcode_guide(); ?>
                <?php $this->render_advanced_features(); ?>
                <?php $this->render_troubleshooting(); ?>
                <?php $this->render_faq(); ?>
            </div>
        </div>

        <style>
        .flp-usage-content h2 {
            background: #f1f1f1;
            padding: 15px 20px;
            margin: 30px 0 15px 0;
            border-left: 4px solid #0073aa;
        }
        .flp-usage-content h3 {
            color: #0073aa;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .flp-step-box {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .flp-highlight-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        .flp-code-block {
            background: #f8f8f8;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 10px;
            font-family: monospace;
            font-size: 13px;
            margin: 10px 0;
        }
        .flp-screenshot {
            max-width: 100%;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 10px 0;
        }
        </style>
        <?php
    }

    /**
     * はじめに
     */
    private function render_introduction() {
        ?>
        <div class="flp-step-box">
            <p style="font-size: 16px; line-height: 1.6;">
                <strong><?php _e('FineLive Multi LP Display', 'finelive-lp'); ?></strong> <?php _e('をご利用いただき、ありがとうございます！', 'finelive-lp'); ?>
            </p>
            <p>
                <?php _e('このプラグインは、ショートコードを使ってサイト内の好きな場所にLP（ランディングページ）を表示するためのツールです。', 'finelive-lp'); ?>
                <?php _e('複数の画像を縦に配置し、ボタンやスライダーを組み合わせた魅力的なLPを簡単に作成できます。', 'finelive-lp'); ?>
            </p>
            
            <h4><?php _e('主な機能', 'finelive-lp'); ?></h4>
            <ul>
                <li><?php _e('静的画像の縦表示（複数画像対応）', 'finelive-lp'); ?></li>
                <li><?php _e('自動切替スライダー機能', 'finelive-lp'); ?></li>
                <li><?php _e('カスタマイズ可能なボタンデザイン', 'finelive-lp'); ?></li>
                <li><?php _e('表示期間の設定', 'finelive-lp'); ?></li>
                <li><?php _e('クリック測定・レポート機能', 'finelive-lp'); ?></li>
                <li><?php _e('LP複製機能', 'finelive-lp'); ?></li>
            </ul>
        </div>
        <?php
    }

    /**
     * 基本的な使い方
     */
    private function render_basic_usage() {
        ?>
        <h2><?php _e('基本的な使い方', 'finelive-lp'); ?></h2>

        <div class="flp-step-box">
            <h3><?php _e('ステップ 1: LPの作成', 'finelive-lp'); ?></h3>
            <ol>
                <li><?php _e('管理画面の左メニューから「LP管理」→「新しいLPを追加」をクリック', 'finelive-lp'); ?></li>
                <li><?php _e('LPのタイトルを入力（例：「サマーセールLP」）', 'finelive-lp'); ?></li>
                <li><?php _e('「LP設定」セクションで各種設定を行う', 'finelive-lp'); ?></li>
                <li><?php _e('「公開」または「下書きとして保存」ボタンで保存', 'finelive-lp'); ?></li>
            </ol>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('ステップ 2: 画像の設定', 'finelive-lp'); ?></h3>
            <p><?php _e('「静的画像設定」セクションで、LPに表示する画像を追加します：', 'finelive-lp'); ?></p>
            <ul>
                <li><?php _e('「＋画像を追加」ボタンをクリック', 'finelive-lp'); ?></li>
                <li><?php _e('「画像選択」ボタンでメディアライブラリから画像を選択', 'finelive-lp'); ?></li>
                <li><?php _e('各画像ごとに「ボタン表示」「スライダー表示」のオプションを設定', 'finelive-lp'); ?></li>
                <li><?php _e('必要に応じて複数の画像を追加', 'finelive-lp'); ?></li>
            </ul>
            
            <div class="flp-highlight-box">
                <strong><?php _e('ポイント:', 'finelive-lp'); ?></strong>
                <?php _e('画像は設定した順番で縦に表示されます。並び順を変更したい場合は、一度削除して再度追加してください。', 'finelive-lp'); ?>
            </div>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('ステップ 3: ボタンの設定', 'finelive-lp'); ?></h3>
            <p><?php _e('「共通設定」セクションでボタンの内容を設定します：', 'finelive-lp'); ?></p>
            <ul>
                <li><strong><?php _e('ボタンテキスト:', 'finelive-lp'); ?></strong> <?php _e('ボタンに表示される文言（例：「今すぐ申し込む」）', 'finelive-lp'); ?></li>
                <li><strong><?php _e('ボタンURL:', 'finelive-lp'); ?></strong> <?php _e('ボタンをクリックした時のリンク先', 'finelive-lp'); ?></li>
            </ul>
            
            <p><?php _e('「ボタンデザイン設定」では見た目をカスタマイズできます：', 'finelive-lp'); ?></p>
            <ul>
                <li><?php _e('背景色・文字色の変更', 'finelive-lp'); ?></li>
                <li><?php _e('ボタンサイズの調整', 'finelive-lp'); ?></li>
                <li><?php _e('角の丸み設定', 'finelive-lp'); ?></li>
            </ul>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('ステップ 4: ショートコードの設置', 'finelive-lp'); ?></h3>
            <p><?php _e('作成したLPを表示したい場所にショートコードを貼り付けます：', 'finelive-lp'); ?></p>
            
            <ol>
                <li><?php _e('LP一覧画面でLPのIDを確認', 'finelive-lp'); ?></li>
                <li><?php _e('投稿や固定ページの編集画面を開く', 'finelive-lp'); ?></li>
                <li><?php _e('以下のショートコードを貼り付け:', 'finelive-lp'); ?></li>
            </ol>
            
            <div class="flp-code-block">
                [finelive_lp id="<strong>123</strong>"]
            </div>
            
            <p><small><?php _e('※ 123の部分を実際のLP IDに置き換えてください', 'finelive-lp'); ?></small></p>
        </div>
        <?php
    }

    /**
     * 設定項目の詳細説明
     */
    private function render_settings_guide() {
        ?>
        <h2><?php _e('設定項目の詳細説明', 'finelive-lp'); ?></h2>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 180px;"><?php _e('設定項目', 'finelive-lp'); ?></th>
                    <th><?php _e('説明', 'finelive-lp'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php _e('ボタンテキスト', 'finelive-lp'); ?></strong></td>
                    <td><?php _e('LPに表示されるボタンの文言です。例：「詳しくはこちら」「お申し込み」「資料請求」など', 'finelive-lp'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('ボタンURL', 'finelive-lp'); ?></strong></td>
                    <td><?php _e('ボタンをクリックした時のリンク先URLです。申し込みフォームや商品ページなどを設定してください。', 'finelive-lp'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('ボタンデザイン設定', 'finelive-lp'); ?></strong></td>
                    <td><?php _e('ボタンの見た目をカスタマイズできます。背景色、文字色、大きさ、角の丸みを自由に調整可能です。', 'finelive-lp'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('スライダー設定', 'finelive-lp'); ?></strong></td>
                    <td><?php _e('自動で切り替わるスライドショーの画像を登録します。切り替え速度も調整可能です。（1000ミリ秒 = 1秒）', 'finelive-lp'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('LP表示期間設定', 'finelive-lp'); ?></strong></td>
                    <td><?php _e('このLPを表示する期間を指定できます。キャンペーン期間限定LPなどに便利です。空欄にすると期間制限なく表示されます。', 'finelive-lp'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('静的画像設定', 'finelive-lp'); ?></strong></td>
                    <td><?php _e('LPの本体となる画像を登録します。複数枚の画像を縦に並べて表示できます。各画像の下に「ボタン」や「スライダー」を表示するかを個別に設定可能です。', 'finelive-lp'); ?></td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * ショートコードの使い方
     */
    private function render_shortcode_guide() {
        ?>
        <h2><?php _e('ショートコードの使い方', 'finelive-lp'); ?></h2>

        <div class="flp-step-box">
            <h3><?php _e('基本的なショートコード', 'finelive-lp'); ?></h3>
            <div class="flp-code-block">
                [finelive_lp id="123"]
            </div>
            <p><?php _e('123の部分に表示したいLPのIDを入力してください。LP IDはLP一覧画面で確認できます。', 'finelive-lp'); ?></p>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('短縮形のショートコード', 'finelive-lp'); ?></h3>
            <div class="flp-code-block">
                [flp id="123"]
            </div>
            <p><?php _e('より短い形式でも記述できます。機能は同じです。', 'finelive-lp'); ?></p>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('ショートコードの設置場所', 'finelive-lp'); ?></h3>
            <ul>
                <li><strong><?php _e('投稿・固定ページ:', 'finelive-lp'); ?></strong> <?php _e('本文エリアに直接貼り付け', 'finelive-lp'); ?></li>
                <li><strong><?php _e('ウィジェット:', 'finelive-lp'); ?></strong> <?php _e('テキストウィジェットに貼り付け', 'finelive-lp'); ?></li>
                <li><strong><?php _e('テーマファイル:', 'finelive-lp'); ?></strong> <?php echo '<?php echo do_shortcode(\'[finelive_lp id="123"]\'); ?>'; ?></li>
            </ul>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('LP IDの確認方法', 'finelive-lp'); ?></h3>
            <ol>
                <li><?php _e('管理画面の「LP管理」→「LP一覧」を開く', 'finelive-lp'); ?></li>
                <li><?php _e('一覧の「ID」列でLPのIDを確認', 'finelive-lp'); ?></li>
                <li><?php _e('または「ショートコード」列に表示されているコードをそのまま使用', 'finelive-lp'); ?></li>
            </ol>
        </div>
        <?php
    }

    /**
     * 高度な機能
     */
    private function render_advanced_features() {
        ?>
        <h2><?php _e('高度な機能', 'finelive-lp'); ?></h2>

        <div class="flp-step-box">
            <h3><?php _e('LP複製機能', 'finelive-lp'); ?></h3>
            <p><?php _e('既存のLPをベースに新しいLPを作成できます：', 'finelive-lp'); ?></p>
            <ol>
                <li><?php _e('LP一覧画面で複製したいLPの「複製」リンクをクリック', 'finelive-lp'); ?></li>
                <li><?php _e('「〜のコピー」という名前で新しいLPが作成される', 'finelive-lp'); ?></li>
                <li><?php _e('設定を変更して異なるバリエーションのLPを作成', 'finelive-lp'); ?></li>
            </ol>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('クリック測定機能', 'finelive-lp'); ?></h3>
            <p><?php _e('ボタンのクリック数を自動で測定・記録します：', 'finelive-lp'); ?></p>
            <ul>
                <li><?php _e('LP一覧の「ボタンクリック数」列で確認', 'finelive-lp'); ?></li>
                <li><?php _e('「LPクリック測定」メニューで詳細レポートを表示', 'finelive-lp'); ?></li>
                <li><?php _e('日別、LP別、ボタン別の統計が閲覧可能', 'finelive-lp'); ?></li>
            </ul>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('表示期間の活用', 'finelive-lp'); ?></h3>
            <p><?php _e('期間限定キャンペーンなどに便利な機能です：', 'finelive-lp'); ?></p>
            <ul>
                <li><strong><?php _e('開始日設定:', 'finelive-lp'); ?></strong> <?php _e('指定日になるまで非表示', 'finelive-lp'); ?></li>
                <li><strong><?php _e('終了日設定:', 'finelive-lp'); ?></strong> <?php _e('指定日を過ぎると自動で非表示', 'finelive-lp'); ?></li>
                <li><strong><?php _e('期間なし:', 'finelive-lp'); ?></strong> <?php _e('両方とも空欄にすると常時表示', 'finelive-lp'); ?></li>
            </ul>
            
            <div class="flp-highlight-box">
                <strong><?php _e('管理者向け機能:', 'finelive-lp'); ?></strong>
                <?php _e('管理者には表示期間外のLPもプレビューとして表示されます。', 'finelive-lp'); ?>
            </div>
        </div>
        <?php
    }

    /**
     * トラブルシューティング
     */
    private function render_troubleshooting() {
        ?>
        <h2><?php _e('トラブルシューティング', 'finelive-lp'); ?></h2>

        <div class="flp-step-box">
            <h3><?php _e('LPが表示されない場合', 'finelive-lp'); ?></h3>
            <ol>
                <li><strong><?php _e('LP IDの確認:', 'finelive-lp'); ?></strong> <?php _e('ショートコードのIDが正しいか確認', 'finelive-lp'); ?></li>
                <li><strong><?php _e('投稿ステータス:', 'finelive-lp'); ?></strong> <?php _e('LPが「公開」状態になっているか確認', 'finelive-lp'); ?></li>
                <li><strong><?php _e('表示期間:', 'finelive-lp'); ?></strong> <?php _e('現在日が表示期間内かどうか確認', 'finelive-lp'); ?></li>
                <li><strong><?php _e('静的画像:', 'finelive-lp'); ?></strong> <?php _e('最低1枚の静的画像が設定されているか確認', 'finelive-lp'); ?></li>
            </ol>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('管理者にはエラーメッセージが表示される', 'finelive-lp'); ?></h3>
            <p><?php _e('管理者でログインしている場合、LPに問題があるとエラーメッセージが表示されます：', 'finelive-lp'); ?></p>
            <ul>
                <li><?php _e('LP IDが見つからない', 'finelive-lp'); ?></li>
                <li><?php _e('表示期間外', 'finelive-lp'); ?></li>
                <li><?php _e('静的画像が未設定', 'finelive-lp'); ?></li>
            </ul>
            <p><?php _e('一般訪問者にはエラーメッセージは表示されず、何も表示されません。', 'finelive-lp'); ?></p>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('ボタンクリックが記録されない場合', 'finelive-lp'); ?></h3>
            <ol>
                <li><?php _e('ボタンURLが正しく設定されているか確認', 'finelive-lp'); ?></li>
                <li><?php _e('JavaScriptエラーがないか確認（ブラウザのデベロッパーツール）', 'finelive-lp'); ?></li>
                <li><?php _e('他のプラグインとの競合がないか確認', 'finelive-lp'); ?></li>
                <li><?php _e('キャッシュプラグインを使用している場合は設定を確認', 'finelive-lp'); ?></li>
            </ol>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('スライダーが動作しない場合', 'finelive-lp'); ?></h3>
            <ul>
                <li><?php _e('スライダー画像が2枚以上設定されているか確認', 'finelive-lp'); ?></li>
                <li><?php _e('静的画像で「スライダー表示」にチェックが入っているか確認', 'finelive-lp'); ?></li>
                <li><?php _e('画像のサイズが極端に大きくないか確認', 'finelive-lp'); ?></li>
            </ul>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('パフォーマンス問題の解決', 'finelive-lp'); ?></h3>
            <ul>
                <li><?php _e('「設定」→「ツール」でキャッシュクリアを実行', 'finelive-lp'); ?></li>
                <li><?php _e('古いクリックデータの削除を実行', 'finelive-lp'); ?></li>
                <li><?php _e('使用する画像サイズを最適化', 'finelive-lp'); ?></li>
                <li><?php _e('必要のない古いLPを削除', 'finelive-lp'); ?></li>
            </ul>
        </div>
        <?php
    }

    /**
     * よくある質問
     */
    private function render_faq() {
        ?>
        <h2><?php _e('よくある質問（FAQ）', 'finelive-lp'); ?></h2>

        <div class="flp-step-box">
            <h3><?php _e('Q: 1つのページに複数のLPを表示できますか？', 'finelive-lp'); ?></h3>
            <p><strong><?php _e('A:', 'finelive-lp'); ?></strong> <?php _e('はい、可能です。異なるIDのショートコードを複数配置することで、1つのページに複数のLPを表示できます。', 'finelive-lp'); ?></p>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('Q: 画像の推奨サイズはありますか？', 'finelive-lp'); ?></h3>
            <p><strong><?php _e('A:', 'finelive-lp'); ?></strong> <?php _e('特に制限はありませんが、表示速度を考慮して幅800px程度、ファイルサイズ200KB以下を推奨します。縦横比は自由です。', 'finelive-lp'); ?></p>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('Q: スマートフォンでも正しく表示されますか？', 'finelive-lp'); ?></h3>
            <p><strong><?php _e('A:', 'finelive-lp'); ?></strong> <?php _e('はい、レスポンシブデザインに対応しており、スマートフォンやタブレットでも適切に表示されます。', 'finelive-lp'); ?></p>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('Q: ボタンの色を変更できますか？', 'finelive-lp'); ?></h3>
            <p><strong><?php _e('A:', 'finelive-lp'); ?></strong> <?php _e('はい、「ボタンデザイン設定」で背景色・文字色を自由に変更できます。リアルタイムプレビューで確認しながら調整可能です。', 'finelive-lp'); ?></p>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('Q: クリック数はどのくらい正確ですか？', 'finelive-lp'); ?></h3>
            <p><strong><?php _e('A:', 'finelive-lp'); ?></strong> <?php _e('JavaScriptによる計測のため、JavaScriptが無効な環境やボットからのアクセスは計測されません。一般的なアクセス解析と同程度の精度です。', 'finelive-lp'); ?></p>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('Q: スライダーの画像枚数に制限はありますか？', 'finelive-lp'); ?></h3>
            <p><strong><?php _e('A:', 'finelive-lp'); ?></strong> <?php _e('技術的な制限はありませんが、表示速度を考慮して5〜10枚程度を推奨します。', 'finelive-lp'); ?></p>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('Q: 他のプラグインと競合することはありますか？', 'finelive-lp'); ?></h3>
            <p><strong><?php _e('A:', 'finelive-lp'); ?></strong> <?php _e('独自の名前空間を使用しているため、競合の可能性は低いです。ただし、同じjQueryライブラリを使用するプラグインとは稀に競合する場合があります。', 'finelive-lp'); ?></p>
        </div>

        <div class="flp-step-box">
            <h3><?php _e('Q: バックアップは必要ですか？', 'finelive-lp'); ?></h3>
            <p><strong><?php _e('A:', 'finelive-lp'); ?></strong> <?php _e('LPのデータはWordPressのデータベースに保存されるため、通常のWordPressバックアップに含まれます。定期的なバックアップを推奨します。', 'finelive-lp'); ?></p>
        </div>

        <div class="flp-highlight-box">
            <h4><?php _e('さらにサポートが必要な場合', 'finelive-lp'); ?></h4>
            <p><?php _e('このガイドで解決しない問題がございましたら、以下の情報をお教えください：', 'finelive-lp'); ?></p>
            <ul>
                <li><?php _e('WordPressバージョン', 'finelive-lp'); ?></li>
                <li><?php _e('プラグインバージョン', 'finelive-lp'); ?></li>
                <li><?php _e('使用中のテーマ', 'finelive-lp'); ?></li>
                <li><?php _e('具体的な症状・エラーメッセージ', 'finelive-lp'); ?></li>
            </ul>
        </div>
        <?php
    }
}
