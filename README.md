# JGrants Integration Pro - WordPress補助金情報管理プラグイン

JグランツAPIと連携し、AIによる自動コンテンツ生成機能を備えたWordPress用補助金情報管理プラグインです。

## 主な機能

### 1. JグランツAPI連携コア
- JグランツAPIから補助金情報を自動取得
- WordPressのカスタム投稿タイプ「grant」に自動投稿
- 定期的な自動同期機能（6時間/12時間/24時間間隔）
- 手動同期機能
- 同期履歴の記録と管理

### 2. AIによるコンテンツ生成機能

#### AIタイトル生成
- 補助金名、実施機関、最大支援額、対象、締切などの情報から魅力的なSEO最適化タイトルを自動生成
- 管理画面でプロンプトのカスタマイズが可能

#### AI抜粋生成
- 補助金情報から重要なポイントを簡潔にまとめた抜粋文を自動生成
- 150文字以内の簡潔な要約

#### AIコンテンツ強化
- 申請を検討している事業者に役立つ詳細な解説記事を自動生成
- 構造化されたコンテンツ（概要、対象者・条件、支援内容、申請のポイント、注意事項、まとめ）

#### AIカテゴリ自動分類
- 補助金の内容をAIが分析し、最適なカテゴリを自動判定
- 12種類のデフォルトカテゴリ（IT・デジタル化、設備投資、研究開発など）

#### AI都道府県自動抽出
- 補助金情報から対象となる都道府県をAIが正確に特定
- 47都道府県＋全国対応

### 3. カスタムタクソノミー
- **補助金カテゴリー**: IT・デジタル化、設備投資、研究開発など
- **対象地域**: 都道府県別、地域別
- **対象事業者**: 中小企業、個人事業主、スタートアップなど
- **支援額範囲**: 〜100万円、100〜500万円など

### 4. 管理機能
- 直感的な管理画面
- API接続テスト機能
- 同期状態のリアルタイム表示
- AIプロンプトのカスタマイズ
- ダッシュボードウィジェット

## システム要件

- WordPress 6.0以上
- PHP 8.0以上
- MySQL 8.0以上
- OpenAI APIキー（AI機能使用時）
- JグランツAPIキー

## インストール方法

### 1. Docker環境のセットアップ

```bash
# リポジトリをクローン
git clone https://github.com/yourusername/jgrants-integration.git
cd jgrants-integration

# Docker Composeで環境を起動
docker-compose up -d

# WordPressの初期設定
# ブラウザで http://localhost:8080 にアクセス
```

### 2. プラグインの有効化

1. WordPressの管理画面にログイン
2. プラグイン → インストール済みプラグイン
3. 「JGrants Integration Pro」を有効化

### 3. 初期設定

1. **API設定**
   - 管理画面 → JGrants連携 → 設定
   - JグランツAPIキーを入力
   - API接続テストを実行

2. **AI設定**
   - 管理画面 → JGrants連携 → AI設定
   - OpenAI APIキーを入力
   - 使用モデルを選択（GPT-4推奨）

3. **同期設定**
   - 管理画面 → JGrants連携 → 同期管理
   - 自動同期の有効化
   - 同期間隔の設定

## 使用方法

### 手動同期
1. 管理画面 → JGrants連携 → 同期管理
2. 「今すぐ同期」ボタンをクリック

### AIコンテンツ再生成
1. 補助金の編集画面を開く
2. 右サイドバーの「AI生成設定」メタボックス
3. 「今すぐAIコンテンツを生成」をクリック

### プロンプトのカスタマイズ
1. 管理画面 → JGrants連携 → AI設定
2. プロンプト設定セクション
3. 各プロンプトをカスタマイズして保存

## ファイル構造

```
plugins/jgrants-integration/
├── jgrants-integration.php      # メインプラグインファイル
├── includes/
│   ├── class-jgrants-core.php   # コアクラス
│   ├── class-jgrants-api-client.php  # API通信クラス
│   ├── class-jgrants-ai-generator.php  # AI生成クラス
│   ├── class-jgrants-post-type.php  # カスタム投稿タイプ
│   ├── class-jgrants-taxonomies.php  # カスタムタクソノミー
│   ├── class-jgrants-sync-manager.php  # 同期管理
│   └── class-jgrants-cron.php   # Cronジョブ管理
├── admin/
│   └── class-jgrants-admin.php  # 管理画面
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
└── languages/               # 翻訳ファイル
```

## API仕様

### REST APIエンドポイント

#### 同期実行
```
POST /wp-json/jgrants/v1/sync
```

#### 補助金一覧取得
```
GET /wp-json/jgrants/v1/grants
```

#### AIコンテンツ再生成
```
POST /wp-json/jgrants/v1/regenerate/{id}
```

## トラブルシューティング

### API接続エラー
- APIキーの確認
- ファイアウォール設定の確認
- SSL証明書の確認

### AI生成エラー
- OpenAI APIキーの確認
- API利用制限の確認
- モデルの可用性確認

### 同期エラー
- データベース接続の確認
- メモリ制限の確認（php.ini）
- タイムアウト設定の確認

## セキュリティ

- すべてのAPI通信はHTTPS経由
- APIキーは暗号化して保存
- nonce検証による CSRF対策
- 適切な権限チェック

## パフォーマンス最適化

- バッチ処理による大量データの効率的な処理
- 非同期処理による UI の応答性向上
- キャッシュ機能（開発中）

## ライセンス

GPL v2 or later

## サポート

- Issues: [GitHub Issues](https://github.com/yourusername/jgrants-integration/issues)
- Documentation: [Wiki](https://github.com/yourusername/jgrants-integration/wiki)

## 開発者向け情報

### フック

#### アクション
- `jgrants_after_sync` - 同期完了後
- `jgrants_before_sync` - 同期開始前
- `jgrants_after_ai_generation` - AI生成完了後

#### フィルター
- `jgrants_api_params` - API パラメータのカスタマイズ
- `jgrants_ai_prompt` - AI プロンプトのカスタマイズ
- `jgrants_grant_data` - 補助金データのフィルタリング

### 開発環境のセットアップ

```bash
# 依存関係のインストール
composer install
npm install

# 開発用ビルド
npm run dev

# 本番用ビルド
npm run build

# テストの実行
phpunit
```

## 更新履歴

### v1.0.0 (2024-01-20)
- 初回リリース
- JグランツAPI連携機能
- AI自動コンテンツ生成機能
- カスタム投稿タイプとタクソノミー
- 管理画面の実装

## 今後の予定

- [ ] 補助金の申請状況管理機能
- [ ] ユーザー向けの検索・フィルター機能
- [ ] メール通知機能
- [ ] 補助金マッチング機能
- [ ] レポート・分析機能
- [ ] 多言語対応

## 貢献

プルリクエストを歓迎します。大きな変更の場合は、まずissueを開いて変更内容を議論してください。

## 謝辞

このプラグインは以下の技術を使用しています：
- WordPress
- OpenAI GPT-4
- JグランツAPI
- Docker

---

© 2024 Your Company. All Rights Reserved.