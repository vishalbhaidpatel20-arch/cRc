@extends('layouts.app')
@section('title', 'Contacts')

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Contacts</h2>
        @if(auth()->user()->isAdmin())<a class="btn" href="{{ route('contacts.create') }}">+ New contact</a>@endif
    </div>
    @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <form method="GET" class="filters">
        <input name="q" value="{{ request('q') }}" placeholder="Search name, email, phone, position, company" style="flex:1;min-width:220px">
        <select name="company_id">
            <option value="">All companies</option>
            @foreach($companies as $c)
                <option value="{{ $c->company_id }}" @selected(request('company_id') == $c->company_id)>{{ $c->name }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="active" @selected($status === 'active')>Active</option>
            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
            <option value="all" @selected($status === 'all')>All</option>
        </select>
        <button class="btn" type="submit">Search</button>
    </form>

    <table class="list">
        <tr><th>Name</th><th>Company</th><th>Position</th><th>Phone</th><th>Email</th><th>Last contacted</th><th>Status</th></tr>
        @forelse($contacts as $ct)
            <tr>
                <td><a href="{{ route('contacts.show', $ct) }}">{{ $ct->full_name }}</a> @if($ct->is_primary)<span class="tag">Primary</span>@endif</td>
                <td><a href="{{ route('companies.show', $ct->company) }}">{{ $ct->company->name }}</a></td>
                <td>{{ $ct->position }}</td>
                <td>{{ $ct->phone }}</td>
                <td>{{ $ct->email }}</td>
                <td class="muted">{{ $ct->last_contacted_at?->format('d M Y') ?? 'Never' }}</td>
                <td><span class="tag {{ $ct->is_active ? '' : 'off' }}">{{ $ct->is_active ? 'Active' : 'Inactive' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No contacts found.</td></tr>
        @endforelse
    </table>
    <div style="margin-top:1rem">{{ $contacts->links('partials.pager') }}</div>
</div>
@endsection
