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

    const createDebugPanel = () => {
        const existing = document.getElementById('passkey-debug-panel');
        if (existing) return existing;

        const panel = document.createElement('section');
        panel.id = 'passkey-debug-panel';
        panel.style.position = 'fixed';
        panel.style.bottom = '1rem';
        panel.style.right = '1rem';
        panel.style.maxWidth = '320px';
        panel.style.width = '90%';
        panel.style.background = 'rgba(0,0,0,0.75)';
        panel.style.color = '#f8f8f8';
        panel.style.padding = '0.8rem';
        panel.style.borderRadius = '0.75rem';
        panel.style.fontSize = '0.8rem';
        panel.style.zIndex = '1000';
        panel.style.maxHeight = '40vh';
        panel.style.overflow = 'auto';
        panel.innerHTML = '<strong style="display:block;margin-bottom:0.4rem;">Passkey Debug</strong><div data-debug-log></div>';
        document.body.appendChild(panel);

        return panel;
    };

    const appendDebugLog = (entry) => {
        const panel = createDebugPanel();
        const container = panel.querySelector('[data-debug-log]');
        const item = document.createElement('div');
        item.style.borderTop = '1px solid rgba(255,255,255,0.15)';
        item.style.padding = '0.25rem 0';
        item.textContent = `${entry.timestamp} [${entry.event}] ${entry.summary || ''}`;
        container.prepend(item);

        while (container.childElementCount > 30) {
            container.removeChild(container.lastChild);
        }
    };

    const getCookie = (name) => {
        const cookies = document.cookie.split('; ').map(cookie => cookie.split('='));
        const match = cookies.find(([key]) => key === encodeURIComponent(name));
        return match ? decodeURIComponent(match[1]) : '';
    };

    const setCookie = (name, value, days = 30) => {
        const expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = `${encodeURIComponent(name)}=${encodeURIComponent(value)}; expires=${expires}; path=/`;
    };

    const serializeError = (error) => {
        if (!error) return null;

        return {
            name: error.name,
            message: error.message,
            stack: error.stack,
            responseStatus: error.responseStatus,
            responseBody: error.responseBody,
            url: error.url,
        };
    };

    const getCsrfMeta = () => document.querySelector('meta[name="csrf-token"]');

    const postDebugEvent = async (payload = {}) => {
        try {
            const meta = getCsrfMeta();
            await fetch('{{ route('debug.passkey-events') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': meta?.content || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
        } catch (error) {
            console.debug('[passkey][debug-endpoint] failed', serializeError(error));
        }
    };

    const logDebug = (event, payload = {}, options = {}) => {
        const entry = {
            timestamp: new Date().toISOString(),
            event,
            visibilityState: document.visibilityState,
            userAgent: navigator.userAgent,
            ...payload,
        };

        console.debug('[passkey]', entry);
        if (options.panel !== false) {
            appendDebugLog({ ...entry, summary: payload.summary });
        }

        if (options.send) {
            postDebugEvent(entry);
        }
    };

    const usernameInput = document.querySelector('input[name="username"]');
    const savedUsername = getCookie(COOKIE_NAME);
    const lastLogoutClick = sessionStorage.getItem('lastLogoutClick');
    if (savedUsername && usernameInput) {
        usernameInput.value = savedUsername;
    }

    logDebug('passkey:page:context', {
        location: window.location.href,
        referrer: document.referrer,
        savedUsername,
        savedUsernamePresent: !!savedUsername,
        lastLogoutClick: lastLogoutClick ? new Date(Number(lastLogoutClick)).toISOString() : null,
        summary: `ctx saved:${savedUsername ? 'yes' : 'no'} ref:${document.referrer || '-'} logout:${lastLogoutClick || 'none'}`,
    });

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
            logDebug('fetch:request', {
                url,
                payload,
                summary: `POST ${url}`,
            });

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
                logDebug('fetch:error', {
                    url,
                    error: serializeError(error),
                    summary: `network error ${url}`,
                }, { send: true });
                throw requestError;
            }

            const data = await response.json().catch(() => ({}));

            logDebug('fetch:response', {
                url,
                status: response.status,
                ok: response.ok,
                body: data,
                summary: `${url} status ${response.status}`,
            }, { send: !response.ok });

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

            logDebug('passkey:login:start', {
                username,
                savedUsername,
                referrer: document.referrer,
                location: window.location.href,
                lastLogoutClick: lastLogoutClick ? new Date(Number(lastLogoutClick)).toISOString() : null,
                summary: 'passkey login flow start',
            });

            try {
                const optionPayload = await fetchJson('{{ route('passkeys.options') }}', { username });
                const publicKey = transformOptions(optionPayload?.options);

                logDebug('passkey:options:received', {
                    status: optionPayload ? 'ok' : 'empty',
                    hasChallenge: !!optionPayload?.options?.challenge,
                    summary: 'options received',
                }, { send: !optionPayload });

                if (!publicKey) {
                    throw new Error('認証情報の取得に失敗しました。');
                }

                logDebug('navigator.credentials.get:start', {
                    allowCredentials: publicKey.allowCredentials?.length || 0,
                    userVerification: publicKey.userVerification,
                    summary: 'begin navigator.credentials.get',
                });

                const assertion = await navigator.credentials.get({ publicKey }).catch((error) => {
                    logDebug('navigator.credentials.get:failure', {
                        error: serializeError(error),
                        summary: error?.message || 'navigator.credentials.get failed',
                    }, { send: true });
                    throw error;
                });

                logDebug('navigator.credentials.get:success', {
                    id: assertion?.id,
                    type: assertion?.type,
                    summary: 'assertion acquired',
                });

                const result = await fetchJson('{{ route('passkeys.login') }}', {
                    username,
                    data: formatAssertion(assertion),
                    state: optionPayload?.state,
                });

                logDebug('passkey:login:response', {
                    redirect: result?.redirect,
                    summary: `login response status:${result?.redirect ? 'redirect' : 'ok'}`,
                });

                setMessage('ログインに成功しました。リダイレクトしています...');
                if (result?.redirect) {
                    window.location.href = result.redirect;
                }
            } catch (error) {
                logDebug('passkey:login:error', {
                    error: serializeError(error),
                    summary: error?.message || 'passkey login failed',
                }, { send: true });
                setMessage(error?.message || 'パスキー認証に失敗しました。', true);
            }
        };

        return { login };
    })();

    document.querySelector('[data-passkey-login]')?.addEventListener('click', () => passkeyLogin.login());
</script>
