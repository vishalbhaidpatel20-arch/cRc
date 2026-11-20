@extends('layouts.app')
@section('title', 'Users')
@php($highlight = session('highlight'))

@push('assets')
@include('partials.grid-assets')
@endpush

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Users</h2>
        <a class="btn" id="new-user-link" data-modal="user-modal" href="{{ route('admin.users.create') }}">+ New agent</a>
    </div>
    @include('partials.flash')

    <form method="GET" class="filters">
        <input name="q" value="{{ request('q') }}" placeholder="Search" title="Search name, email, phone or role" autocomplete="off">
        <noscript><button class="btn" type="submit">Search</button></noscript>
    </form>

<div data-region="results">
    {{-- One delete form per agent, outside the grid (the row's button points at it with form="..."). Admins are never deletable. --}}
    @foreach($users as $u)
        @if($u->isAgent())
            <form id="delete-user-{{ $u->user_id }}" method="POST" action="{{ route('admin.users.destroy', $u) }}"
                  onsubmit="return confirm('Delete agent {{ addslashes($u->full_name) }}? They can no longer sign in and their open tickets go back to the unassigned queue. You can restore them later from Deleted agents.')">
                @csrf
                @method('DELETE')
            </form>
        @endif
    @endforeach
    <x-grid-skeleton :total="$users->count()" />
    <table class="list" data-grid>
        <tr><th>Name</th><th>Email</th><th>Role</th><th>Companies</th><th>Status</th><th data-nosort>Actions</th></tr>
        @forelse($users as $u)
            <tr @class(['row-new' => $highlight === $u->user_id]) id="user-{{ $u->user_id }}">
                <td>{{ $u->full_name }}@if($highlight === $u->user_id) <span class="pill green">New</span>@endif</td>
                <td>{{ $u->email }}</td>
                <td><span class="pill {{ $u->isAdmin() ? 'customization' : 'query' }}">{{ ucfirst($u->role) }}</span></td>
                <td>{{ $u->all_companies ? 'All' : 'Selected' }}</td>
                <td><span class="pill {{ $u->is_active ? 'green' : 'late' }}">{{ $u->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td>@if($u->isAgent())<button class="btn" type="submit" form="delete-user-{{ $u->user_id }}" style="background:#b42318;padding:.3rem .7rem">Delete</button>@endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No users found.</td></tr>
        @endforelse
    </table>
    {{ $users->links('partials.pager') }}
</div>

{{-- Soft-deleted agents (admins only: this whole controller is behind role:admin). Always-present area so a delete or a
     restore refreshes both lists in place. --}}
<div data-region="deleted" id="deleted-agents">
    <h3 style="margin:1.6rem 0 .3rem">Deleted agents</h3>
    <p class="muted" style="margin:0 0 .6rem">They cannot sign in. Restore gives back the account; reassign their old tickets from the unassigned queue.</p>
    @php($tz = \App\Support\UserTime::zone(request()))
    @foreach($deleted as $d)
        <form id="restore-user-{{ $d->user_id }}" method="POST" action="{{ route('admin.users.restore', $d) }}"
              onsubmit="return confirm('Restore agent {{ addslashes($d->full_name) }}? They can sign in again.')">
            @csrf
            @method('PATCH')
        </form>
    @endforeach
    <x-grid-skeleton :total="$deleted->count()" />
    <table class="list" data-grid>
        <tr><th>Name</th><th>Email</th><th>Deleted on</th><th data-nosort>Actions</th></tr>
        @forelse($deleted as $d)
            <tr id="deleted-user-{{ $d->user_id }}">
                <td>{{ $d->full_name }}</td>
                <td>{{ $d->email }}</td>
                <td>{{ \App\Support\UserTime::local($d->deleted_at, $tz)->format('d M Y, H:i') }}</td>
                <td><button class="btn" type="submit" form="restore-user-{{ $d->user_id }}" style="padding:.3rem .7rem">Restore</button></td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No deleted agents.</td></tr>
        @endforelse
    </table>
</div>
</div>

<x-modal id="user-modal" marker="user" title="New agent" :action="route('admin.users.store')" submit="Create agent"
         note="You set a temporary password and pass it on yourself. Agents cannot register on their own." :open="request()->boolean('new')">
    @include('admin.users._fields')
</x-modal>
@endsection
