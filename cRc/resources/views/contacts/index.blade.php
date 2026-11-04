@extends('layouts.app')
@section('title', 'Contacts')
@php($isAdmin = auth()->user()->isAdmin())
@php($highlight = session('highlight'))

@push('assets')
@include('partials.grid-assets')
@endpush

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Contacts</h2>
        @if($isAdmin)<a class="btn" id="new-contact-link" data-modal="contact-modal" href="{{ route('contacts.create') }}">+ New contact</a>@endif
    </div>
    @include('partials.flash')

    <form method="GET" class="filters">
        <input name="q" value="{{ request('q') }}" placeholder="Search" title="Search name, email, phone, position, company" autocomplete="off">
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
        <noscript><button class="btn" type="submit">Search</button></noscript>
    </form>

<div data-region="results">
    <x-grid-skeleton :total="$contacts->count()" />
    <table class="list" data-grid>
        <tr><th>Name</th><th>Company</th><th>Position</th><th>Phone</th><th>Email</th><th>Last contacted</th><th>Status</th></tr>
        @forelse($contacts as $ct)
            <tr @class(['row-new' => $highlight === $ct->contact_id]) id="contact-{{ $ct->contact_id }}">
                <td><a href="{{ route('contacts.show', $ct) }}">{{ $ct->full_name }}</a> @if($ct->is_primary)<span class="tag">Primary</span>@endif @if($highlight === $ct->contact_id)<span class="pill green">New</span>@endif</td>
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
    {{ $contacts->links('partials.pager') }}
</div>
</div>

@if($isAdmin)
    <x-modal id="contact-modal" marker="contact" title="New contact" :action="route('contacts.store')" submit="Create contact" wide
             :open="request()->boolean('new')">
        @include('contacts._fields', ['contact' => $newContact, 'companies' => $formCompanies])
    </x-modal>
@endif
@endsection
