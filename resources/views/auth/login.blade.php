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
                <input type="password" name="password" placeholder="パスワードを入力" required>
            </label>
            <button type="submit">パスワードでログイン</button>
        </form>
        <div class="grid" style="margin-top:1rem;">
            <button type="button" id="webauthn-login" class="secondary">パスキーでログイン</button>
            <p class="muted" style="margin:0;">ブラウザや端末の生体認証でログインします。</p>
        </div>
    </article>
</x-layouts.app>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const button = document.getElementById('webauthn-login');
        if (!button) return;

        if (!window.webpassClient) {
            button.disabled = true;
            button.textContent = 'このブラウザはパスキー非対応';
            return;
        }

        const usernameInput = document.querySelector('input[name="username"]');

        button.addEventListener('click', async () => {
            if (!usernameInput.value) {
                alert('ユーザー名を入力してください');
                return;
            }

            button.disabled = true;
            const originalText = button.textContent;
            button.textContent = 'パスキーで認証中…';

            try {
                await window.webpassClient.login({username: usernameInput.value});
                window.location.href = "{{ route('dashboard') }}";
            } catch (e) {
                if (window.appDebug) {
                    if (e instanceof Response) {
                        try {
                            const body = await e.clone().text();
                            console.error('Passkey login failed', {
                                status: e.status,
                                statusText: e.statusText,
                                body,
                            });
                        } catch (logError) {
                            console.error('Passkey login failed', e);
                            console.error('Failed to read error response body', logError);
                        }
                    } else {
                        console.error('Passkey login failed', e);
                    }
                }

                button.textContent = originalText;
                button.disabled = false;
                alert('パスキー認証に失敗しました。再度お試しください。');
            }
        });
    });
</script>
