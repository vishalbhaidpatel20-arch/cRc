<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CRC') - Client Support</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#f4f6f8;color:#1f2933}
        header{background:#1f3a5f;color:#fff;padding:.75rem 1.5rem;display:flex;justify-content:space-between;align-items:center}
        header .who{font-size:.9rem}
        .badge{background:#ffffff26;border-radius:999px;padding:.1rem .6rem;margin-left:.4rem;font-size:.75rem;text-transform:uppercase}
        header button{background:transparent;color:#fff;border:1px solid #ffffff80;border-radius:6px;padding:.3rem .8rem;cursor:pointer}
        main{max-width:960px;margin:2rem auto;padding:0 1rem}
        .card{background:#fff;border-radius:10px;padding:1.5rem;box-shadow:0 1px 3px #0001}
        .error{color:#b42318;font-size:.85rem;margin-top:.3rem}
        nav a{color:#fff;margin-left:1.2rem;text-decoration:none;opacity:.85}
        nav a:hover{opacity:1;text-decoration:underline}
        table.list{width:100%;border-collapse:collapse;margin-top:1rem}
        table.list th{text-align:left;border-bottom:1px solid #cbd2d9;padding:.4rem}
        table.list td{border-bottom:1px solid #eee;padding:.4rem}
        .muted{color:#7b8794}
        .flash{color:#067647;margin:.6rem 0}
        .filters{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem}
        .filters input,.filters select,.f input,.f select{padding:.5rem;border:1px solid #cbd2d9;border-radius:6px}
        .f input,.f select{width:100%;margin:.3rem 0 .6rem}
        .btn{display:inline-block;padding:.5rem 1rem;background:#1f3a5f;color:#fff;border:0;border-radius:6px;cursor:pointer;text-decoration:none;font-size:.9rem}
        .btn.light{background:#e4e7eb;color:#1f2933}
        .tag{font-size:.75rem;background:#e4e7eb;border-radius:999px;padding:.1rem .5rem}
        .tag.off{background:#fde8e8;color:#b42318}
    </style>
</head>
<body>
@auth
    <header>
        <nav>
            <strong>Client Support</strong>
            <a href="{{ route(auth()->user()->homeRoute()) }}">Dashboard</a>
            <a href="{{ route('companies.index') }}">Companies</a>
            <a href="{{ route('contacts.index') }}">Contacts</a>
            @if(auth()->user()->isAdmin())<a href="{{ route('admin.users.index') }}">Users</a>@endif
        </nav>
        <form method="POST" action="{{ route('logout') }}" class="who">
            @csrf
            {{ auth()->user()->full_name }}<span class="badge">{{ auth()->user()->role }}</span>
            <button type="submit">Log out</button>
        </form>
    </header>
@endauth
<main>@yield('content')</main>
</body>
</html>
