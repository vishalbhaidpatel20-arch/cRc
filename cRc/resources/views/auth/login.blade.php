@extends('layouts.app')
@section('title', 'Log in')

@push('assets')
<style>
    /* Login page: full-bleed split screen (brand panel + form). */
    main:has(.login-shell){max-width:none;margin:0;padding:0}
    .login-shell{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,1fr);min-height:calc(100vh - 41px)}

    .login-brand{position:relative;overflow:hidden;color:#fff;padding:3.5rem 3.5rem 3rem;display:flex;flex-direction:column;justify-content:space-between;
        background:linear-gradient(145deg,#1F1A5C 0%,#352b9e 52%,#5B4BDB 100%)}
    .login-brand::before,.login-brand::after{content:"";position:absolute;border-radius:50%;pointer-events:none}
    .login-brand::before{width:520px;height:520px;right:-190px;top:-170px;background:radial-gradient(circle,#ffffff26 0%,#ffffff00 70%)}
    .login-brand::after{width:420px;height:420px;left:-150px;bottom:-160px;background:radial-gradient(circle,#f0a23b33 0%,#f0a23b00 70%)}
    .login-brand > *{position:relative;z-index:1}
    .login-logo{display:flex;align-items:center;gap:.85rem}
    .login-logo-tile{width:54px;height:54px;border-radius:15px;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px #0003}
    .login-logo-tile img{width:42px;height:42px;display:block}
    .login-logo-text{font-weight:600;letter-spacing:.01em;font-size:1.02rem;line-height:1.25}
    .login-logo-text small{display:block;font-weight:400;opacity:.75;font-size:.8rem}
    .login-pitch h1{margin:0 0 1rem;font-size:2.35rem;line-height:1.12;letter-spacing:-.025em;font-weight:700}
    .login-pitch p{margin:0 0 2rem;font-size:1.05rem;line-height:1.55;color:#dcd8ff;max-width:30rem}
    .login-points{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:1rem;max-width:30rem}
    .login-points li{display:flex;align-items:flex-start;gap:.9rem}
    .login-points .ico{flex:0 0 40px;height:40px;border-radius:12px;background:#ffffff1f;border:1px solid #ffffff2e;display:flex;align-items:center;justify-content:center}
    .login-points strong{display:block;font-size:.98rem}
    .login-points span.d{display:block;font-size:.88rem;color:#cfcaff;margin-top:.1rem}

    .login-form-side{display:flex;align-items:center;justify-content:center;padding:2.5rem 1.5rem;background:#fff}
    .login-form-wrap{width:100%;max-width:400px}
    .login-mobile-brand{display:none;align-items:center;gap:.7rem;margin-bottom:1.6rem}
    .login-mobile-brand .login-logo-tile{width:46px;height:46px;border-radius:13px;background:#f3f0ff;box-shadow:none}
    .login-mobile-brand .login-logo-tile img{width:34px;height:34px}
    .login-form-wrap h2{margin:0;font-size:1.85rem;letter-spacing:-.02em;color:#1F1A5C}
    .login-form-wrap .lead{margin:.4rem 0 1.8rem;color:#6b7785}
    .lg-label{display:block;font-size:.85rem;font-weight:600;color:#3e4c59;margin:0 0 .4rem}
    .lg-field{position:relative;margin-bottom:1.1rem}
    .lg-field .lead-ico{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);color:#9aa5b1;pointer-events:none;display:flex}
    .lg-field input{width:100%;height:48px;padding:0 2.8rem 0 2.7rem;font:inherit;font-size:.95rem;border:1px solid #d5d8dd;border-radius:10px;background:#fff;color:#1f2933;transition:border-color .15s,box-shadow .15s}
    .lg-field input::placeholder{color:#a3acb7}
    .lg-field input:focus{outline:0;border-color:#5B4BDB;box-shadow:0 0 0 4px #5b4bdb26}
    .lg-field.invalid input{border-color:#d92d20;box-shadow:0 0 0 4px #d92d2014}
    .lg-field .toggle{position:absolute;right:.35rem;top:50%;transform:translateY(-50%);width:38px;height:38px;border:0;background:none;border-radius:8px;color:#7b8794;cursor:pointer;display:flex;align-items:center;justify-content:center}
    .lg-field .toggle:hover{background:#f3f0ff;color:#5B4BDB}
    .lg-error{display:flex;align-items:flex-start;gap:.5rem;background:#fef3f2;border:1px solid #fecdca;color:#b42318;border-radius:10px;padding:.65rem .85rem;font-size:.88rem;margin:-.3rem 0 1.1rem}
    .lg-submit{width:100%;height:50px;margin-top:.4rem;border:0;border-radius:10px;cursor:pointer;font:inherit;font-weight:600;font-size:1rem;color:#fff;
        background:linear-gradient(135deg,#1F1A5C,#5B4BDB);box-shadow:0 8px 20px #5b4bdb40;transition:transform .12s,box-shadow .12s,filter .12s}
    .lg-submit:hover{transform:translateY(-1px);box-shadow:0 12px 26px #5b4bdb55;filter:brightness(1.06)}
    .lg-submit:active{transform:translateY(0)}
    .lg-submit:focus-visible{outline:3px solid #5b4bdb55;outline-offset:2px}
    .lg-note{display:flex;align-items:flex-start;gap:.55rem;margin-top:1.6rem;font-size:.82rem;line-height:1.45;color:#7b8794}
    .lg-note svg{flex:0 0 auto;margin-top:2px}
    /* Small screens: just the form. Last, so it wins over the rules above. */
    @media (max-width:900px){.login-shell{grid-template-columns:minmax(0,1fr)}.login-brand{display:none}.login-mobile-brand{display:flex!important}}
</style>
@endpush

@section('content')
<div class="login-shell">
    <aside class="login-brand" aria-label="About cRc">
        <div class="login-logo">
            <span class="login-logo-tile"><img src="{{ asset('images/crc-badge.svg') }}" alt=""></span>
            <span class="login-logo-text">cRc<small>Customer Relationship Console</small></span>
        </div>

        <div class="login-pitch">
            <h1>Every client conversation,<br>in one place.</h1>
            <p>Track what your clients ask, who is working on it, and what you promised to do next, without losing a thing.</p>
            <ul class="login-points">
                <li>
                    <span class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5v2"/><path d="M15 11v2"/><path d="M15 17v2"/><path d="M5 5h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4V7a2 2 0 0 1 2-2z"/></svg></span>
                    <span><strong>Tickets that always have an owner</strong><span class="d">Assign work, follow its history and see who is busy.</span></span>
                </li>
                <li>
                    <span class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-12.4 7.4L3 21l1.9-5.4A8.5 8.5 0 1 1 21 11.5z"/></svg></span>
                    <span><strong>A complete conversation log</strong><span class="d">Calls, WhatsApp, email and meetings for every contact.</span></span>
                </li>
                <li>
                    <span class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg></span>
                    <span><strong>Alerts and follow-ups</strong><span class="d">Know the moment something is assigned or completed.</span></span>
                </li>
            </ul>
        </div>

        <div></div>
    </aside>

    <section class="login-form-side">
        <div class="login-form-wrap">
            <div class="login-mobile-brand">
                <span class="login-logo-tile"><img src="{{ asset('images/crc-badge.svg') }}" alt=""></span>
                <span class="login-logo-text" style="color:#1F1A5C">cRc<small style="opacity:.7">Customer Relationship Console</small></span>
            </div>

            <h2>Welcome back</h2>
            <p class="lead">Log in to your support desk to continue.</p>

            <form method="POST" action="{{ url('/login') }}" data-no-ajax>
                @csrf

                <label class="lg-label" for="email">Email</label>
                <div class="lg-field @error('email') invalid @enderror">
                    <span class="lead-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="you@company.com" autocomplete="username" required autofocus>
                </div>
                @error('email')
                    <div class="lg-error"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 auto;margin-top:1px"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg><span>{{ $message }}</span></div>
                @enderror

                <label class="lg-label" for="password">Password</label>
                <div class="lg-field @error('password') invalid @enderror">
                    <span class="lead-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></span>
                    <input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required>
                    <button type="button" class="toggle" data-toggle-password aria-label="Show password" title="Show password">
                        <svg data-eye width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                @error('password')
                    <div class="lg-error"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 auto;margin-top:1px"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg><span>{{ $message }}</span></div>
                @enderror

                <button type="submit" class="lg-submit">Log in</button>
            </form>

            <div class="lg-note">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                Accounts are created by an administrator. There is no public sign-up.
            </div>
        </div>
    </section>
</div>

<script>
(function () {
    var btn = document.querySelector('[data-toggle-password]');
    var input = document.getElementById('password');
    if (!btn || !input) { return; }
    btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        btn.title = show ? 'Hide password' : 'Show password';
        input.focus();
    });
})();
</script>
@endsection
