@extends('layouts.app')
@php($editing = $company->exists)
@section('title', $editing ? 'Edit company' : 'New company')

@section('content')
<div class="card f" style="max-width:520px">
    <h2 style="margin-top:0">{{ $editing ? 'Edit company' : 'New company' }}</h2>
    <form method="POST" action="{{ $editing ? route('companies.update', $company) : route('companies.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif

        @if($editing)<p class="muted">Code: {{ $company->company_code }}</p>
        @else<p class="muted">The company code (CMP001, CMP002...) is generated automatically.</p>@endif

        @include('companies._fields')

        <button class="btn" type="submit">{{ $editing ? 'Save changes' : 'Create company' }}</button>
        <a href="{{ $editing ? route('companies.show', $company) : route('companies.index') }}" style="margin-left:1rem">Cancel</a>
    </form>
</div>
@endsection
