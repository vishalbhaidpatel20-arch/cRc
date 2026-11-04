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
    <x-grid-skeleton :total="$users->count()" />
    <table class="list" data-grid>
        <tr><th>Name</th><th>Email</th><th>Role</th><th>Companies</th><th>Status</th></tr>
        @forelse($users as $u)
            <tr @class(['row-new' => $highlight === $u->user_id]) id="user-{{ $u->user_id }}">
                <td>{{ $u->full_name }}@if($highlight === $u->user_id) <span class="pill green">New</span>@endif</td>
                <td>{{ $u->email }}</td>
                <td>{{ $u->role }}</td>
                <td>{{ $u->all_companies ? 'All' : 'Selected' }}</td>
                <td>{{ $u->is_active ? 'Active' : 'Inactive' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No users found.</td></tr>
        @endforelse
    </table>
    {{ $users->links('partials.pager') }}
</div>
</div>

<x-modal id="user-modal" marker="user" title="New agent" :action="route('admin.users.store')" submit="Create agent"
         note="You set a temporary password and pass it on yourself. Agents cannot register on their own." :open="request()->boolean('new')">
    @include('admin.users._fields')
</x-modal>
@endsection
