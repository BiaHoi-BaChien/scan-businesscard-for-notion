<x-layouts.app>
    <section class="cards" x-data="Object.assign(cardUploader(false), processor())" x-init="initFlash(@json(session('toast')), @json(session('status')))">
        <article class="panel">
            <header class="grid" style="gap:0.35rem; align-items:flex-start;">
                <div>
                    <h2 style="margin:0;">名刺アップロード</h2>
                    <p class="muted" style="margin:0;">表裏最大2枚。ドラッグ＆ドロップにも対応しています。</p>
                </div>
            </header>
            <form method="POST" action="{{ route('cards.analyze') }}" enctype="multipart/form-data" class="stack gap-sm" @submit.prevent="submit($event)" data-message="解析中..." data-success="解析が完了しました" data-upload-form>
                @csrf
                <label class="dropzone" @dragover.prevent @drop.prevent="handleDrop($event)">
                    ここにファイルをドロップ（表面推奨）、またはクリックして選択
                    <input type="file" name="front" accept="image/*" @change="updateLabel($event)">
                </label>
                <div class="grid grid-2 align-center">
                    <div class="muted">裏面</div>
                    <label class="file-label">ファイルを選択
                        <input type="file" name="back" accept="image/*" @change="updateLabel($event)">
                    </label>
                </div>
                <p class="muted">送信すると画像を保存せずに解析を実行します。</p>
                <button type="submit" :disabled="!hasFiles || processing" class="primary block">解析する</button>
            </form>
        </article>
        <article class="panel">
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
                            <span class="wave-char" :style="`animation-delay:${idx * 60}ms`" x-text="char"></span>
                        </template>
                    </p>
                    <button type="button" class="secondary" @click="cancel">CANCEL</button>
                </article>
            </div>
        </template>
        <div class="toast-container" x-show="toastVisible" :class="{'toast-active': toastVisible}" :key="toastKey" x-transition.opacity.duration.200ms style="display:none;" @click="hideToast()">
            <div class="toast contrast">
                <p x-text="toastMessage"></p>
            </div>
        </div>
    </section>

    <section class="grid grid-2" style="margin-top:1.5rem; align-items:stretch;">
        <article class="panel">
            <h3 style="margin-bottom:0.35rem;">パスキー登録（WebAuthn/FIDO2）</h3>
            <p class="muted">スマホの顔認証や指紋・PINでログインできます。</p>
            <div class="stack gap-sm">
                <button type="button" id="register-passkey">この端末で登録する</button>
                <p class="muted" style="margin:0;">登録後は「パスキーでログイン」から生体認証でサインインできます。</p>
            </div>
        </article>
        @if(auth()->user()->is_admin)
            <article class="panel">
                <header class="grid" style="gap:0.25rem;">
                    <h3 style="margin:0;">ユーザー管理</h3>
                    <p class="muted" style="margin:0;">追加・更新・削除</p>
                </header>
                <a href="{{ route('users.index') }}" role="button" class="secondary">管理画面へ</a>
            </article>
        @endif
    </section>
</x-layouts.app>
<script>
    function cardUploader(initialHasFiles = false) {
        return {
            processing: false,
            hasFiles: initialHasFiles,
            clearForm() {
                document.querySelectorAll('input[type=file]').forEach(el => el.value = '');
                this.hasFiles = false;
            },
            handleDrop(e) {
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const front = document.querySelector('input[name="front"]');
                    front.files = files;
                    this.hasFiles = true;
                }
            },
            updateLabel() {
                const uploadForm = document.querySelector('form[data-upload-form]');
                if (uploadForm) {
                    this.hasFiles = Array.from(uploadForm.querySelectorAll('input[type=file]')).some(input => input.files.length > 0) || this.hasFiles;
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
            toastMessage: '',
            toastVisible: false,
            toastTimer: null,
            initFlash(toast, status) {
                if (toast === 'analysis_complete') {
                    this.showToast('解析が完了しました');
                    return;
                }
                if (toast === 'notion_complete') {
                    this.showToast('Notionへの登録が完了しました');
                    return;
                }
                if (status) this.showToast(status);
            },
            setMessage(msg) {
                this.message = msg || '';
                this.messageChars = this.message.split('');
            },
            showToast(message) {
                if (!message) return;
                this.toastMessage = message;
                this.toastVisible = true;
                clearTimeout(this.toastTimer);
                this.toastTimer = setTimeout(() => this.hideToast(), 6000);
            },
            hideToast() {
                this.toastVisible = false;
            },
            success(event) {
                const msg = event.target?.dataset?.success;
                if (msg) this.showToast(msg);
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

                try {
                    const formData = new FormData(event.target);

                    const response = await fetch(event.target.action, {
                        method: event.target.method,
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        signal: this.controller.signal,
                    });

                    if (response.redirected) {
                        this.success(event);
                        window.location.href = response.url;
                        return;
                    }

                    this.success(event);
                    window.location.reload();
                } catch (e) {
                    if (e.name === 'AbortError') return;
                    alert('通信に失敗しました');
                } finally {
                    this.processing = false;
                    this.showOverlay = true;
                    this.setMessage('');
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

    document.addEventListener('DOMContentLoaded', () => {
        const registerButton = document.getElementById('register-passkey');
        if (!registerButton) return;

        if (!window.webauthnClient) {
            registerButton.disabled = true;
            registerButton.textContent = 'このブラウザはパスキー非対応';
            return;
        }

        registerButton.addEventListener('click', async () => {
            registerButton.disabled = true;
            const original = registerButton.textContent;
            registerButton.textContent = '登録中';

            try {
                await window.webauthnClient.register();
                alert('パスキーを登録しました');
                window.location.reload();
            } catch (e) {
                registerButton.disabled = false;
                registerButton.textContent = original;
                alert('パスキー登録に失敗しました。再度お試しください。');
            }
        });
    });
</script>
