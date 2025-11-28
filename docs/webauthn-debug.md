# WebAuthn パスキー取得の確認手順 (Google Chrome)

ブラウザから `/webauthn/login` に送られるデータを確認し、`navigator.credentials.get` が正しく値を返しているかを調べる手順です。

## 前提
- Chrome を使用。
- ログイン画面に「パスキーでログイン」ボタンがあること。

## 手順概要
1. **DevTools を開く**: `F12` または `Ctrl+Shift+I`（Mac は `Cmd+Opt+I`）。
2. **`webpass.js` を開く**: `Sources` タブ → `public/webpass.js`。
3. **ブレークポイントを設定**: `login` 関数内の `navigator.credentials.get({publicKey});` 行（約 360 行台）。
4. **パスキー認証を開始**: 画面でユーザー名を入力し「パスキーでログイン」をクリック。
5. **変数を確認**:
   1. ブレークポイントで一時停止した直後は `await` の前なので `credentials` が「値がありません」と表示されます。`F10`（Step over）で 1 行進めて `navigator.credentials.get` が解決した後に再度 `credentials` を展開してください。
   2. それでも空の場合は `Console` タブで `credentials` を直接評価するか、`Step into` で Promise が解決するまで 1～2 ステップ進めてください。
   3. `credentials` を展開し、以下が入っているか確認します。
   - `id` / `rawId`
   - `response.authenticatorData`
   - `response.clientDataJSON`
   - `response.signature`
6. **送信ペイロードを確認**: ブレークポイントを解除→続行後、`Network` タブで `/webauthn/login` リクエストの `Payload` を開き、上記の値が JSON で送られているか確認。

## 補足: 行位置の目安
- `navigator.credentials.get` は `public/webpass.js` の `login` 関数終盤（`try` ブロック内）にあります。
- 該当部分周辺にコメント `// Get a passkey credential.` があるので目印にしてください。

## 期待する結果
- `credentials` に `id` や `response.signature` が入っている → ブラウザ側では取得成功。
- `Network` のリクエストペイロードにも同じ値がある → サーバーには完全なアサーションが送れている。
- どちらも空/`null` の場合は端末にパスキーが無い、またはユーザー操作でキャンセルされた可能性があります。

## `credentials` が埋まっているのに 422 などで失敗する場合のチェックポイント
1. **Network タブのレスポンスを確認**: `/webauthn/login` のレスポンス本文にエラー理由が含まれていないか確認します（`message` フィールドなど）。
2. **サーバーログを照合**: 422 などで拒否された時刻のサーバーログに、`username` やアサーションの概要が出ていないかを確認します。クライアントで表示された `id`/`rawId` がサーバー側のログの値と一致しているか見比べてください。
3. **登録済みのクレデンシャルと一致するか確認**: 登録時に保存したクレデンシャル ID（`id` または `rawId`）と、ログイン時に送信される ID が一致しているかを比較します。別アカウントや別端末のパスキーを使うと検証が失敗します。
4. **オリジンや RP ID の不一致を疑う**: テスト環境の URL が変わった場合、登録時と異なるオリジン/RP ID で検証するとサーバー側で失敗します。登録とログインで同じドメイン・ポート・プロトコルを使っているか確認してください。
5. **チャレンジの有効期限**: ログイン開始から時間が経ちすぎるとチャレンジが無効になる実装もあります。再度「パスキーでログイン」を押して新しいチャレンジで試します。

## クライアントが正常に動作していると判断できる根拠
ユーザーが Chrome の DevTools で `credentials` に `id` / `rawId` / `response.signature` などが入っているのを確認できた場合、以下のブラウザ側処理は完了しています。

- `navigator.credentials.get()` の実行とパスキー検索
- 署名の生成と `AuthenticatorAssertionResponse` の取得
- `rawId` / `signature` / `clientDataJSON` などの値を JSON 化して送信する処理

この状態で 422 などのエラーが返るときは「クライアントは成功しているがサーバー側検証で拒否されている」状況です。

## サーバー側で優先的に検証すべきポイント
ブラウザ送信データに問題がない場合、サーバー側の検証ロジックを順番に確認します。

1. **challenge が一致しているか**
   - 認証開始時にサーバーが発行した challenge と、`clientDataJSON` 内の challenge（Base64Url デコード後）を比較します。
   - Laravel では Base64Url ↔ Base64 変換の実装ミスが多いので、符号あり/なしやパディングの扱いを確認してください。
2. **署名検証で生のバイト列を使っているか**
   - `authenticatorData` と `clientDataHash` を結合した「生の bytes」を公開鍵で検証できているかを確認します。JSON 文字列や Base64 文字列のまま検証すると失敗します。
3. **`authenticatorData` 内の `rpIdHash` が一致するか**
   - サーバー側で計算した RP ID ハッシュと、`authenticatorData` に含まれる `rpIdHash` を比較します。ドメインやポートが変わっていないか確認してください。
4. **公開鍵の取り出しが正しいか**
   - 登録時に保存した COSEKey から公開鍵を正しく復元できているか確認します。アルゴリズム（例: ES256）やキー長の解釈ミスで検証に失敗することがあります。
5. **WebAuthn ライブラリの呼び出し方に誤りがないか**
   - `web-auth/webauthn-lib` などを使う場合、`AuthenticatorAssertionResponseValidator` に正しい入力（オリジン、RP ID、チャレンジ、登録済みクレデンシャル情報）を渡しているか確認します。

これらを上から順に確認すると、クライアントは正常だがサーバー側で検証が落ちる典型的な原因を洗い出せます。サーバーログにチャレンジや `rpIdHash`、使用している公開鍵を追記すると調査が容易になります。
