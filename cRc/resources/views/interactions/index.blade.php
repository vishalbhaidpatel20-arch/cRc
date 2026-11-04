@extends('layouts.app')
@section('title', 'Conversations')

@push('assets')
@include('partials.grid-assets')
@endpush

@section('content')
<div class="card">
    <h2 style="margin:0">Conversations</h2>
    <p class="muted" style="margin:.3rem 0 0">The daily log of every conversation with client contacts. Log new ones from a ticket or a contact page.</p>

    <form method="GET" class="filters">
        <input name="q" value="{{ request('q') }}" placeholder="Search" title="Search summary or contact name" autocomplete="off">
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
        <noscript><button class="btn" type="submit">Filter</button></noscript>
    </form>

<div data-region="results">
    <x-grid-skeleton :total="$interactions->count()" />
    <table class="list" data-grid>
        <tr><th>When</th><th data-flex="1.5">Channel</th><th>Contact</th><th data-flex="3">Summary</th><th>Topic</th><th>Follow-up</th><th>Ticket</th><th>Logged by</th></tr>
        @forelse($interactions as $i)
            <tr id="interaction-{{ $i->interaction_id }}">
                <td style="white-space:nowrap" title="{{ $i->interaction_at->format('d M Y H:i') }}">{{ $i->whenLabel() }}</td>
                <td><span style="display:inline-flex;align-items:center;gap:.4rem">@include('partials.channel-icon', ['channel' => $i->channel]){{ \App\Models\Interaction::channelLabel($i->channel) }}, {{ $i->direction }}</span></td>
                <td><a href="{{ route('contacts.show', $i->contact) }}">{{ $i->contact->full_name }}</a></td>
                <td style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $i->summary }}</td>
                <td><span class="pill {{ $i->topic }}">{{ ucfirst(str_replace('_', ' ', $i->topic)) }}</span></td>
                <td>
                    @if($i->followUp && $i->followUp->status === 'pending')
                        @if($i->followUp->isOverdue())<span class="pill late">Overdue</span>@else<span class="pill fu">{{ $i->followUp->dueLabel() }}</span>@endif
                    @elseif($i->followUp && $i->followUp->status === 'done')
                        <span class="pill done">Done</span>
                    @else
                        <span class="muted">-</span>
                    @endif
                </td>
                <td>
                    @if($i->ticket)<a class="pill" href="{{ route('tickets.show', $i->ticket) }}">{{ $i->ticket->ticket_no }}</a>
                    @elseif($i->ticket_id)<span class="pill">another agent's ticket</span>
                    @else<span class="muted">-</span>@endif
                </td>
                <td class="muted">{{ $i->user->full_name }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">No conversations logged yet.</td></tr>
        @endforelse
    </table>
    {{ $interactions->links('partials.pager') }}
</div>
</div>
@endsection
