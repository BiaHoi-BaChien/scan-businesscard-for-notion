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

        <section class="stack" style="margin-top: 1.5rem;">
            <header>
                <h3 style="margin:0;">パスキーでログイン</h3>
                <p class="muted" style="margin: 0.2rem 0 0;">ブラウザに登録したパスキーで、ワンタップログインができます。</p>
            </header>
            <button type="button" class="secondary" data-passkey-login>パスキーを使用する</button>
            <small class="muted" id="passkey-login-message"></small>
        </section>
    </article>
</x-layouts.app>
<script>
    const passkeyLogin = (() => {
        const base64URLToBuffer = (value) => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0)).buffer;
        const bufferToBase64URL = (buffer) => btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

        const setMessage = (message, isError = false) => {
            const el = document.getElementById('passkey-login-message');
            if (!el) return;
            el.textContent = message || '';
            el.style.color = isError ? '#c00' : 'inherit';
        };

        const transformOptions = (options) => {
            if (!options?.challenge) return null;

            const publicKey = { ...options };
            publicKey.challenge = base64URLToBuffer(options.challenge);

            if (Array.isArray(options.allowCredentials)) {
                publicKey.allowCredentials = options.allowCredentials.map(item => ({
                    ...item,
                    id: base64URLToBuffer(item.id),
                }));
            }

            return publicKey;
        };

        const formatAssertion = (assertion) => ({
            id: assertion.id,
            type: assertion.type,
            rawId: bufferToBase64URL(assertion.rawId),
            response: {
                authenticatorData: bufferToBase64URL(assertion.response.authenticatorData),
                clientDataJSON: bufferToBase64URL(assertion.response.clientDataJSON),
                signature: bufferToBase64URL(assertion.response.signature),
                userHandle: assertion.response.userHandle ? bufferToBase64URL(assertion.response.userHandle) : null,
            },
            clientExtensionResults: assertion.getClientExtensionResults(),
        });

        const fetchJson = async (url, payload) => {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data?.message || 'リクエストに失敗しました。');
            }

            return data;
        };

        const login = async () => {
            if (!('credentials' in navigator) || !('PublicKeyCredential' in window)) {
                setMessage('パスキーに対応したブラウザでお試しください。', true);
                return;
            }

            const username = document.querySelector('input[name="username"]').value.trim();
            if (!username) {
                setMessage('ユーザー名を入力してください。', true);
                return;
            }

            setMessage('パスキー認証の準備中です...');

            try {
                const optionPayload = await fetchJson('{{ route('passkeys.options') }}', { username });
                const publicKey = transformOptions(optionPayload);

                if (!publicKey) {
                    throw new Error('認証情報の取得に失敗しました。');
                }

                const assertion = await navigator.credentials.get({ publicKey });

                const result = await fetchJson('{{ route('passkeys.login') }}', {
                    username,
                    data: formatAssertion(assertion),
                });

                setMessage('ログインに成功しました。リダイレクトしています...');
                if (result?.redirect) {
                    window.location.href = result.redirect;
                }
            } catch (error) {
                console.error(error);
                setMessage(error?.message || 'パスキー認証に失敗しました。', true);
            }
        };

        return { login };
    })();

    document.querySelector('[data-passkey-login]')?.addEventListener('click', () => passkeyLogin.login());
</script>
