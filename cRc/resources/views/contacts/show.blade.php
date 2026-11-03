@extends('layouts.app')
@section('title', $contact->full_name)

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">{{ $contact->full_name }}
            <span class="tag {{ $contact->is_active ? '' : 'off' }}">{{ $contact->is_active ? 'Active' : 'Inactive' }}</span>
            @if($contact->is_primary)<span class="tag">Primary</span>@endif</h2>
        <span>
            @if(auth()->user()->isAdmin())
                <a class="btn light" href="{{ route('contacts.edit', $contact) }}">Edit</a>
                <form method="POST" action="{{ route('contacts.active', $contact) }}" style="display:inline">
                    @csrf @method('PATCH')
                    <button class="btn light" type="submit">{{ $contact->is_active ? 'Deactivate' : 'Activate' }}</button>
                </form>
            @endif
        </span>
    </div>
    @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <table class="list" style="max-width:640px">
        <tr><th>Company</th><td><a href="{{ route('companies.show', $contact->company) }}">{{ $contact->company->name }}</a></td></tr>
        <tr><th>Position</th><td>{{ $contact->position ?: '-' }}</td></tr>
        <tr><th>Department</th><td>{{ $contact->department ?: '-' }}</td></tr>
        <tr><th>Business</th><td>{{ $contact->business_name ?: '-' }}</td></tr>
        <tr><th>Email</th><td>{{ $contact->email }}</td></tr>
        <tr><th>Phone</th><td>{{ $contact->phone ?: '-' }}</td></tr>
        <tr><th>WhatsApp</th><td>{{ $contact->whatsapp_no ?: '-' }}</td></tr>
        <tr><th>Product role</th><td>{{ str_replace('_', ' ', $contact->product_role) }}</td></tr>
        <tr><th>Preferred channel</th><td>{{ $contact->preferred_channel }}</td></tr>
        <tr><th>Last contacted</th><td>{{ $contact->last_contacted_at?->format('d M Y H:i') ?? 'Never' }}</td></tr>
    </table>
</div>
@endsection
