@extends('layouts.app')
@section('title', 'Conversations')

@section('content')
<div class="card">
    <h2 style="margin:0">Conversations</h2>
    <p class="muted" style="margin:.3rem 0 0">The daily log of every conversation with client contacts. Log new ones from a ticket or a contact page.</p>

    <form method="GET" class="filters">
        <input name="q" value="{{ request('q') }}" placeholder="Search summary or contact name" style="flex:1;min-width:200px">
        <select name="channel">
            <option value="">Any channel</option>
            @foreach(\App\Models\Interaction::CHANNELS as $ch)<option value="{{ $ch }}" @selected(request('channel') === $ch)>{{ $ch }}</option>@endforeach
        </select>
        <select name="topic">
            <option value="">Any topic</option>
            @foreach(\App\Models\Interaction::TOPICS as $tp)<option value="{{ $tp }}" @selected(request('topic') === $tp)>{{ str_replace('_', ' ', $tp) }}</option>@endforeach
        </select>
        @if(auth()->user()->isAdmin())
            <select name="user_id">
                <option value="">Any agent</option>
                @foreach($users as $u)<option value="{{ $u->user_id }}" @selected(request('user_id') == $u->user_id)>{{ $u->full_name }}</option>@endforeach
            </select>
        @endif
        <label style="align-self:center"><input type="checkbox" name="mine" value="1" @checked(request()->boolean('mine'))> Only mine</label>
        <input type="date" name="from" value="{{ request('from') }}" title="From">
        <input type="date" name="to" value="{{ request('to') }}" title="To">
        <button class="btn" type="submit">Filter</button>
    </form>

    <div style="margin-top:1rem">
        @include('partials.conversation-list', ['interactions' => $interactions, 'showContact' => true, 'showTicket' => true])
    </div>
    <div style="margin-top:1rem">{{ $interactions->links('partials.pager') }}</div>
</div>
@endsection
