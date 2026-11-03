@extends('layouts.app')
@php($editing = $contact->exists)
@section('title', $editing ? 'Edit contact' : 'New contact')

@section('content')
<div class="card f" style="max-width:560px">
    <h2 style="margin-top:0">{{ $editing ? 'Edit contact' : 'New contact' }}</h2>
    <form method="POST" action="{{ $editing ? route('contacts.update', $contact) : route('contacts.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif

        @include('contacts._fields')

        <button class="btn" type="submit">{{ $editing ? 'Save changes' : 'Create contact' }}</button>
        <a href="{{ $editing ? route('contacts.show', $contact) : route('contacts.index') }}" style="margin-left:1rem">Cancel</a>
    </form>
</div>
@endsection
