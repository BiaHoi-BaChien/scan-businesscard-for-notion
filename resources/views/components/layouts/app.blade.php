<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}" sizes="512x512">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" type="image/png" sizes="512x512" href="{{ asset('favicon.png') }}">
    <title>{{ config('app.display_name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <script>window.appDebug = @json(config('app.debug'));</script>
    @vite('resources/js/app.js')
    <style>
        :root {
            --bg: #f6f7f9;
            --surface: #ffffff;
            --surface-subtle: #f8fafc;
            --border: #d8dee6;
            --border-strong: #b8c1cc;
            --text: #182230;
            --muted: #5b6572;
            --primary: #1d4ed8;
            --primary-hover: #1e40af;
            --primary-soft: #eff6ff;
            --success: #166534;
            --success-soft: #f0fdf4;
            --danger: #b42318;
            --danger-soft: #fff1f0;
            --focus: rgba(29, 78, 216, 0.25);
        }
        * { box-sizing: border-box; letter-spacing: 0; }
        html { overflow-x: hidden; }
        body { min-width: 0; background: var(--bg); color: var(--text); font-family: "Noto Sans JP", system-ui, -apple-system, sans-serif; }
        body > header, body > main, body > footer { width: 100%; }
        a, button, input { border-radius: 6px; }
        button, [role="button"], input[type="submit"] { min-height: 44px; }
        button:focus-visible, a:focus-visible, input:focus-visible, summary:focus-visible { outline: 3px solid var(--focus); outline-offset: 2px; }
        .app-header { background: var(--surface); border-bottom: 1px solid var(--border); }
        .app-nav { display: flex; min-width: 0; align-items: center; justify-content: space-between; gap: 1rem; padding-top: 0.85rem; padding-bottom: 0.85rem; }
        .app-brand { display: inline-flex; min-width: 0; align-items: baseline; gap: 0.55rem; color: var(--text); text-decoration: none; }
        .app-title { min-width: 0; margin: 0; color: var(--text); font-size: 1.15rem; font-weight: 700; line-height: 1.25; overflow-wrap: anywhere; }
        .app-subtitle { color: var(--muted); font-size: 0.78rem; white-space: nowrap; }
        .app-account { display: flex; flex: 0 0 auto; align-items: center; gap: 0.8rem; }
        .app-user { color: var(--muted); font-size: 0.875rem; font-weight: 600; }
        .app-account form { margin: 0; }
        .button-link { width: auto; min-height: 36px; margin: 0; padding: 0.35rem 0.55rem; border-color: transparent; background: transparent; color: var(--muted); box-shadow: none; font-size: 0.875rem; }
        .button-link:hover { border-color: var(--border); background: var(--surface-subtle); color: var(--text); }
        main.container { padding-top: 1.5rem; padding-bottom: 2.5rem; }
        .notice { margin-bottom: 1rem; padding: 0.8rem 1rem; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); }
        .notice p, .notice ul { margin: 0; }
        .notice-success { border-color: #bbdfc5; background: var(--success-soft); color: var(--success); }
        .notice-error { border-color: #f1c0bb; background: var(--danger-soft); color: var(--danger); }
        .grid { display: grid; gap: 1rem; }
        @media (min-width: 768px) { .grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .muted { color: var(--muted); }
        .workflow-grid { display: grid; min-width: 0; align-items: start; gap: 1rem; grid-template-columns: 1fr; }
        @media (min-width: 960px) { .workflow-grid { grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr); } }
        .full-span { grid-column: 1 / -1; }
        .panel { min-width: 0; margin: 0; padding: 1.25rem; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); box-shadow: none; }
        .panel-header { margin: -1.25rem -1.25rem 1.15rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); border-radius: 8px 8px 0 0; background: var(--surface-subtle); }
        .panel-header h2, .panel-header h3 { margin: 0; color: var(--text); font-size: 1.05rem; line-height: 1.35; }
        .panel-header p { margin: 0.25rem 0 0; color: var(--muted); font-size: 0.875rem; }
        .stack { display: grid; }
        .stack.gap-sm { gap: 0.65rem; }
        .block { width: 100%; }
        .dropzone { display: grid; gap: 0.75rem; padding: 1.25rem; border: 2px dashed var(--border-strong); border-radius: 6px; background: var(--surface-subtle); color: var(--text); cursor: pointer; text-align: center; transition: border-color 0.15s ease, background 0.15s ease; }
        .dropzone:hover { border-color: var(--primary); background: var(--primary-soft); }
        .file-label { display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem; margin: 0; padding: 0.55rem 0.9rem; border: 1px solid var(--border-strong); border-radius: 6px; background: var(--surface); color: var(--text); cursor: pointer; transition: background 0.15s ease; }
        .file-label:hover { background: var(--surface-subtle); }
        .file-label input[type=file] { display: none; }
        .visually-hidden { position: absolute !important; width: 1px !important; max-width: 1px !important; height: 1px !important; padding: 0 !important; margin: -1px !important; overflow: hidden; clip: rect(0, 0, 0, 0); clip-path: inset(50%); white-space: nowrap; border: 0 !important; }
        .align-center { align-items: center; }
        .field-label { color: var(--text); font-size: 0.875rem; font-weight: 600; }
        .field-label input { margin-top: 0.35rem; font-weight: 400; }
        .field-hint { margin: 0; color: var(--muted); font-size: 0.82rem; }
        .empty-state { min-height: 9rem; display: grid; place-items: center; padding: 1.5rem; border: 1px dashed var(--border); border-radius: 6px; background: var(--surface-subtle); color: var(--muted); text-align: center; }
        .workflow-actions { display: flex; justify-content: flex-end; }
        .confirm-label { display: inline-flex; align-items: center; gap: 0.45rem; color: var(--text); font-weight: 600; }
        .confirm-label input { margin: 0; }
        form button.primary { background: var(--primary); border-color: var(--primary); }
        form button.primary:hover { background: var(--primary-hover); border-color: var(--primary-hover); }
        .security-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border); }
        .security-section header { margin-bottom: 1rem; }
        .security-section h2 { margin: 0; font-size: 1.05rem; }
        .security-section header p { margin: 0.25rem 0 0; color: var(--muted); font-size: 0.875rem; }
        .security-form { max-width: 680px; }
        .passkey-list { display: grid; max-width: 680px; gap: 0.65rem; margin: 0; padding: 0; list-style: none; }
        .passkey-item { display: flex; min-width: 0; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.85rem 1rem; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); }
        .passkey-item > div { display: grid; min-width: 0; gap: 0.15rem; }
        .passkey-item strong { overflow-wrap: anywhere; }
        .passkey-item span { color: var(--muted); font-size: 0.8rem; }
        .passkey-item form { flex: 0 0 auto; margin: 0; }
        .danger-button { width: auto; margin: 0; padding-inline: 0.9rem; border-color: #d92d20; background: var(--surface); color: var(--danger); }
        .danger-button:hover { border-color: var(--danger); background: var(--danger-soft); color: var(--danger); }
        .inline-message { min-height: 1.25rem; color: var(--muted); }
        .inline-message.is-error { color: var(--danger); }
        .processing-overlay { position: fixed; inset: 0; z-index: 50; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(2px); }
        .processing-dialog { width: min(100%, 360px); margin: 0; padding: 1.25rem; border-radius: 8px; background: var(--surface); color: var(--text); text-align: center; }
        .processing-dialog p { margin-bottom: 1rem; }
        .toast-container { position: fixed; left: 50%; top: 50%; z-index: 60; transform: translate(-50%, -50%); pointer-events: none; }
        .toast-container.toast-active { animation: toast-fade-in 0.25s ease forwards; }
        .toast { min-width: 240px; padding: 0.85rem 1.1rem; border-radius: 6px; background: #14532d; color: #f0fdf4; box-shadow: 0 10px 30px rgba(0,0,0,0.18); text-align: center; pointer-events: auto; }
        @keyframes toast-fade-in { from { transform: translate(-50%, -50%) scale(0.96); opacity: 0; } to { transform: translate(-50%, -50%) scale(1); opacity: 1; } }
        @keyframes waveChar { 0% { transform: translateY(0); } 30% { transform: translateY(-4px); } 60% { transform: translateY(0); } 100% { transform: translateY(0); } }
        .message-char { display: inline-block; }
        .wave-char { animation: waveChar 1s ease-in-out infinite; }
        .wave-char:nth-child(odd) { animation-delay: 0.08s; }
        .wave-char:nth-child(2n) { animation-delay: 0.16s; }
        .wave-char:nth-child(3n) { animation-delay: 0.24s; }
        .auth-shell { width: min(100%, 460px); margin: 1rem auto 0; }
        .auth-panel { margin: 0; padding: 1.5rem; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); box-shadow: none; }
        .auth-panel h1 { margin: 0 0 0.35rem; font-size: 1.45rem; }
        .auth-intro { margin: 0 0 1.25rem; color: var(--muted); font-size: 0.9rem; }
        .auth-divider { display: flex; align-items: center; gap: 0.75rem; margin: 1.35rem 0; color: var(--muted); font-size: 0.8rem; }
        .auth-divider::before, .auth-divider::after { content: ""; height: 1px; flex: 1; background: var(--border); }
        .passkey-login h2 { margin: 0; font-size: 1rem; }
        .passkey-login p { margin: 0.25rem 0 0.9rem; color: var(--muted); font-size: 0.875rem; }
        @media (max-width: 767px) {
            .app-nav { align-items: flex-start; }
            .app-brand { display: grid; gap: 0.1rem; }
            .app-account { gap: 0.35rem; }
            .app-user { display: none; }
            main.container { padding-top: 1rem; }
            .panel { padding: 1rem; }
            .panel-header { margin: -1rem -1rem 1rem; padding: 0.85rem 1rem; }
            .grid-2 { grid-template-columns: minmax(0, 1fr); }
            .mobile-actions { grid-template-columns: minmax(0, 1fr); }
            .security-section { margin-top: 1.5rem; }
            .passkey-item { align-items: stretch; flex-direction: column; }
            .passkey-item form, .passkey-item .danger-button { width: 100%; }
            .auth-shell { margin-top: 0; }
            .auth-panel { padding: 1.15rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; }
        }
    </style>
</head>
<body>
<header class="app-header">
    <nav class="container app-nav" aria-label="メインナビゲーション">
        <a class="app-brand" href="{{ auth()->check() ? route('dashboard') : route('login.form') }}">
            <span class="app-title">{{ config('app.display_name') }}</span>
            <span class="app-subtitle">Notion連携</span>
        </a>
        @auth
            <div class="app-account">
                <span class="app-user">{{ auth()->user()->username }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="button-link">ログアウト</button>
                </form>
            </div>
        @endauth
    </nav>
</header>
<main class="container">
    @if(session('status'))
        <div class="notice notice-success" role="status">{{ session('status') }}</div>
    @endif
    @if(session('notion_url'))
        <div class="notice notice-success" role="status">
            <div class="grid align-center">
                <p>Notionページが作成されました。</p>
                <a href="{{ session('notion_url') }}" class="secondary" target="_blank" rel="noopener noreferrer">
                    登録したNotionページを開く
                </a>
            </div>
        </div>
    @endif
    @if($errors->any())
        <div class="notice notice-error" role="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    {{ $slot ?? '' }}
</main>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('form[action="{{ route('logout') }}"]')?.forEach((form) => {
            form.addEventListener('submit', () => {
                sessionStorage.setItem('lastLogoutClick', String(Date.now()));
            });
        });
    });
</script>
</body>
</html>
