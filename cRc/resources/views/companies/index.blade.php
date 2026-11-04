@extends('layouts.app')
@section('title', 'Companies')
@php($isAdmin = auth()->user()->isAdmin())
@php($highlight = session('highlight'))

@push('assets')
@include('partials.grid-assets')
@endpush

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Companies</h2>
        {{-- A plain link (works without JavaScript); the script below turns it into a pop-up. --}}
        @if($isAdmin)<a class="btn" id="new-company-link" data-modal="company-modal" href="{{ route('companies.create') }}">+ New company</a>@endif
    </div>
    @include('partials.flash')

    <form method="GET" class="filters">
        <input name="q" value="{{ request('q') }}" placeholder="Search" title="Search name, code, city, industry" autocomplete="off">
        <select name="status">
            <option value="active" @selected($status === 'active')>Active</option>
            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
            <option value="all" @selected($status === 'all')>All</option>
        </select>
        <noscript><button class="btn" type="submit">Search</button></noscript>
    </form>

<div data-region="results">
    <x-grid-skeleton :total="$companies->count()" />
    <table class="list" data-grid>
        <tr><th>Code</th><th>Name</th><th>Industry</th><th>City</th><th>Contacts</th><th>Status</th></tr>
        @forelse($companies as $c)
            <tr @class(['row-new' => $highlight === $c->company_id]) id="company-{{ $c->company_id }}">
                <td>{{ $c->company_code }}</td>
                <td><a href="{{ route('companies.show', $c) }}">{{ $c->name }}</a>@if($highlight === $c->company_id) <span class="pill green">New</span>@endif</td>
                <td>{{ $c->industry }}</td>
                <td>{{ $c->city }}</td>
                <td>{{ $c->contacts_count }}</td>
                <td><span class="tag {{ $c->is_active ? '' : 'off' }}">{{ $c->is_active ? 'Active' : 'Inactive' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No companies found.</td></tr>
        @endforelse
    </table>
    {{ $companies->links('partials.pager') }}
</div>
</div>

@if($isAdmin)
    <x-modal id="company-modal" marker="company" title="New company" :action="route('companies.store')" submit="Create company"
             note="The company code (CMP001, CMP002...) is generated automatically." :open="request()->boolean('new')">
        @include('companies._fields', ['company' => new \App\Models\Company])
    </x-modal>
@endif
@endsection
