@extends('layouts.app')
@section('title', $company->name)

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">{{ $company->name }}
            <span class="tag {{ $company->is_active ? '' : 'off' }}">{{ $company->is_active ? 'Active' : 'Inactive' }}</span></h2>
        <span>
            @if(auth()->user()->isAdmin())
                <a class="btn light" href="{{ route('companies.edit', $company) }}">Edit</a>
                <form method="POST" action="{{ route('companies.active', $company) }}" style="display:inline">
                    @csrf @method('PATCH')
                    <button class="btn light" type="submit">{{ $company->is_active ? 'Deactivate' : 'Activate' }}</button>
                </form>
            @endif
        </span>
    </div>
    @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <p class="muted">{{ $company->company_code }} · {{ $company->industry ?: 'No industry' }} · {{ $company->city ?: 'No city' }}<br>
        {{ $company->email ?: 'No email' }} · {{ $company->phone ?: 'No phone' }}</p>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1.5rem">
        <h3 style="margin:0">Contacts</h3>
        @if(auth()->user()->isAdmin())<a class="btn" href="{{ route('contacts.create', ['company_id' => $company->company_id]) }}">+ Add contact</a>@endif
    </div>
    <table class="list">
        <tr><th>Name</th><th>Position</th><th>Phone</th><th>Email</th><th>Status</th></tr>
        @forelse($contacts as $ct)
            <tr>
                <td><a href="{{ route('contacts.show', $ct) }}">{{ $ct->full_name }}</a> @if($ct->is_primary)<span class="tag">Primary</span>@endif</td>
                <td>{{ $ct->position }}</td>
                <td>{{ $ct->phone }}</td>
                <td>{{ $ct->email }}</td>
                <td><span class="tag {{ $ct->is_active ? '' : 'off' }}">{{ $ct->is_active ? 'Active' : 'Inactive' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No contacts yet.</td></tr>
        @endforelse
    </table>
</div>
@endsection
