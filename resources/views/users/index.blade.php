<x-layouts.app>
    <section class="grid grid-2">
        <article>
            <h2>ユーザー一覧</h2>
            <ul>
                @foreach($users as $user)
                    <li class="grid" style="align-items:center; grid-template-columns: 1fr 1fr 1fr auto; gap:0.5rem;">
                        <div>
                            <strong>{{ $user->username }}</strong><br>
                            <small class="muted">{{ $user->email }}</small>
                        </div>
                        <span>{{ $user->is_admin ? 'ADMIN' : 'USER' }}</span>
                        <span>@if($user->hasPasskey())<span class="badge">パスキー登録済</span>@endif</span>
                        <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('削除しますか？');">
                            @csrf @method('DELETE')
                            <button type="submit" class="secondary">削除</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </article>
        <article>
            <h2>ユーザー追加</h2>
            <form method="POST" action="{{ route('users.store') }}" class="grid">
                @csrf
                <label>ユーザー名<input name="username" required></label>
                <label>メールアドレス<input name="email" type="email"></label>
                <label>パスワード<input name="password" type="password" required></label>
                <label><input type="checkbox" name="is_admin" value="1"> 管理者として作成</label>
                <button type="submit">追加</button>
            </form>
        </article>
    </section>
</x-layouts.app>
