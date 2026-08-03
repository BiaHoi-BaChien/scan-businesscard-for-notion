<x-layouts.app>
    <section class="workflow-grid" x-data="Object.assign(deviceState(), cardUploader(false), processor())" x-init="initDeviceState(); scrollToResultsIfNeeded(@json(!is_null(session('analysis'))))">
        <article class="panel">
            <header class="panel-header">
                <div>
                    <h2>名刺アップロード</h2>
                    <p>表裏最大2枚。PCではドラッグ＆ドロップも利用できます。</p>
                </div>
            </header>
            <form method="POST" action="{{ route('cards.analyze') }}" enctype="multipart/form-data" class="stack gap-sm" @submit.prevent="submit($event)" data-message="解析中..." data-success="解析が完了しました" data-upload-form>
                @csrf
                <template x-if="!isMobile">
                    <label class="dropzone" @dragover.prevent @drop.prevent="handleDrop($event)">
                        ここにファイルをドロップ（表面推奨）、またはクリックして選択
                        <input type="file" name="front" accept="image/*" capture="environment" @change="updateLabel($event)">
                    </label>
                </template>
                <template x-if="isMobile">
                    <div class="stack gap-sm">
                        <div class="field-label">表面の画像を選択</div>
                        <div class="grid grid-2 mobile-actions">
                            <button type="button" class="secondary" @click="openMobilePicker('frontInput', false)">ギャラリーから選択</button>
                            <button type="button" class="secondary" @click="openMobilePicker('frontInput', true)">カメラで撮影</button>
                        </div>
                        <input type="file" x-ref="frontInput" name="front" accept="image/*" @change="updateLabel($event)" class="visually-hidden">
                        <p class="field-hint" aria-live="polite">選択中: <span x-text="frontFileName || 'なし'"></span></p>
                    </div>
                </template>
                <template x-if="!isMobile">
                    <div class="grid grid-2 align-center">
                        <div class="muted">裏面</div>
                        <label class="file-label">ファイルを選択
                            <input type="file" name="back" accept="image/*" capture="environment" @change="updateLabel($event)">
                        </label>
                    </div>
                </template>
                <template x-if="isMobile">
                    <div class="stack gap-sm">
                        <div class="field-label">裏面（任意）</div>
                        <div class="grid grid-2 mobile-actions">
                            <button type="button" class="secondary" @click="openMobilePicker('backInput', false)">ギャラリーから選択</button>
                            <button type="button" class="secondary" @click="openMobilePicker('backInput', true)">カメラで撮影</button>
                        </div>
                        <input type="file" x-ref="backInput" name="back" accept="image/*" @change="updateLabel($event)" class="visually-hidden">
                        <p class="field-hint" aria-live="polite">選択中: <span x-text="backFileName || 'なし'"></span></p>
                    </div>
                </template>
                <p class="field-hint">画像は保存せず、解析処理にのみ使用します。</p>
                <button type="submit" :disabled="!hasFiles || processing" class="primary block">解析する</button>
            </form>
        </article>
        <article class="panel" id="analysis-results">
            <header class="panel-header">
                <div>
                    <h2>解析結果</h2>
                    <p>抽出内容を確認し、必要に応じて修正してください。</p>
                </div>
            </header>
            <div class="stack gap-sm">
                @php
                    $labels = [
                        'name' => '氏名',
                        'job_title' => '役職',
                        'company' => '会社名',
                        'address' => '住所（都道府県・市区町村）',
                        'website' => '会社サイトURL',
                        'email' => 'メールアドレス',
                        'phone_number_1' => '電話番号1',
                        'phone_number_2' => '電話番号2',
                        'industry' => '業種',
                    ];
                    $analysis = session('analysis');
                @endphp
                @if($analysis)
                    <form method="POST" action="{{ route('cards.notion') }}" class="stack gap-sm" data-message="Notionへ登録中..." data-success="Notionへの登録が完了しました" @submit.prevent="submit($event)" x-data="{ ok: false }">
                        @csrf
                        <div class="grid grid-2">
                            @foreach($labels as $key => $label)
                                @if(array_key_exists($key, $analysis))
                                    <label class="field-label">{{ $label }}
                                        <input
                                            type="text"
                                            name="{{ $key }}"
                                            value="{{ old($key, $analysis[$key]) }}"
                                            @if($key === 'address')
                                                placeholder="例: 東京都千代田区1-1-1"
                                                aria-describedby="address-hint"
                                            @endif
                                        >
                                    </label>
                                @endif
                            @endforeach
                        </div>
                        @if(array_key_exists('address', $analysis))
                            <p class="field-hint" id="address-hint">
                                住所の入力は「都道府県」などを新字体で統一してください。
                            </p>
                        @endif
                        <label class="confirm-label"><input type="checkbox" x-model="ok"> 内容を確認しました</label>
                        <button type="submit" class="primary block" :disabled="!ok || processing">Notionに登録する</button>
                        @if(session('notion_url'))
                            <div>
                                <a
                                    href="{{ session('notion_url') }}"
                                    class="secondary"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    登録したNotionページを開く
                                </a>
                            </div>
                        @endif
                    </form>
                @else
                    <div class="empty-state">名刺を解析すると、ここに確認項目が表示されます。</div>
                @endif
            </div>
        </article>
        <div class="workflow-actions full-span">
            <form method="POST" action="{{ route('cards.clear') }}" data-message="" @submit.prevent="clearAll($event)">
                @csrf
                <button type="submit" class="button-link" :disabled="processing">入力内容をクリア</button>
            </form>
        </div>

        <template x-if="processing && showOverlay">
            <div class="processing-overlay" role="dialog" aria-modal="true" aria-label="処理状況">
                <article class="processing-dialog">
                    <p class="wave-text" aria-live="assertive">
                        <template x-for="(char, idx) in messageChars" :key="idx">
                            <span
                                class="message-char"
                                :class="{ 'wave-char': shouldAnimateMessage() }"
                                :style="`animation-delay:${idx * 60}ms`"
                                x-text="char"
                            ></span>
                        </template>
                    </p>
                    <button type="button" class="secondary" @click="cancel">キャンセル</button>
                </article>
            </div>
        </template>
    </section>

    <section class="security-section">
        @if($passkeys->isEmpty())
            <header>
                <h2>パスキー登録</h2>
                <p>この端末にパスキーを登録すると、次回から簡単にログインできます。</p>
            </header>
            <div class="stack gap-sm security-form">
                <label class="field-label">デバイス名（任意）
                    <input type="text" id="passkey-device-name" placeholder="例: 自宅PC">
                </label>
                <p class="field-hint">共有端末では登録しないでください。</p>
                <button type="button" class="secondary" data-passkey-register aria-describedby="passkey-register-message">この端末にパスキーを登録</button>
                <small class="inline-message" id="passkey-register-message" role="status" aria-live="polite"></small>
            </div>
        @else
            <header>
                <h2>登録済みパスキー</h2>
                <p>不要になった端末のパスキーを削除できます。</p>
            </header>
            <ul class="passkey-list">
                @foreach($passkeys as $passkey)
                    <li class="passkey-item">
                        <div>
                            <strong>{{ $passkey->name ?: '名称未設定の端末' }}</strong>
                            <span>登録日: {{ $passkey->created_at?->format('Y/m/d H:i') }}</span>
                        </div>
                        <form method="POST" action="{{ route('passkeys.destroy', $passkey) }}" onsubmit="return confirm('このパスキーを削除しますか？');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="danger-button">削除</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.app>
