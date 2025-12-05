<x-layouts.app>
    <section class="grid grid-2">
        <article class="panel">
            <header class="grid" style="gap:0.35rem; align-items:flex-start;">
                <div>
                    <h2 style="margin:0;">ユーザー一覧</h2>
                    <p class="muted" style="margin:0;">登録済みユーザーの権限を管理します。</p>
                </div>
            </header>
            <ul class="user-list">
                @foreach($users as $user)
                    <li class="user-card">
                        <div class="user-header">
                            <div>
                                <strong>{{ $user->username }}</strong>
                            </div>
                            <span class="role-pill">{{ $user->is_admin ? 'ADMIN' : 'USER' }}</span>
                        </div>
                        <div class="user-actions">
                            <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('削除しますか？');">
                                @csrf @method('DELETE')
                                <button type="submit" class="secondary">削除</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </article>
        <article class="panel">
            <header class="grid" style="gap:0.35rem; align-items:flex-start;">
                <div>
                    <h2 style="margin:0;">ユーザー追加</h2>
                    <p class="muted" style="margin:0;">新しいメンバーを作成できます。</p>
                </div>
            </header>
            <form method="POST" action="{{ route('users.store') }}" class="grid">
                @csrf
                <label>ユーザー名<input name="username" required></label>
                <label>パスワード<input name="password" type="password" required></label>
                <label><input type="checkbox" name="is_admin" value="1"> 管理者として作成</label>
                <button type="submit">追加</button>
            </form>
        </article>
    </section>
</x-layouts.app>
