<x-layouts.app>
    <section class="cards" x-data="Object.assign(deviceState(), cardUploader(false), processor())" x-init="initDeviceState(); scrollToResultsIfNeeded(@json(!is_null(session('analysis'))))">
        <article class="panel">
            <header class="grid" style="gap:0.35rem; align-items:flex-start;">
                <div>
                    <h2 style="margin:0;">名刺アップロード</h2>
                    <p class="muted" style="margin:0;">表裏最大2枚。PCならドラッグ＆ドロップにも対応しています。</p>
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
                        <div class="muted">表面の画像を選択</div>
                        <div class="grid grid-2">
                            <button type="button" class="secondary" @click="openMobilePicker('frontInput', false)">ギャラリーから選択</button>
                            <button type="button" class="secondary" @click="openMobilePicker('frontInput', true)">カメラで撮影</button>
                        </div>
                        <input type="file" x-ref="frontInput" name="front" accept="image/*" @change="updateLabel($event)" class="visually-hidden">
                        <p class="muted" aria-live="polite">選択中: <span x-text="frontFileName || 'なし'"></span></p>
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
                        <div class="muted">裏面（任意）</div>
                        <div class="grid grid-2">
                            <button type="button" class="secondary" @click="openMobilePicker('backInput', false)">ギャラリーから選択</button>
                            <button type="button" class="secondary" @click="openMobilePicker('backInput', true)">カメラで撮影</button>
                        </div>
                        <input type="file" x-ref="backInput" name="back" accept="image/*" @change="updateLabel($event)" class="visually-hidden">
                        <p class="muted" aria-live="polite">選択中: <span x-text="backFileName || 'なし'"></span></p>
                    </div>
                </template>
                <p class="muted">送信すると画像を保存せずに解析を実行します。</p>
                <button type="submit" :disabled="!hasFiles || processing" class="primary block">解析する</button>
            </form>
        </article>
        <article class="panel" id="analysis-results">
            <header class="grid" style="gap:0.35rem; align-items:flex-start;">
                <div>
                    <h2 style="margin:0;">解析結果</h2>
                    <p class="muted" style="margin:0;">OpenAIで抽出した内容を確認してください。</p>
                </div>
            </header>
            <div class="stack gap-sm">
                @php
                    $labels = [
                        'name' => '氏名',
                        'job_title' => '役職',
                        'company' => '会社名',
                        'address' => '住所',
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
                                    <label class="muted">{{ $label }}
                                        <input type="text" name="{{ $key }}" value="{{ old($key, $analysis[$key]) }}">
                                    </label>
                                @endif
                            @endforeach
                        </div>
                        <label><input type="checkbox" x-model="ok"> この内容でOK</label>
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
                    <p class="muted">まだ解析結果がありません。</p>
                @endif
            </div>
        </article>
        <form method="POST" action="{{ route('cards.clear') }}" class="full-span" data-message="" @submit.prevent="clearAll($event)">
            @csrf
            <button type="submit" class="secondary block" :disabled="processing">クリア</button>
        </form>

        <template x-if="processing && showOverlay">
            <div style="position:fixed;inset:0;background:rgba(0,0,0,0.55);display:flex;align-items:center;justify-content:center;z-index:50;backdrop-filter:blur(1px);">
                <article class="contrast overlay-card">
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
                    <button type="button" class="secondary" @click="cancel">CANCEL</button>
                </article>
            </div>
        </template>
    </section>

    <section style="margin-top:1.5rem; align-items:stretch;">
        <article class="panel">
            <header class="grid" style="gap:0.25rem;">
                <h3 style="margin:0;">パスキー登録</h3>
                <p class="muted" style="margin:0;">この端末にパスキーを登録して、次回以降のログインを簡単にしましょう。</p>
            </header>
            <div class="stack gap-sm">
                <label class="muted">デバイス名（任意）
                    <input type="text" id="passkey-device-name" placeholder="例: 自宅PC">
                </label>
                <p class="muted" style="margin:0;">ブラウザのパスキー機能を利用します。共有端末では実施しないでください。</p>
                <button type="button" class="secondary" data-passkey-register>この端末にパスキーを登録</button>
                <small class="muted" id="passkey-register-message"></small>
            </div>
        </article>
    </section>

    @if(auth()->user()->is_admin)
        <section style="margin-top:1.5rem; align-items:stretch;">
            <article class="panel">
                <header class="grid" style="gap:0.25rem;">
                    <h3 style="margin:0;">ユーザー管理</h3>
                </header>
                <a href="{{ route('users.index') }}" role="button" class="secondary">管理画面へ</a>
            </article>
        </section>
    @endif
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

                    if (response.redirected) {
                        await this.handleSuccess();
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
        const base64URLToBuffer = (value) => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0)).buffer;
        const bufferToBase64URL = (buffer) => btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

        const setMessage = (message, isError = false) => {
            const el = document.getElementById('passkey-register-message');
            if (!el) return;
            el.textContent = message || '';
            el.style.color = isError ? '#c00' : 'inherit';
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
                const publicKey = transformOptions(optionPayload);

                if (!publicKey) {
                    throw new Error('登録情報の取得に失敗しました。');
                }

                const credential = await navigator.credentials.create({ publicKey });

                await fetchJson('{{ route('passkeys.register') }}', {
                    name,
                    data: formatAttestation(credential),
                });

                setMessage('パスキーを登録しました。次回からパスキーでログインできます。');
            } catch (error) {
                console.error(error);
                setMessage(error?.message || 'パスキーの登録に失敗しました。', true);
            }
        };

        return { register };
    })();

    document.querySelector('[data-passkey-register]')?.addEventListener('click', () => passkeyRegistration.register());
</script>