<script>
    function deviceState() {
        return {
            isMobile: false,
            initDeviceState() {
                const mediaQuery = window.matchMedia('(max-width: 768px)');
                const syncDeviceFlag = () => {
                    this.isMobile = mediaQuery.matches || navigator.maxTouchPoints > 0;
                };

                syncDeviceFlag();
                mediaQuery.addEventListener('change', syncDeviceFlag);
            },
            scrollToResultsIfNeeded(hasAnalysis) {
                if (!hasAnalysis || !this.isMobile) return;
                const target = document.getElementById('analysis-results');
                if (!target) return;
                requestAnimationFrame(() => target.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            },
        };
    }

    function cardUploader(initialHasFiles = false) {
        return {
            processing: false,
            hasFiles: initialHasFiles,
            frontFileName: '',
            backFileName: '',
            clearForm() {
                document.querySelectorAll('input[type=file]').forEach(el => el.value = '');
                this.hasFiles = false;
                this.frontFileName = '';
                this.backFileName = '';
            },
            openMobilePicker(refName, useCamera = false) {
                const input = this.$refs?.[refName];
                if (!input || this.processing) return;

                if (useCamera) {
                    input.setAttribute('capture', 'environment');
                } else {
                    input.removeAttribute('capture');
                }

                input.click();
            },
            handleDrop(e) {
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const front = document.querySelector('input[name="front"]');
                    front.files = files;
                    this.frontFileName = files[0]?.name || '';
                    this.updateHasFiles();
                }
            },
            updateLabel(event) {
                const target = event?.target;
                const selectedFile = target?.files?.[0]?.name || '';

                if (target?.name === 'front') {
                    this.frontFileName = selectedFile;
                }

                if (target?.name === 'back') {
                    this.backFileName = selectedFile;
                }

                this.updateHasFiles();
            },
            updateHasFiles() {
                const uploadForm = document.querySelector('form[data-upload-form]');
                if (uploadForm) {
                    this.hasFiles = Array.from(uploadForm.querySelectorAll('input[type=file]')).some(input => input.files.length > 0);
                }
            }
        }
    }

    function processor() {
        return {
            processing: false,
            controller: null,
            message: '',
            messageChars: [],
            showOverlay: true,
            successMessage: '処理が完了しました',
            setMessage(msg) {
                this.message = msg || '';
                this.messageChars = this.message.split('');
            },
            async handleSuccess() {
                this.setMessage(this.successMessage);
                await new Promise(resolve => setTimeout(resolve, 800));
            },
            shouldAnimateMessage() {
                return this.message !== this.successMessage;
            },
            async clearAll(event) {
                if (this.processing) return;
                this.clearForm();
                await this.submit(event, { overlay: false });
            },
            async submit(event, opts = {}) {
                if (this.processing) return;
                this.showOverlay = opts.overlay !== false;
                this.processing = true;
                this.setMessage(this.showOverlay ? (event.target.dataset.message || '処理中...') : '');
                this.controller = new AbortController();
                let successHandled = false;

                try {
                    const formData = new FormData(event.target);

                    const response = await fetch(event.target.action, {
                        method: event.target.method,
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        signal: this.controller.signal,
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const contentType = response.headers.get('content-type') || '';

                    if (contentType.includes('text/html')) {
                        const html = await response.text();
                        successHandled = true;
                        document.open();
                        document.write(html);
                        document.close();
                        return;
                    }

                    if (response.redirected) {
                        successHandled = true;
                        window.location.href = response.url;
                        return;
                    }

                    await this.handleSuccess();
                    successHandled = true;
                    window.location.reload();
                } catch (e) {
                    if (e.name === 'AbortError') return;
                    alert('通信に失敗しました');
                } finally {
                    this.processing = false;
                    this.showOverlay = true;
                    if (!successHandled) this.setMessage('');
                }
            },
            cancel() {
                if (this.controller) {
                    this.controller.abort();
                }
                this.processing = false;
                this.showOverlay = true;
                this.setMessage('');
            }
        }
    }

    const passkeyRegistration = (() => {
        const base64URLToBuffer = (value) => {
            const normalized = value.replace(/-/g, '+').replace(/_/g, '/');
            const padded = normalized.padEnd(normalized.length + (4 - (normalized.length % 4)) % 4, '=');

            return Uint8Array.from(atob(padded), c => c.charCodeAt(0)).buffer;
        };
        const bufferToBase64URL = (buffer) => btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

        const setMessage = (message, isError = false) => {
            const el = document.getElementById('passkey-register-message');
            if (!el) return;
            el.textContent = message || '';
            el.classList.toggle('is-error', isError);
        };

        const transformOptions = (options) => {
            if (!options?.challenge) return null;

            const publicKey = { ...options };
            publicKey.challenge = base64URLToBuffer(options.challenge);

            if (options.user?.id) {
                publicKey.user = {
                    ...options.user,
                    id: base64URLToBuffer(options.user.id),
                };
            }

            if (Array.isArray(options.excludeCredentials)) {
                publicKey.excludeCredentials = options.excludeCredentials.map(item => ({
                    ...item,
                    id: base64URLToBuffer(item.id),
                }));
            }

            return publicKey;
        };

        const formatAttestation = (credential) => ({
            id: credential.id,
            type: credential.type,
            rawId: bufferToBase64URL(credential.rawId),
            response: {
                attestationObject: bufferToBase64URL(credential.response.attestationObject),
                clientDataJSON: bufferToBase64URL(credential.response.clientDataJSON),
            },
            clientExtensionResults: credential.getClientExtensionResults(),
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

        const register = async () => {
            if (!('credentials' in navigator) || !('PublicKeyCredential' in window)) {
                setMessage('パスキーに対応したブラウザでお試しください。', true);
                return;
            }

            setMessage('パスキー登録の準備中です...');

            try {
                const name = document.getElementById('passkey-device-name')?.value || null;
                const optionPayload = await fetchJson('{{ route('passkeys.register.options') }}', { name });
                const publicKey = transformOptions(optionPayload?.options);

                if (!publicKey) {
                    throw new Error('登録情報の取得に失敗しました。');
                }

                const credential = await navigator.credentials.create({ publicKey });

                await fetchJson('{{ route('passkeys.register') }}', {
                    name,
                    data: formatAttestation(credential),
                    state: optionPayload?.state,
                });

                window.location.reload();
            } catch (error) {
                console.error(error);
                setMessage(error?.message || 'パスキーの登録に失敗しました。', true);
            }
        };

        return { register };
    })();

    document.querySelector('[data-passkey-register]')?.addEventListener('click', () => passkeyRegistration.register());
</script>
