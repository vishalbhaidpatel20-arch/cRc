<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'cRc') - cRc</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/crc-badge.svg') }}">
    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#f4f6f8;color:#1f2933;min-height:100vh;display:flex;flex-direction:column}
        header{background:#fff;color:#1F1A5C;padding:0 1.5rem;height:65px;display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #5B4BDB}
        header .who{font-size:.9rem}
        .logo{height:48px;width:48px;display:block}
        nav a.brand{display:flex;align-items:center;margin-right:1.5rem}
        nav a.brand:hover{text-decoration:none}
        nav{display:flex;align-items:center}
        .badge{background:#ece9ff;color:#1F1A5C;border-radius:999px;padding:.1rem .6rem;margin-left:.4rem;font-size:.75rem;text-transform:uppercase}
        header button{background:transparent;color:#1F1A5C;border:1px solid #1F1A5C;border-radius:6px;padding:.3rem .8rem;cursor:pointer}
        main{width:100%;max-width:960px;margin:2rem auto;padding:0 1rem;flex:1 0 auto}
        /* Signed-in pages: the content fills the first screen, so the footer starts below the fold and shows only when you scroll */
        body:has(header) main{min-height:calc(100vh - 65px - 4rem)}
        footer.site{flex:0 0 auto;background:#fff;border-top:1px solid #e5e5ea;padding:.7rem 1.5rem;font-size:.85rem;color:#7b8794;display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap}
        /* Pages with a data grid, and the dashboards, use the full width of the window */
        main:has(.grid-skeleton:not([data-page-size])),main:has(table[data-grid]:not([data-page-size])),main:has(.grid-host:not([data-page-size])),main:has(.dash-hero){max-width:none;padding:0 1.5rem}
        .grid-host{width:100%;margin-top:1rem;--ag-font-size:14px}
        .grid-host .row-new{background:#f0fbf4}
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
        .filters input[name=q]{width:250px}
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
        /* Pop-up dialogs */
        dialog.modal{border:0;border-radius:14px;padding:1.3rem 1.4rem;width:min(520px,92vw);max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px #0004;color:inherit}
        dialog.modal.wide{width:min(640px,94vw)}
        dialog.modal::backdrop{background:#1f1a5c66}
        .modal-head{display:flex;justify-content:space-between;align-items:center}
        .modal-x{background:none;border:0;font-size:1.6rem;line-height:1;cursor:pointer;color:#7b8794;padding:0 .3rem}
        .modal-foot{display:flex;justify-content:flex-end;gap:.6rem;margin-top:.6rem}
        tr.row-new td{background:#f0fbf4}
        .stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.8rem}
        .stat{display:flex;flex-direction:column;gap:.15rem;background:#fff;border:1px solid #e5e5ea;border-radius:12px;padding:.9rem 1rem;text-decoration:none;color:inherit}
        a.stat:hover{border-color:#5B4BDB}
        .stat-n{font-size:1.7rem;font-weight:600;line-height:1.1}
        .stat.warn .stat-n{color:#9a5b00}
        .stat.bad .stat-n{color:#b42318}
        .bar{display:flex;height:14px;border-radius:7px;overflow:hidden;background:#eef0f3;min-width:14px}
        .bar span{display:block;min-width:4px}
        .hist-tags{display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;margin-top:.4rem}
        /* Data grid placeholder (js/grid-size.js marks <html class="js"> before the first paint): the plain table is never shown
           while the grid loads, so the unpaginated rows cannot flash. Without JavaScript the table shows instead. */
        html.js .grid-skeleton ~ table[data-grid]{display:none}
        html:not(.js) .grid-skeleton{display:none}
        .grid-skeleton{width:100%;margin-top:1rem;border:1px solid #dde1e6;border-radius:8px;background:#fff;overflow:hidden}
        .grid-skeleton .gs-head{height:49px;background:#f8f9fb;border-bottom:1px solid #dde1e6}
        .grid-skeleton .gs-row{height:56px;border-bottom:1px solid #eef0f3;display:flex;align-items:center;padding:0 16px}
        .grid-skeleton .gs-row span{display:block;width:60%;height:10px;border-radius:5px;background:#eef0f3}
        /* Notification bell (partials/notification-bell.blade.php, js/notifications.js) */
        .hright{display:flex;align-items:center;gap:1rem}
        .bell{position:relative}
        header .bell-btn{position:relative;display:flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid #d5d8dd;border-radius:50%;background:#fff;color:#1F1A5C;padding:0;margin:0;text-decoration:none}
        header .bell-btn:hover{background:#f3f0ff;text-decoration:none}
        .bell-count{position:absolute;top:-5px;right:-5px;min-width:18px;height:18px;padding:0 4px;border-radius:9px;background:#d92d20;color:#fff;font-size:.7rem;font-weight:600;line-height:18px;text-align:center}
        .bell-count[hidden]{display:none}
        .bell-panel{position:absolute;right:0;top:calc(100% + 10px);width:380px;max-width:92vw;background:#fff;border:1px solid #e5e5ea;border-radius:12px;box-shadow:0 12px 40px #0003;z-index:60;overflow:hidden}
        .bell-panel[hidden]{display:none}
        .bell-head{display:flex;justify-content:space-between;align-items:center;padding:.7rem 1rem;border-bottom:1px solid #eee}
        header .bell-link{border:0;background:none;padding:0;color:#5B4BDB;font-size:.85rem;cursor:pointer}
        header .bell-link:hover{text-decoration:underline}
        .bell-list{max-height:60vh;overflow-y:auto}
        .bell-item{display:grid;grid-template-columns:auto 1fr;gap:.2rem .6rem;padding:.7rem 1rem;border-bottom:1px solid #f0f0f3;color:#1f2933;text-decoration:none!important;margin:0!important;font-weight:400!important}
        .bell-item:hover{background:#f8f7ff}
        .bell-item.unread{background:#f3f0ff;box-shadow:inset 3px 0 0 #5B4BDB}
        .bell-text{font-size:.9rem;overflow-wrap:anywhere}
        .bell-detail,.bell-time{grid-column:2;font-size:.78rem;color:#7b8794}
        .bell-empty{padding:1.4rem 1rem;text-align:center;color:#7b8794;font-size:.9rem}
        header .bell-foot{display:block;text-align:center;padding:.65rem;font-size:.85rem;border-top:1px solid #eee;margin:0}
        .bell-tag{align-self:start;font-size:.7rem;font-weight:600;border-radius:999px;padding:.1rem .55rem;background:#e4e7eb;color:#52606d;white-space:nowrap}
        .bell-tag.assigned{background:#dbe8ff;color:#1d4e9e}
        .bell-tag.completed{background:#cdeede;color:#0b5b3a}
        .bell-toast{position:fixed;right:1.5rem;bottom:1.5rem;max-width:360px;background:#1F1A5C;color:#fff;padding:.8rem 1.1rem;border-radius:10px;box-shadow:0 10px 30px #0004;font-size:.9rem;z-index:70}
        .bell-toast[hidden]{display:none}
        /* Dashboards: hero, stat cards, panels, breakdown bars (admin/dashboard, agent/dashboard) */
        .dash-hero{display:flex;justify-content:space-between;align-items:flex-end;gap:1rem 2rem;flex-wrap:wrap;margin:.2rem 0 1.4rem}
        .dash-eyebrow{font-size:.78rem;font-weight:600;letter-spacing:.09em;text-transform:uppercase;color:#7b8794;min-height:1em}
        .dash-hero h2{margin:.25rem 0 .1rem;font-size:1.75rem;letter-spacing:-.015em;color:#1F1A5C}
        .dash-sub{margin:0;color:#52606d}
        .dash-actions{display:flex;gap:.5rem;flex-wrap:wrap}
        .dash-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem}
        .dstat{display:flex;align-items:center;gap:.95rem;background:#fff;border:1px solid #e8eaf0;border-radius:14px;padding:1rem 1.1rem;text-decoration:none;color:inherit;box-shadow:0 1px 2px #1f1a5c0f;transition:transform .12s,box-shadow .12s,border-color .12s}
        a.dstat:hover{transform:translateY(-2px);box-shadow:0 8px 22px #1f1a5c1a;border-color:#cfc9f7}
        .dstat-ico{flex:0 0 46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center}
        .dstat-body{display:flex;flex-direction:column;min-width:0}
        .dstat-n{font-size:1.85rem;font-weight:700;line-height:1.05;letter-spacing:-.02em}
        .dstat-l{font-size:.88rem;font-weight:600;color:#3e4c59;margin-top:.15rem}
        .dstat-h{font-size:.76rem;color:#7b8794}
        .tone-brand .dstat-ico{background:#ece9ff;color:#5B4BDB}
        .tone-info .dstat-ico{background:#e1efff;color:#1d6fc4}
        .tone-good .dstat-ico{background:#e1f5ea;color:#0b7a4b}
        .tone-warn .dstat-ico{background:#fff1d6;color:#b26b00}
        .tone-bad .dstat-ico{background:#fde8e8;color:#c0291e}
        .tone-warn .dstat-n{color:#b26b00}
        .tone-bad .dstat-n{color:#c0291e}
        .dash-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:1rem;align-items:start;margin-top:1rem}
        .dash-col,.dash-side{display:flex;flex-direction:column;gap:1rem;min-width:0}
        @media (max-width:1100px){.dash-grid{grid-template-columns:minmax(0,1fr)}}
        .panel{background:#fff;border:1px solid #e8eaf0;border-radius:14px;padding:1.2rem 1.3rem;box-shadow:0 1px 2px #1f1a5c0f;min-width:0}
        .panel-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:.2rem}
        .panel-title{margin:0;font-size:1.02rem;color:#1f2933}
        .panel-sub{font-size:.82rem;color:#7b8794;margin-top:.1rem}
        .count-chip{display:inline-block;white-space:nowrap;font-size:.78rem;font-weight:600;border-radius:999px;padding:.2rem .7rem;background:#e4e7eb;color:#52606d}
        .count-chip.warn{background:#fff1d6;color:#9a5b00}
        .count-chip.good{background:#e1f5ea;color:#0b7a4b}
        .count-chip.info{background:#e1efff;color:#1d4e9e}
        .legend{font-size:.8rem;color:#7b8794;white-space:nowrap}
        .legend-dot{display:inline-block;width:.65rem;height:.65rem;border-radius:3px;margin:0 .25rem 0 .7rem;vertical-align:baseline}
        .dbars{display:flex;flex-direction:column;gap:.55rem;margin-top:1rem}
        .dbar{display:grid;grid-template-columns:5.2rem minmax(0,1fr) 2.2rem;align-items:center;gap:.7rem;text-decoration:none;color:inherit;border-radius:8px;padding:.15rem .3rem;margin:0 -.3rem}
        a.dbar:hover{background:#f6f5ff}
        .dbar-label{font-size:.86rem;color:#3e4c59}
        .dbar-track{height:10px;border-radius:5px;background:#eef0f3;overflow:hidden}
        .dbar-fill{display:block;height:100%;border-radius:5px;min-width:4px}
        .dbar-n{font-size:.88rem;font-weight:600;text-align:right}
        .feed-row{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:.75rem 0;border-bottom:1px solid #f0f0f3;color:inherit;text-decoration:none}
        .feed-row:last-of-type{border-bottom:0}
        a.feed-link:hover{background:#faf9ff}
        .feed-main{min-width:0;overflow-wrap:anywhere}
        /* While a partial refresh is in flight (js/partial.js) */
        html.ajax-busy,html.ajax-busy *{cursor:progress}
        html.ajax-busy [data-region]{opacity:.7;transition:opacity .15s}
    </style>
@if(file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
    <script src="{{ asset('js/grid-size.js') }}"></script>
    @vite(['resources/js/app.js'])
@endif
@stack('assets')
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
        <div class="hright">
            @include('partials.notification-bell')
            <form method="POST" action="{{ route('logout') }}" class="who" data-no-ajax>
                @csrf
                {{ auth()->user()->full_name }}<span class="badge">{{ auth()->user()->role }}</span>
                <button type="submit">Log out</button>
            </form>
        </div>
    </header>
@endauth
<main>@yield('content')</main>
<footer class="site">
    <span>cRc &middot; Customer Relationship Console</span>
    <span>&copy; {{ date('Y') }} Client Support Desk. For internal use only.</span>
</footer>
<script src="{{ asset('js/partial.js') }}" defer></script>
@auth
    <script src="{{ asset('js/notifications.js') }}" defer></script>
@endauth
</body>
</html>
