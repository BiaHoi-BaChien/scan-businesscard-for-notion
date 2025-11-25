<x-layouts.app>
    <article style="max-width: 480px; margin: auto;">
        <hgroup>
            <h1>ログイン</h1>
            <p class="muted">パスワードまたはパスキーで認証</p>
        </hgroup>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <label>ユーザー名
                <input type="text" name="username" value="{{ old('username') }}" required>
            </label>
            <label>パスワード
                <input type="password" name="password" placeholder="パスワードを入力">
            </label>
            <label>パスキー
                <input type="text" name="passkey" placeholder="パスキーでログイン">
            </label>
            <button type="submit">ログイン</button>
        </form>
    </article>
</x-layouts.app>
