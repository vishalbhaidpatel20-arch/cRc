<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'cRc') - cRc</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/crc-badge.svg') }}">
    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#f4f6f8;color:#1f2933}
        header{background:#fff;color:#1F1A5C;padding:0 1.5rem;height:65px;display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #5B4BDB}
        header .who{font-size:.9rem}
        .logo{height:48px;width:48px;display:block}
        nav a.brand{display:flex;align-items:center;margin-right:1.5rem}
        nav a.brand:hover{text-decoration:none}
        nav{display:flex;align-items:center}
        .badge{background:#ece9ff;color:#1F1A5C;border-radius:999px;padding:.1rem .6rem;margin-left:.4rem;font-size:.75rem;text-transform:uppercase}
        header button{background:transparent;color:#1F1A5C;border:1px solid #1F1A5C;border-radius:6px;padding:.3rem .8rem;cursor:pointer}
        main{max-width:960px;margin:2rem auto;padding:0 1rem}
        .card{background:#fff;border-radius:10px;padding:1.5rem;box-shadow:0 1px 3px #0001}
        .error{color:#b42318;font-size:.85rem;margin-top:.3rem}
        nav a{color:#1F1A5C;margin-left:1.2rem;text-decoration:none;font-weight:500}
        nav a:hover{color:#5B4BDB;text-decoration:underline}
        nav a.brand{margin-left:0}
        table.list{width:100%;border-collapse:collapse;margin-top:1rem}
        table.list th{text-align:left;border-bottom:1px solid #cbd2d9;padding:.4rem}
        table.list td{border-bottom:1px solid #eee;padding:.4rem}
        .muted{color:#7b8794}
        .flash{color:#067647;margin:.6rem 0}
        .filters{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem}
        .filters input,.filters select,.f input,.f select{padding:.5rem;border:1px solid #cbd2d9;border-radius:6px}
        .f input,.f select{width:100%;margin:.3rem 0 .6rem}
        .btn{display:inline-block;padding:.5rem 1rem;background:#1F1A5C;color:#fff;border:0;border-radius:6px;cursor:pointer;text-decoration:none;font-size:.9rem}
        .btn.light{background:#e4e7eb;color:#1f2933}
        .tag{font-size:.75rem;background:#e4e7eb;border-radius:999px;padding:.1rem .5rem}
        .tag.off{background:#fde8e8;color:#b42318}
        .tag.critical{background:#fde8e8;color:#b42318}
        .tag.high{background:#fff0d6;color:#9a5b00}
        .tag.medium{background:#e7f0ff;color:#1d4e9e}
        .tag.low{background:#e4e7eb;color:#52606d}
        .overdue{color:#b42318;font-weight:600}

        /* Contact page / conversation log */
        .contact-grid{display:grid;grid-template-columns:300px minmax(0,1fr);gap:1.5rem;align-items:start}
        @media (max-width:800px){.contact-grid{grid-template-columns:1fr}}
        .avatar{flex:0 0 56px;width:56px;height:56px;border-radius:50%;background:#cfe3ff;color:#1e4f9c;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:1.05rem}
        .kv{display:grid;grid-template-columns:88px minmax(0,1fr);gap:.6rem .8rem;font-size:.9rem;margin:1rem 0}
        .kv dt{color:#7b8794}
        .kv dd{margin:0;overflow-wrap:anywhere}
        hr.soft{border:0;border-top:1px solid #e5e5ea;margin:1rem 0}
        .pill{display:inline-block;font-size:.78rem;line-height:1.5;padding:.1rem .65rem;border-radius:999px;background:#e4e7eb;color:#52606d}
        .pill.green{background:#cdeede;color:#0b5b3a}
        .pill.teal{background:#c8ece4;color:#0b5b50;font-size:.85rem;padding:.15rem .75rem}
        .pill.bug,.pill.late{background:#fbd6d6;color:#a11a1a}
        .pill.customization{background:#e1dcff;color:#4a3fb0}
        .pill.query{background:#dbe8ff;color:#1d4e9e}
        .pill.follow_up,.pill.fu{background:#fbe3b0;color:#7a5200}
        .pill.done{background:#e4e7eb;color:#52606d}
        .btn.outline{background:#fff;color:#1f2933;border:1px solid #cbd2d9}
        .btn.block{display:block;width:100%;text-align:center}
        .log-panel{border:1px solid #e5e5ea;border-radius:12px;padding:1rem 1.1rem}
        .log-panel h3{margin:0 0 .8rem;font-size:1rem}
        .log-panel select,.log-panel input,.log-panel textarea{width:100%;padding:.6rem .7rem;border:1px solid #d5d8dd;border-radius:8px;background:#fff;font:inherit;font-size:.92rem}
        .log-panel textarea{resize:vertical;min-height:84px}
        .log-panel label{font-size:.8rem;color:#7b8794;display:block;margin:.6rem 0 .25rem}
        .row3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.6rem}
        @media (max-width:560px){.row3{grid-template-columns:1fr}}
        .log-foot{display:flex;justify-content:space-between;align-items:center;gap:.8rem;margin-top:.7rem;flex-wrap:wrap}
        .log-foot .fu{display:flex;align-items:center;gap:.5rem;background:none;padding:0;font-size:.9rem;color:#52606d}
        .log-foot input[type=date]{width:auto}
        .hist-head{display:flex;justify-content:space-between;align-items:baseline;border-bottom:1px solid #e5e5ea;padding-bottom:.45rem;margin-top:1.4rem}
        .hist-head h3{margin:0;font-size:1rem}
        .hist-item{display:flex;gap:.8rem;padding:.85rem 0;border-bottom:1px solid #eee}
        .hist-icon{flex:0 0 34px;width:34px;height:34px;border-radius:50%;background:#f1f3f5;display:flex;align-items:center;justify-content:center;color:#3e4c59}
        .hist-top{display:flex;justify-content:space-between;gap:1rem}
        .hist-tags{display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;margin-top:.4rem}
    </style>
</head>
<body>
@auth
    <header>
        <nav>
            <a class="brand" href="{{ route(auth()->user()->homeRoute()) }}">
                <img class="logo" src="{{ asset('images/crc-badge.svg') }}" alt="cRc, Customer Relationship Console" title="cRc, Customer Relationship Console">
            </a>
            <a href="{{ route(auth()->user()->homeRoute()) }}">Dashboard</a>
            <a href="{{ route('companies.index') }}">Companies</a>
            <a href="{{ route('contacts.index') }}">Contacts</a>
            <a href="{{ route('interactions.index') }}">Conversations</a>
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.users.index') }}">Users</a>
            @endif
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
