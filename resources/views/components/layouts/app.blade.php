<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="{{ asset('webauthn.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.webauthnClient) return;

            const supportsPasskey = 'PublicKeyCredential' in window && typeof WebAuthn !== 'undefined';
            if (!supportsPasskey) return;

            try {
                window.webauthnClient = new WebAuthn();
            } catch (error) {
                console.warn('Failed to initialize WebAuthn client', error);
            }
        });
    </script>
    <style>
        :root {
            --bg: #f4f8f3;
            --card-bg: #ffffff;
            --border: #d7e5da;
            --text: #1f2937;
            --muted: #5f6b63;
            --primary: #16a34a;
            --primary-soft: #e8f6ec;
            --header-bg: #0f3a2d;
            --header-text: #e9f5ed;
            --shadow: 0 14px 40px rgba(0, 0, 0, 0.08);
        }
        body { background: radial-gradient(circle at 18% 22%, #e8f6ec 0, #f4f8f3 28%, #f4f8f3 100%); color: var(--text); font-family: "Manrope", "Noto Sans JP", system-ui, -apple-system, sans-serif; }
        header nav { background: transparent; box-shadow: none; }
        .badge { padding: 0.2rem 0.65rem; border-radius: 999px; background: #0f9f4f; color: #fff; font-size: 0.8rem; }
        .dropzone { border: 2px dashed #9bd6ad; padding: 1.2rem; border-radius: 0.9rem; text-align: center; background: var(--primary-soft); color: var(--text); cursor: pointer; transition: border-color 0.2s ease, transform 0.1s ease; }
        .dropzone:hover { border-color: var(--primary); transform: translateY(-2px); }
        .grid { display: grid; gap: 1rem; }
        @media (min-width: 768px) { .grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .muted { color: var(--muted); }
        .cards { display: grid; gap: 1.2rem; grid-template-columns: 1fr; }
        @media (min-width: 960px) { .cards { grid-template-columns: 1.05fr 0.95fr; } }
        .full-span { grid-column: 1 / -1; }
        .panel { background: var(--card-bg); padding: 1.4rem; border-radius: 1rem; border: 1px solid var(--border); box-shadow: var(--shadow); }
        .panel header { margin: -1.4rem -1.4rem 1rem; padding: 1rem 1.25rem; background: var(--header-bg); color: var(--header-text); border-radius: 1rem 1rem 0.75rem 0.75rem; }
        .panel header h2, .panel header h3 { color: var(--header-text); margin: 0; }
        .panel header p { color: rgba(233, 245, 237, 0.78); margin: 0.2rem 0 0; }
        .stack { display: grid; }
        .stack.gap-sm { gap: 0.65rem; }
        .block { width: 100%; }
        .file-label { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.55rem 0.9rem; border-radius: 0.55rem; background: #e3f6ea; color: var(--text); cursor: pointer; border: 1px solid #9bd6ad; transition: background 0.15s ease; }
        .file-label:hover { background: #d5f0df; }
        .file-label input[type=file] { display: none; }
        .align-center { align-items: center; }
        .toast-container { position: fixed; left: 50%; top: 50%; transform: translate(-50%, -50%); z-index: 60; pointer-events: none; }
        .toast-container.toast-active { animation: toast-slide-in 0.45s ease forwards; }
        .toast { padding: 0.85rem 1.1rem; border-radius: 0.65rem; box-shadow: 0 10px 30px rgba(0,0,0,0.18); min-width: 240px; text-align: center; background: #0f5132; color: #ecfdf3; pointer-events: auto; }
        @keyframes toast-slide-in {
            from { transform: translate(-50%, -120%); opacity: 0; }
            to { transform: translate(-50%, -50%); opacity: 1; }
        }
        @keyframes waveChar { 0% { transform: translateY(0); } 30% { transform: translateY(-4px); } 60% { transform: translateY(0); } 100% { transform: translateY(0); } }
        .wave-char { display: inline-block; animation: waveChar 1s ease-in-out infinite; }
        .wave-char:nth-child(odd) { animation-delay: 0.08s; }
        .wave-char:nth-child(2n) { animation-delay: 0.16s; }
        .wave-char:nth-child(3n) { animation-delay: 0.24s; }
        .overlay-card { min-width: 320px; text-align: center; }
        main.container { padding-top: 1.5rem; padding-bottom: 2rem; }
        nav ul { align-items: center; }
        nav li strong { color: var(--text); }
        form button.primary { background: var(--primary); border-color: var(--primary); }
        form button.primary:hover { filter: brightness(0.92); }
    </style>
</head>
<body>
<header class="container">
    <nav>
        <ul>
            <li><strong>{{ config('app.name') }}</strong></li>
        </ul>
        <ul>
            @auth
                <li>{{ auth()->user()->username }} @if(auth()->user()->hasPasskey())<span class="badge">パスキー登録済み</span>@endif</li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="contrast">ログアウト</button></form>
                </li>
            @endauth
        </ul>
    </nav>
</header>
<main class="container">
    @if(session('status'))
        <article class="contrast">{{ session('status') }}</article>
    @endif
    @if($errors->any())
        <article class="contrast">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </article>
    @endif
    {{ $slot ?? '' }}
</main>
</body>
</html>
