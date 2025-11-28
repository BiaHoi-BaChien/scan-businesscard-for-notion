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
            <p class="muted" style="margin:0;">ブラウザや端末の生体認証でログインします。ユーザー名を入力してから実行してください。</p>
        </div>
    </article>
</x-layouts.app>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const button = document.getElementById('webauthn-login');
        const usernameInput = document.querySelector('input[name="username"]');
        if (!button) return;

        if (!window.webpassClient) {
            button.disabled = true;
            button.textContent = 'このブラウザはパスキー非対応';
            return;
        }

        button.addEventListener('click', async () => {
            button.disabled = true;
            const originalText = button.textContent;
            button.textContent = 'パスキーで認証中…';

            const request = {};
            const username = usernameInput?.value.trim();
            if (!username) {
                alert('パスキーでログインする前にユーザー名を入力してください。');
                button.textContent = originalText;
                button.disabled = false;
                usernameInput?.focus();
                return;
            }

            request.username = username;

            try {
                await window.webpassClient.login(request);
                window.location.href = "{{ route('dashboard') }}";
            } catch (e) {
                let errorMessage = 'パスキー認証に失敗しました。再度お試しください。';

                if (window.appDebug) {
                    if (e instanceof Response) {
                        try {
                            const cloned = e.clone();
                            const body = await cloned.text();
                            const json = JSON.parse(body);

                            if (typeof json?.message === 'string' && json.message.trim() !== '') {
                                errorMessage = json.message;
                            }

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
                alert(errorMessage);
            }
        });
    });
</script>
