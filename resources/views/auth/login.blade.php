<x-layouts.app>
    <article style="max-width: 480px; margin: auto;">
        <hgroup>
            <h1>ログイン</h1>
        </hgroup>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <label>ユーザー名
                <input type="text" name="username" value="{{ old('username') }}" required>
            </label>
            <label>パスワード
                <input type="password" name="password" placeholder="パスワードを入力" required>
            </label>
            <button type="submit">パスワードでログイン</button>
        </form>
    </article>
</x-layouts.app>
