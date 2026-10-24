# Party Finder UI：Tabler

更新：2026-10-25（Asia/Tokyo）。ユーザーが承認した「最初のTabler案＋アイコン」「フェーズ×募集枠を結果先頭」「注意書きの専用エリア」を開発アプリへ適用した。リリースは標準の `_ops/deploy.sh` を使う。

## 基盤と責務

Laravel 12／Blade／素のJavaScriptを維持。UI基盤は **Tabler 1.7.1**。Bootstrap別読み込み、jQuery、npm／Vite、React／Vue／Tailwindは追加しない。

| ファイル | 役割 |
|---|---|
| `public/vendor/tabler/1.7.1/` | 公式コンパイル済みCSS・JS、TablerとIconsのMITライセンス、取得元・バージョン・SHA-256のSOURCE.json |
| `resources/views/layouts/partyfinder.blade.php` | ダーク属性、標準navbar・pageレイアウト、Tabler CSS／JSの各1回読み込み、スキップリンク |
| `resources/views/partyfinder/index.blade.php` | 見出し、保存／除外モーダルの入口と件数、エラー／初期状態、クライアント設定JSON |
| `resources/views/components/partyfinder/` | Tablerのbutton・field・selectgroup・badgeを使うBladeラッパー。アイコンは公式Tabler IconsのSVGパスをインライン化 |
| `resources/views/partyfinder/partials/search-form.blade.php` | 既存GETフォーム。条件名・値・チェック項目を維持 |
| `partials/view-controls.blade.php` | 並び替え・表／カード・表示適用。外側でもform属性で検索フォームに関連付け |
| `partials/personal-tools.blade.php` | 保存条件／除外のTablerモーダル。既存JSのIDを維持 |
| `partials/results.blade.php` | 先頭の全体集計、件数・内訳・取得失敗、表／カード・CSV |
| `partials/phase-matrix.blade.php` | 既存の解析済みsummaryを表に表示。全体は初期展開、従来のコンテンツ別集計も各見出し下に保持 |
| `partials/recruitment-table.blade.php`・`recruitment-cards.blade.php` | 全既存項目・原文・ロール・外部日記リンク・除外の表示 |
| `partials/footer.blade.php` | 「ご利用にあたって」のカード。非公式・公式案内・権利・出典・キャッシュの文言を維持 |
| `public/css/partyfinder.css` | 日本語の最小文字サイズ、長い文字の折り返し、ドメイン固有の列幅・カード配置、フォーカス・検索中の補正 |
| `public/js/partyfinder.js` | 検索条件保存／復元・除外・Cookie・検索中状態。動的要素の外観クラスをTablerへ変更 |

`partials/`は `resources/views/partyfinder/partials/`。色・入力・カード・ボタン・余白・角丸・影はTablerが基準。独自のパレットや装飾を重ねない。追加画面も同じレイアウト・ラッパーを使う。

## 配信と更新

