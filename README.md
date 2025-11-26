# scan-businesscard-for-notion
名刺画像をOpenAI APIを利用して解析し、Notionに登録する

## 必要環境

- PHP (8.2 以上を推奨)
- Composer
- Node.js / npm (もしくは pnpm/yarn)
- SQLite3（デフォルト構成）

## セットアップ手順

1. リポジトリをクローンします。
2. `.env.example` を `.env` にコピーし、後述の環境変数を設定します。
3. 依存関係をインストールします。
   - バックエンド: `composer install`
   - フロントエンド: `npm install`
4. SQLite を利用する場合は、`.env` の `DB_DATABASE` で指定したパスに空のファイルを作成します（例: `touch database/database.sqlite`）。
5. マイグレーションとシーディングを実行して既定の管理者を作成します（`.env` の `DEFAULT_ADMIN_USERNAME` / `DEFAULT_ADMIN_PASSWORD` が使用されます）。
   - `php artisan migrate`
   - `php artisan db:seed`
6. 開発サーバーを起動します。
   - バックエンド API: `php artisan serve`
   - フロントエンド（Vite 等）: `npm run dev`

## 環境変数

`.env` には以下を設定してください。

| 変数名 | 説明 |
| --- | --- |
| `DB_DATABASE` | SQLite ファイルのパス。例: `database/database.sqlite` |
| `AUTH_SECRET` | 認証用の秘密鍵（JWT/Session 用）。 |
| `DEFAULT_ADMIN_USERNAME` | 既定管理者のユーザー名（シーダーが利用）。 |
| `DEFAULT_ADMIN_PASSWORD` | 既定管理者のパスワード（シーダーが利用）。 |
| `OPENAI_API_KEY` | OpenAI API キー。 |
| `NOTION_API_KEY` | Notion のインテグレーションシークレット。 |
| `NOTION_DATA_SOURCE_ID` | 登録先データベース ID。 |
| `NOTION_PROPERTY_MAPPING` | Notion のプロパティ対応表 JSON。例: `{ "name": {"name": "名前", "type": "title"} }` |
| `NOTION_VERSION` | Notion API バージョン（例: `2025-09-03`）。 |

## 認証と管理者

- ログインはパスワード認証に加え、環境に応じてパスキー（Passkey/WebAuthn）も利用できます。
- 既定の管理者はシーディング時に `.env` の `DEFAULT_ADMIN_USERNAME` / `DEFAULT_ADMIN_PASSWORD` で作成されます。
- 管理者はアプリ内のユーザー管理画面から追加・編集・無効化できます（権限のないユーザーは操作できません）。

## ファイルアップロードと処理フロー

- 対応形式: 一般的な画像（JPG/PNG）。フロントエンドはドラッグ＆ドロップでアップロードできます。
- アップロード上限: PHP の `upload_max_filesize` / `post_max_size` と、フロントエンドの選択枚数チェックに従います。大きい場合は圧縮またはリサイズしてください。
- レスポンシブ UI: 画面幅に応じてカード一覧やフォームが縦積み／横並びに切り替わります。モバイルではプレビューが折りたたまれ、重要情報を優先表示します。
- 処理フロー: **解析 → 確認 → Notion 登録** の順で進行します。解析結果を確認してから Notion へ登録してください。
- 取り消し・クリア: 進行中の処理は「キャンセル」で停止でき、フォーム内容やアップロード済みファイルは「クリア」でリセットできます。

## Notion への登録

- `NOTION_PROPERTY_MAPPING` の JSON により、解析結果のフィールド名と Notion プロパティを対応付けます。
- Notion API キーは、対象データベースに対して閲覧・編集権限を持つインテグレーションで発行してください。

## テスト・ビルド

- バックエンドの自動テスト: `php artisan test`
- マイグレーション検証: `php artisan migrate --pretend`
- フロントエンドのビルド: `npm run build`
- 必要に応じて型チェックやリンター（`npm run lint` など）を実行してください。
