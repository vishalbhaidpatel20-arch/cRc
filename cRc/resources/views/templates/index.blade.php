@extends('layouts.app')
@php($isAdmin = auth()->user()->isAdmin())
@section('title', 'Templates')

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Message templates</h2>
        @if($isAdmin)<a class="btn" href="{{ route('templates.create') }}">+ New template</a>@endif
    </div>
    @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
    <p class="muted">Pick a template while logging a conversation on a ticket or contact page. Placeholders:
        @foreach(\App\Models\MessageTemplate::PLACEHOLDERS as $key => $desc)<code title="{{ $desc }}">{{ \App\Models\MessageTemplate::token($key) }}</code> @endforeach</p>

    <form method="GET" class="filters">
        <select name="channel">
            <option value="">Any channel</option>
            @foreach(\App\Models\MessageTemplate::CHANNELS as $ch)<option value="{{ $ch }}" @selected(request('channel') === $ch)>{{ $ch }}</option>@endforeach
        </select>
        <select name="category">
            <option value="">Any category</option>
            @foreach(\App\Models\MessageTemplate::CATEGORIES as $c)<option value="{{ $c }}" @selected(request('category') === $c)>{{ str_replace('_', ' ', $c) }}</option>@endforeach
        </select>
        @if($isAdmin)
            <select name="status">
                <option value="active" @selected($status === 'active')>Active</option>
                <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                <option value="all" @selected($status === 'all')>All</option>
            </select>
        @endif
        <button class="btn" type="submit">Filter</button>
    </form>

    <table class="list">
        <tr><th>Name</th><th>Category</th><th>Channel</th><th>Message</th>@if($isAdmin)<th>Status</th><th></th>@endif</tr>
        @forelse($templates as $t)
            <tr>
                <td>{{ $t->name }}</td>
                <td>{{ str_replace('_', ' ', $t->category) }}</td>
                <td>{{ $t->channel }}</td>
                <td>@if($t->subject)<strong>{{ $t->subject }}</strong><br>@endif<span style="white-space:pre-wrap">{{ $t->body }}</span></td>
                @if($isAdmin)
                    <td><span class="tag {{ $t->is_active ? '' : 'off' }}">{{ $t->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td style="white-space:nowrap">
                        <a href="{{ route('templates.edit', $t) }}">Edit</a>
                        <form method="POST" action="{{ route('templates.active', $t) }}" style="display:inline">
                            @csrf @method('PATCH')
                            <button class="btn light" type="submit">{{ $t->is_active ? 'Deactivate' : 'Activate' }}</button>
                        </form>
                    </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="{{ $isAdmin ? 6 : 4 }}" class="muted">No templates found.</td></tr>
        @endforelse
    </table>
</div>
@endsection
