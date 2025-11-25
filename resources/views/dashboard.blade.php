<x-layouts.app>
    <section class="grid grid-2" x-data="Object.assign(cardUploader(), processor())">
        <article>
            <header class="grid" style="gap:0.25rem;">
                <h2>名刺アップロード</h2>
                <p class="muted">表と裏の最大2枚までアップロードできます。ドラッグ＆ドロップ対応。</p>
            </header>
            <form method="POST" action="{{ route('cards.upload') }}" enctype="multipart/form-data" data-upload-form>
                @csrf
                <label class="dropzone" @dragover.prevent @drop.prevent="handleDrop($event)">
                    ここにファイルをドロップ（表面推奨）、またはクリックして選択！
                    <input type="file" name="front" accept="image/*" @change="updateLabel($event)">
                </label>
                <div class="grid grid-2">
                    <div></div>
                    <label>裏面<input type="file" name="back" accept="image/*"></label>
                </div>
                <div class="grid" style="margin-top:0.5rem;">
                    <button type="button" class="secondary" @click="clearForm" :disabled="processing">クリア</button>
                </div>
            </form>
            <form method="POST" action="{{ route('cards.analyze') }}" class="grid" style="margin-top:1rem;" @submit.prevent="submit($event)" data-message="解析中…">
                @csrf
                <button type="submit" :disabled="{{ $card?->front_path || $card?->back_path ? 'false' : 'true' }} || processing">解析する</button>
            </form>
        </article>
        <article>
            <header class="grid" style="gap:0.25rem;">
                <h2>解析結果</h2>
                <p class="muted">OpenAI APIで抽出された内容を確認してからNotionに登録</p>
            </header>
            <div class="grid">
                @if($card && $card->analysis)
                    @php
                        $labels = [
                            'name' => '氏名',
                            'company' => '会社名',
                            'website' => '会社サイトURL',
                            'email' => 'メールアドレス',
                            'phone_number_1' => '電話番号1',
                            'phone_number_2' => '電話番号2',
                            'industry' => '業種',
                        ];
                    @endphp
                    @foreach($labels as $key => $label)
                        @if(array_key_exists($key, $card->analysis))
                            <label class="muted">{{ $label }}<input type="text" value="{{ $card->analysis[$key] }}" readonly></label>
                        @endif
                    @endforeach
                    <form method="POST" action="{{ route('cards.notion') }}" x-data="{ ok: false }" @submit.prevent="submit($event)" data-message="Notionへ登録中…">
                        @csrf
                        <label><input type="checkbox" x-model="ok"> この内容でOK</label>
                        <button type="submit" :disabled="!ok || processing">Notionに登録する</button>
                    </form>
                @else
                    <p class="muted">解析結果がまだありません。</p>
                @endif
            </div>
        </article>
    </section>

    <section class="grid grid-2" style="margin-top:1.5rem;">
        <article>
            <h3>パスキー登録（WebAuthn/FIDO2）</h3>
            <p class="muted">スマホの指紋認証やFace IDを使ってログインできるようにします。</p>
            <div class="grid">
                <button type="button" id="register-passkey">この端末を登録する</button>
                <p class="muted" style="margin:0;">登録後、「パスキーでログイン」ボタンから生体認証でサインインできます。</p>
            </div>
        </article>
        @if(auth()->user()->is_admin)
            <article>
                <header class="grid" style="gap:0.25rem;">
                    <h3>ユーザー管理</h3>
                    <p class="muted">追加・更新・削除</p>
                </header>
                <a href="{{ route('users.index') }}" role="button" class="secondary">管理画面へ</a>
            </article>
        @endif
    </section>
</x-layouts.app>
<script>
    function cardUploader() {
        return {
            processing: false,
            clearForm() { document.querySelectorAll('input[type=file]').forEach(el => el.value = '') },
            handleDrop(e) {
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const front = document.querySelector('input[name="front"]');
                    front.files = files;
                }
            },
            updateLabel(e) {
                this.processing = false;
            }
        }
    }

    function processor() {
        return {
            processing: false,
            controller: null,
            message: '',
            async submit(event) {
                if (this.processing) return;
                this.processing = true;
                this.controller = new AbortController();

                try {
                    const uploadForm = document.querySelector('form[data-upload-form]');
                    if (uploadForm) {
                        const hasFiles = Array.from(uploadForm.querySelectorAll('input[type=file]')).some(input => input.files.length > 0);
                        if (hasFiles) {
                            this.message = 'アップロード中…';
                            const uploadResponse = await fetch(uploadForm.action, {
                                method: uploadForm.method || 'POST',
                                body: new FormData(uploadForm),
                                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                signal: this.controller.signal,
                            });
                            if (!uploadResponse.ok) {
                                throw new Error('upload_failed');
                            }
                        }
                    }

                    this.message = event.target.dataset.message || '処理中…';
                    const formData = new FormData(event.target);

                    const response = await fetch(event.target.action, {
                        method: event.target.method,
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        signal: this.controller.signal,
                    });

                    if (response.redirected) {
                        window.location.href = response.url;
                        return;
                    }

                    window.location.reload();
                } catch (e) {
                    if (e.name === 'AbortError') return;
                    const message = e.message === 'upload_failed' ? 'アップロードに失敗しました' : '処理に失敗しました';
                    alert(message);
                } finally {
                    this.processing = false;
                }
            },
            cancel() {
                if (this.controller) {
                    this.controller.abort();
                }
                this.processing = false;
                this.message = '';
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
            registerButton.textContent = '登録中…';

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
<template x-if="processing">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;z-index:50;">
        <article class="contrast" style="min-width:280px;text-align:center;">
            <p x-text="message || '処理中…'"></p>
            <button type="button" class="secondary" @click="cancel">CANCEL</button>
        </article>
    </div>
</template>
