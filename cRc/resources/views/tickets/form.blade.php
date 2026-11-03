@extends('layouts.app')
@php($editing = $ticket->exists)
@php($isAdmin = auth()->user()->isAdmin())
@section('title', $editing ? 'Edit ticket' : 'New ticket')

@section('content')
<div class="card f" style="max-width:600px">
    <h2 style="margin-top:0">{{ $editing ? 'Edit '.$ticket->ticket_no : 'New ticket' }}</h2>
    <form method="POST" action="{{ $editing ? route('tickets.update', $ticket) : route('tickets.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif

        @include('tickets._fields')

        <button class="btn" type="submit">{{ $editing ? 'Save changes' : 'Create ticket' }}</button>
        <a href="{{ $editing ? route('tickets.show', $ticket) : route('tickets.index') }}" style="margin-left:1rem">Cancel</a>
    </form>
</div>
@endsection
