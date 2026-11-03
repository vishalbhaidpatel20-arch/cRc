@extends('layouts.app')
@php($editing = $template->exists)
@section('title', $editing ? 'Edit template' : 'New template')

@section('content')
<div class="card f" style="max-width:640px">
    <h2 style="margin-top:0">{{ $editing ? 'Edit template' : 'New template' }}</h2>
    <p class="muted">Placeholders you can use:
        @foreach(\App\Models\MessageTemplate::PLACEHOLDERS as $key => $desc)<code>{{ \App\Models\MessageTemplate::token($key) }}</code> ({{ $desc }})@if(! $loop->last), @endif @endforeach</p>
    <form method="POST" action="{{ $editing ? route('templates.update', $template) : route('templates.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif

        @include('templates._fields')

        <button class="btn" type="submit">{{ $editing ? 'Save changes' : 'Create template' }}</button>
        <a href="{{ route('templates.index') }}" style="margin-left:1rem">Cancel</a>
    </form>
</div>
@endsection
