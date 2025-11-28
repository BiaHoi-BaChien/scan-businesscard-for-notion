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
   - サーバー側で計算した RP ID ハッシュ（`hash('sha256', config('webauthn.relying_party.id'))`）と、`authenticatorData` に含まれる `rpIdHash` を比較します。ドメインやポートが変わっていないか確認してください。
   - ログでは RP ID（例: `clb-biahoi.net`）とオリジン（例: `https://clb-biahoi.net`）は値が異なるように見えますが、仕様上問題ありません。RP ID はホスト名のみ、オリジンはスキーム＋ホスト（＋ポート）で、RP ID ハッシュが一致していれば正しいオリジン/RP ID で検証されています。
4. **公開鍵の取り出しが正しいか**
   - 登録時に保存した COSEKey から公開鍵を正しく復元できているか確認します。アルゴリズム（例: ES256）やキー長の解釈ミスで検証に失敗することがあります。
5. **WebAuthn ライブラリの呼び出し方に誤りがないか**
   - `web-auth/webauthn-lib` などを使う場合、`AuthenticatorAssertionResponseValidator` に正しい入力（オリジン、RP ID、チャレンジ、登録済みクレデンシャル情報）を渡しているか確認します。

これらを上から順に確認すると、クライアントは正常だがサーバー側で検証が落ちる典型的な原因を洗い出せます。サーバーログにチャレンジや `rpIdHash`、使用している公開鍵を追記すると調査が容易になります。

## ログが空配列（`client_data: []` や `authenticator_data: []`）になる場合
デバッグログに `client_data: []` や `authenticator_data: []` と出ている場合、サーバーが受け取ったリクエストにアサーション本体（`assertion.response.clientDataJSON` / `authenticatorData` など）が入っていない可能性があります。まず以下を確認してください。

- `request_payload.assertion_present` が `false` になっていないか。
- `request_payload.raw_body_length` が 0 になっていないか（フロントからデータが届いていない）。
- `expected.session_challenge` が `null` の場合、セッションが欠落しているか、チャレンジ保存前にセッションが切れている可能性があります。`session_has_challenge` が `false` のときは、セッションストアや Cookie の送出状況を確認してください。
- 422 応答が返る前に、ブラウザの Network タブで `/webauthn/login` リクエストの `Request Payload` に `assertion.response` フィールドが入っているかを確認します。ここで空ならフロント側送信が欠落、入っているのにサーバーで空ならミドルウェアやボディパーサーで落ちている可能性があります。

ログに `rp_id` と `origin` が並んで表示されますが、RP ID はホスト名のみ、オリジンはスキーム＋ホスト（＋ポート）であるため、値が異なるように見えても仕様上問題ありません。RP ID ハッシュの一致可否（`rp_id_hash_matches_expected`）が `true` かどうかを基準に確認してください。

## 今回のログ例から読み取れること
該当ログでは以下が同時に発生しており、サーバーが検証を開始できない状態です。

- `session_challenge` が `null`、`session_has_challenge` が `false` → 認証開始時に発行したチャレンジがセッションに存在しない。セッション Cookie が送信されていない、またはチャレンジ保存前にセッションが破棄された可能性があります。
- `assertion_present` が `false`、`client_data` / `authenticator_data` が空配列 → ブラウザからアサーション本体（`clientDataJSON` や `authenticatorData`）が届いていない。`navigator.credentials.get()` が呼ばれていない、もしくは署名データを含まないリクエストが送信されている可能性があります。

### 対応の優先順位（具体的な確認手順）
1. **チャレンジ発行 API 呼び出し後の Cookie を確認する**
   1. DevTools `Network` タブでチャレンジ発行 API（例: `/webauthn/login/options` など認証開始エンドポイント）を選択。
   2. `Headers` → `Response Headers` から `Set-Cookie` を確認し、セッション Cookie が返却されているかを見る。
   3. その Cookie に `Secure` と `SameSite=None`（クロスサイトなら必須）が付いているか、`Domain`/`Path` がオリジンに一致しているかをチェック。
2. **実際の認証リクエストで Cookie が送られているか確認する**
   1. 同じく `Network` タブで `/webauthn/login` を選択。
   2. `Headers` → `Request Headers` の `Cookie` 行を開き、1 で確認したセッション Cookie が含まれているか確認する。
   3. ここにセッション Cookie が無ければ、ブラウザ設定（iOS Safari のトラッキング防止やプライベートモード）、HTTPS 設定、またはドメイン属性の誤りを疑う。
3. **`navigator.credentials.get()` でチャレンジを渡しているか検証する**
   1. 画面で「パスキーでログイン」を押す直前に DevTools `Sources` で `public/webpass.js` を開き、`navigator.credentials.get({ publicKey })` の行にブレークポイントを置く。
   2. ブレーク後、`publicKey.challenge` を展開して値が入っているかを確認（Base64Url 文字列で入っていれば OK）。
   3. `Network` タブで `/webauthn/login` リクエストの `Payload` を開き、`response.clientDataJSON` / `authenticatorData` / `signature` が送られているかを確認する。ここが空の場合、`navigator.credentials.get()` が呼ばれていないか、返却値を送信していない可能性が高い。
