@extends('layouts.app')
@php($isAdmin = auth()->user()->isAdmin())
@php($highlight = session('highlight'))
@section('title', 'Templates')

@push('assets')
@include('partials.grid-assets')
@endpush

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Message templates</h2>
        @if($isAdmin)<a class="btn" id="new-template-link" data-modal="template-modal" href="{{ route('templates.create') }}">+ New template</a>@endif
    </div>
    @include('partials.flash')
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
        <noscript><button class="btn" type="submit">Filter</button></noscript>
    </form>

    <div data-region="results">
    <x-grid-skeleton :total="$templates->count()" />
    <table class="list" data-grid>
        <tr><th>Name</th><th>Category</th><th>Channel</th><th>Message</th>@if($isAdmin)<th>Status</th><th></th>@endif</tr>
        @forelse($templates as $t)
            <tr @class(['row-new' => $highlight === $t->template_id]) id="template-{{ $t->template_id }}">
                <td>{{ $t->name }}@if($highlight === $t->template_id) <span class="pill green">New</span>@endif</td>
                <td>{{ str_replace('_', ' ', $t->category) }}</td>
                <td>{{ $t->channel }}</td>
                <td>@if($t->subject)<strong>{{ $t->subject }}</strong><br>@endif<span style="white-space:pre-wrap">{{ $t->body }}</span></td>
                @if($isAdmin)
                    <td><span class="tag {{ $t->is_active ? '' : 'off' }}">{{ $t->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td style="white-space:nowrap">
                        <a href="{{ route('templates.edit', $t) }}" data-modal="template-edit-{{ $t->template_id }}">Edit</a>
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

    @if($isAdmin)
        @foreach($templates as $t)
            <x-modal :id="'template-edit-'.$t->template_id" :marker="'template-edit-'.$t->template_id" :title="'Edit template'" :action="route('templates.update', $t)" method="PUT" submit="Save changes" wide>
                @include('templates._fields', ['template' => $t, 'scope' => 'template-edit-'.$t->template_id])
            </x-modal>
        @endforeach
    @endif
    {{ $templates->links('partials.pager') }}
    </div>
</div>

@if($isAdmin)
    <x-modal id="template-modal" marker="template-new" title="New template" :action="route('templates.store')" submit="Create template" wide
             :note="'Placeholders: '.implode(', ', array_map(fn ($k) => \App\Models\MessageTemplate::token($k), array_keys(\App\Models\MessageTemplate::PLACEHOLDERS)))"
             :open="request()->boolean('new')">
        @include('templates._fields', ['template' => new \App\Models\MessageTemplate(['category' => 'general', 'channel' => 'email']), 'scope' => 'template-new'])
    </x-modal>
@endif
@endsection