- `html[data-bs-theme="dark"]` をサーバー出力。テーマスクリプトは使用しない。
- CSSはTabler→最小補正、JSはTabler→既存の専用JS。別のBootstrapやPopperを追加しない。Tabler JSにはBootstrapが含まれる。
- 静的ファイルは自前配信。CDN・外部フォントへの実行時依存なし。すべて `asset()` でサブパスに対応。
- Tablerバージョン更新時は配置ディレクトリ・レイアウト参照・SOURCE.json・ライセンス・テストルーターの許可アセットを一緒に更新。
- Blade／CSS／JS／vendorを同じ配布物に含める。
- 公式資料：[Installation](https://docs.tabler.io/ui/getting-started/installation)、[Laravel](https://docs.tabler.io/ui/getting-started/frameworks/laravel)、[Color modes](https://docs.tabler.io/ui/getting-started/color-modes)、[Select group](https://docs.tabler.io/ui/forms/select-group)。

## 部品の使い方

```blade
<x-partyfinder.button variant="primary" type="submit" icon="search">検索する</x-partyfinder.button>
<x-partyfinder.field name="q" label="検索語" hint="条件の説明">
    <input class="form-control" id="q" name="q" type="text" aria-describedby="q-hint">
</x-partyfinder.field>
<x-partyfinder.choice-chip name="role_filter[]" value="D3" :checked="$selected" tone="dps">D3（レンジ）</x-partyfinder.choice-chip>
```

ボタンvariantはprimary／secondary／quiet。移動は`a.btn`、操作は`button`。入力はform-control／form-select。チェックボックスは送信時適用の意味を維持。選択チップは公式selectgroupで、隠したネイティブ入力にキーボードフォーカスが来たら可視ラベルへ輪郭を表示する。

タンク＝盾・青、ヒーラー＝ハート・緑、DPS＝剣・赤。文字ラベルを必ず併用し、装飾SVGはaria-hidden。16px未満の注釈・表見出しは使わない。募集外ロールは取り消し線と読み上げ文言を維持。フェーズの省略原文はdetailsで確認できる。

## 互換性と操作

- Controller／Service／routes／DB／API／composerは変更していない。
- GETキー、初期値、空欄、未選択、リセット、ページ範囲、CSV16列、保存・除外のキー／形式／上限を維持。
- 保存復元とCSVリンクの空文字保持は前回の修正を維持。
- 詳細設定は初期折りたたみ。言語・取得順・走査ページに非既定値がある場合や検証エラー時は開く。閉じても入力を無効化・削除しない。JavaScriptなしでも開閉・GET検索・CSVが使える。
- 保存・除外は上部のモーダルから操作。保存領域が使えなければ入口・除外操作を出さない既存挙動を維持。
- 除外後の再読み込み通知はモーダル内に加え、ページ上の状態メッセージにも出す。保存名・日記タイトル等はtextContentで描画。
- 表は横スクロール領域だけがはみ出す。カードは広い画面2列／狭い画面1列。モードは利用者の選択を保持。
- 検索開始のbusy・送信無効化、pageshowでの解除、未適用条件の案内、表示条件のCSVリンクを維持。
- 募集期間・更新日時・説明の構造化データは既存取得結果にない。推測せず投稿日時と取得済み解析を表示し、原文リンクで補う。

## 検証

2026-10-25実施：

- PHP：**122テスト、217 assertions合格**。既存検索・解析・本文判定・除外・CSVに加え、全体集計が表／カードの前にあり、合計が12件になることと注意書きエリアを確認。
- Laravel Pint：23ファイル合格。`git diff --check`も合格。
- Chromium：初期／表／カード／0件画面を1440／1024／768／390／320pxで検証。全体集計の初期展開・表示順、Tabler CSS／JS各1回、キーボード選択とフォーカス、詳細開閉、保存／空欄復元／旧形式／破損データ、表／カード、未適用表示、除外／個別解除／全解除／Cookie／CSV件数、リセット、検索中／pageshow、JavaScript無効時の検索が合格。コンソールエラーなし。
- 開発nginx `http://localhost:8010/ff14tools/partyfinder/` の初期表示・アセット・保存モーダルと背景1枚・コンソールを確認。外部への実検索は実施していない。
- 画像は `/tmp/partyfinder-tabler-applied/`。フィクスチャ検証は実際の利用者の保存領域を使わない。

再実行：

```bash
docker exec -u www-data -w /var/www/html/vel.works/ff14tools/partyfinder php8.4 php artisan test --compact
docker exec -u www-data -w /var/www/html/vel.works/ff14tools/partyfinder php8.4 vendor/bin/pint --test
# ターミナルA：検証後にCtrl+Cで停止
docker exec -u www-data -e APP_ENV=testing -w /var/www/html/vel.works/ff14tools/partyfinder php8.4 php -S 0.0.0.0:8099 tests/Browser/router.php
# ターミナルB：実際のコンテナIPを使用
PARTYFINDER_TEST_URL=http://<container-ip>:8099/ff14tools/partyfinder/ python3 tests/Browser/ui_smoke.py
```

ルーターはAPP_ENV=testing・PHP組み込みサーバーのみ動作。arrayキャッシュ／セッションとHTTPフェイクで外部検索・DB・実キャッシュflushを行わない。Python PlaywrightとChromeは開発用でアプリの実行依存ではない。

未確認：Safari／Firefox・実機iOS／Android・スクリーンリーダー、実際のBFCache格納、実データでの外部検索。pageshowハンドラーは検証済み。

## 引き継ぎ

このプロジェクトのみを変更した。前の未コミットUI差分と既存のAGENTS.mdを保持。適用前のファイルバックアップは `/tmp/partyfinder-before-tabler-2026-10-25/`。次の担当者は本書と実差分を読み、必要な実機検証・通常のデプロイ手順へ進む。配布時は自前配信のTablerファイルも必ず含める。
