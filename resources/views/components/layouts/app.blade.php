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
        window.webauthnRoutes = {
            registerOptions: "{{ route('webauthn.register.options') }}",
            register: "{{ route('webauthn.register') }}",
            loginOptions: "{{ route('webauthn.login.options') }}",
            login: "{{ route('webauthn.login') }}",
        };
        window.webauthnClient = typeof WebAuthn !== 'undefined' && WebAuthn.supportsWebAuthn()
            ? new WebAuthn(window.webauthnRoutes)
            : null;
    </script>
    <style>
        .badge { padding: 0.15rem 0.5rem; border-radius: 999px; background: #0f766e; color: #fff; font-size: 0.8rem; }
        .dropzone { border: 2px dashed #9ca3af; padding: 1rem; border-radius: 0.75rem; text-align: center; background: #f9fafb; cursor: pointer; }
        .grid { display: grid; gap: 1rem; }
        @media (min-width: 768px) { .grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .muted { color: #6b7280; }
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
                <li>{{ auth()->user()->username }} @if(auth()->user()->hasPasskey())<span class="badge">パスキー登録済</span>@endif</li>
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
