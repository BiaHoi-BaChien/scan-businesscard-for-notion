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
    const COOKIE_NAME = 'username';

    const getCookie = (name) => {
        const cookies = document.cookie.split('; ').map(cookie => cookie.split('='));
        const match = cookies.find(([key]) => key === encodeURIComponent(name));
        return match ? decodeURIComponent(match[1]) : '';
    };

    const setCookie = (name, value, days = 30) => {
        const expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = `${encodeURIComponent(name)}=${encodeURIComponent(value)}; expires=${expires}; path=/`;
    };

    const getCsrfMeta = () => document.querySelector('meta[name="csrf-token"]');

    const usernameInput = document.querySelector('input[name="username"]');
    const savedUsername = getCookie(COOKIE_NAME);
    if (savedUsername && usernameInput) {
        usernameInput.value = savedUsername;
    }

    document.querySelector('form[action="{{ route('login') }}"]')?.addEventListener('submit', () => {
        const username = usernameInput?.value.trim();
        if (username) {
            setCookie(COOKIE_NAME, username);
        }
    });

    const passkeyLogin = (() => {
        const base64URLToBuffer = (value) => {
            const normalized = value.replace(/-/g, '+').replace(/_/g, '/');
            const padded = normalized.padEnd(normalized.length + (4 - (normalized.length % 4)) % 4, '=');

            return Uint8Array.from(atob(padded), c => c.charCodeAt(0)).buffer;
        };
        const bufferToBase64URL = (buffer) => btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        let inFlight = false;
        let attemptId = 0;
        const reloadFlagKey = 'passkey-reloaded-after-stale';

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
            const csrfMeta = getCsrfMeta();

            let response;
            try {
                response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfMeta?.content || '',
                    },
                    body: JSON.stringify(payload),
                });
            } catch (error) {
                const requestError = new Error('ネットワークエラーが発生しました。');
                requestError.url = url;
                throw requestError;
            }

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                const err = new Error(data?.message || 'リクエストに失敗しました。');
                err.responseStatus = response.status;
                err.responseBody = data;
                err.url = url;
                throw err;
            }

            return data;
        };

        const login = async () => {
            if (inFlight) {
                return; // prevent reusing a previous attempt
            }

            if (!('credentials' in navigator) || !('PublicKeyCredential' in window)) {
                setMessage('このブラウザはパスキーに対応していません。', true);
                return;
            }

            const username = document.querySelector('input[name="username"]').value.trim();
            if (!username) {
                setMessage('ユーザー名を入力してください。', true);
                return;
            }

            setMessage('パスキー認証を開始します...');
            inFlight = true;
            const currentAttempt = ++attemptId;

            try {
                const optionPayload = await fetchJson('{{ route('passkeys.options') }}', { username });
                const publicKey = transformOptions(optionPayload?.options);

                if (!publicKey) {
                    throw new Error('認証オプションの取得に失敗しました。');
                }

                if (currentAttempt !== attemptId) {
                    return; // aborted because a newer attempt started
                }

                const assertion = await navigator.credentials.get({ publicKey }).catch((error) => {
                    throw error;
                });

                if (currentAttempt !== attemptId) {
                    return; // aborted because a newer attempt started
                }

                const result = await fetchJson('{{ route('passkeys.login') }}', {
                    username,
                    data: formatAssertion(assertion),
                    state: optionPayload?.state,
                });

                setMessage('認証に成功しました。リダイレクト中...');
                if (result?.redirect) {
                    window.location.href = result.redirect;
                }
            } catch (error) {
                if (error?.responseStatus === 419 && !sessionStorage.getItem(reloadFlagKey)) {
                    sessionStorage.setItem(reloadFlagKey, '1');
                    setMessage('Session expired. Reloading the page to retry passkey.');
                    window.location.reload();
                    return;
                }
                setMessage(error?.message || '認証に失敗しました。', true);
            } finally {
                inFlight = false;
            }
        };
        return { login };
    })();

    document.querySelector('[data-passkey-login]')?.addEventListener('click', () => passkeyLogin.login());
</script>
