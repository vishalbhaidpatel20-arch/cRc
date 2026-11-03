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

        <label>Name *</label>
        <input name="name" value="{{ old('name', $template->name) }}" required>
        @error('name')<div class="error">{{ $message }}</div>@enderror

        <label>Channel *</label>
        <select name="channel">
            @foreach(\App\Models\MessageTemplate::CHANNELS as $ch)<option value="{{ $ch }}" @selected(old('channel', $template->channel) === $ch)>{{ $ch }}</option>@endforeach
        </select>

        <label>Category</label>
        <select name="category">
            @foreach(\App\Models\MessageTemplate::CATEGORIES as $c)<option value="{{ $c }}" @selected(old('category', $template->category) === $c)>{{ str_replace('_', ' ', $c) }}</option>@endforeach
        </select>

        <label>Subject (email only)</label>
        <input name="subject" value="{{ old('subject', $template->subject) }}">
        @error('subject')<div class="error">{{ $message }}</div>@enderror

        <label>Message *</label>
        <textarea name="body" rows="8" required style="width:100%;padding:.5rem;border:1px solid #cbd2d9;border-radius:6px;margin:.3rem 0 .6rem">{{ old('body', $template->body) }}</textarea>
        @error('body')<div class="error">{{ $message }}</div>@enderror

        <button class="btn" type="submit">{{ $editing ? 'Save changes' : 'Create template' }}</button>
        <a href="{{ route('templates.index') }}" style="margin-left:1rem">Cancel</a>
    </form>
</div>
@endsection
