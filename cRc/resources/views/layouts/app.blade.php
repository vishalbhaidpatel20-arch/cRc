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
    </style>
</head>
<body>
@auth
    <header>
        <strong>Client Support</strong>
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
