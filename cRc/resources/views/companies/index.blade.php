@extends('layouts.app')
@section('title', 'Companies')

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Companies</h2>
        @if(auth()->user()->isAdmin())<a class="btn" href="{{ route('companies.create') }}">+ New company</a>@endif
    </div>
    @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <form method="GET" class="filters">
        <input name="q" value="{{ request('q') }}" placeholder="Search name, code, city, industry" style="flex:1;min-width:220px">
        <select name="status">
            <option value="active" @selected($status === 'active')>Active</option>
            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
            <option value="all" @selected($status === 'all')>All</option>
        </select>
        <button class="btn" type="submit">Search</button>
    </form>

    <table class="list">
        <tr><th>Code</th><th>Name</th><th>Industry</th><th>City</th><th>Contacts</th><th>Status</th></tr>
        @forelse($companies as $c)
            <tr>
                <td>{{ $c->company_code }}</td>
                <td><a href="{{ route('companies.show', $c) }}">{{ $c->name }}</a></td>
                <td>{{ $c->industry }}</td>
                <td>{{ $c->city }}</td>
                <td>{{ $c->contacts_count }}</td>
                <td><span class="tag {{ $c->is_active ? '' : 'off' }}">{{ $c->is_active ? 'Active' : 'Inactive' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No companies found.</td></tr>
        @endforelse
    </table>
    <div style="margin-top:1rem">{{ $companies->links('partials.pager') }}</div>
</div>
@endsection
