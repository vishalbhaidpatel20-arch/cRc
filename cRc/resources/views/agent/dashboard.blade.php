@extends('layouts.app')
@section('title', 'Agent dashboard')

@php($colors = ['critical' => '#d94b4b', 'high' => '#f0a23b', 'medium' => '#5b8def', 'low' => '#b5bcc6'])
@php($visibleTickets = \App\Support\NotificationPresenter::visibleTicketIds($notifications))

@push('assets')
@include('partials.grid-assets')
@endpush

@section('content')
<div class="dash-hero">
    <div>
        <div class="dash-eyebrow" data-greeting>Dashboard</div>
        <script>(function (el) { var h = new Date().getHours(); el.textContent = (h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening') + ' · ' + new Date().toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' }); })(document.currentScript.previousElementSibling);</script>
        <h2>{{ \Illuminate\Support\Str::of(auth()->user()->full_name)->before(' ') }}'s dashboard</h2>
        <p class="dash-sub">Welcome, {{ auth()->user()->full_name }}. You have <strong>{{ $openCount }}</strong> open {{ Str::plural('ticket', $openCount) }}.</p>
    </div>
    <div class="dash-actions">
        <a class="btn" href="{{ route('tickets.index') }}">My tickets</a>
        <a class="btn outline" href="{{ route('tickets.index', ['new' => 1]) }}">+ New ticket</a>
    </div>
</div>

<div class="dash-stats" data-region="agent-stats">
    <x-stat icon="ticket" tone="brand" :value="$openCount" label="Open tickets" hint="assigned to you" :href="route('tickets.index')" />
    <x-stat icon="clock" :tone="$overdueCount > 0 ? 'bad' : 'good'" :value="$overdueCount" label="Overdue" :hint="$overdueCount > 0 ? 'past the due date' : 'nothing late'" :href="route('tickets.index', ['overdue' => 1])" />
    <x-stat icon="bell" :tone="$followUps->count() > 0 ? 'warn' : 'good'" :value="$followUps->count()" label="Follow-ups due" :hint="$followUps->count() > 0 ? 'today or overdue' : 'all caught up'" />
    <x-stat icon="inbox" :tone="$notifications->count() > 0 ? 'info' : 'good'" :value="$notifications->count()" label="New assignments" :hint="$notifications->count() > 0 ? 'unread' : 'nothing new'" :href="route('notifications.index')" />
</div>

<div class="dash-grid">
    <div class="dash-col">
        <div class="panel" data-region="agent-next">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">Next up</h3>
                    <div class="panel-sub">Your most urgent open tickets</div>
                </div>
                <a href="{{ route('tickets.index') }}" style="font-size:.85rem">View all</a>
            </div>
            <x-grid-skeleton :total="$topTickets->count()" :page-size="5" />
            <table class="list" data-grid data-page-size="5">
                <tr><th>No</th><th data-flex="3">Title</th><th>Company</th><th>Priority</th></tr>
                @forelse($topTickets as $t)
                    <tr>
                        <td><a href="{{ route('tickets.show', $t) }}">{{ $t->ticket_no }}</a></td>
                        <td>{{ $t->title }}</td>
                        <td class="muted">{{ $t->company->name }}</td>
                        <td><span class="tag {{ $t->priority }}">{{ $t->priority }}</span></td>
                    </tr>
                @empty
                    <tr><td class="muted">Nothing open. Nice work.</td></tr>
                @endforelse
            </table>
        </div>

        <div class="panel" data-region="agent-followups">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">Follow-ups due</h3>
                    <div class="panel-sub">Calls and messages you promised, due today or late</div>
                </div>
                @if($followUps->isNotEmpty())<span class="count-chip warn">{{ $followUps->count() }}</span>@endif
            </div>
            @forelse($followUps as $f)
                <div class="feed-row">
                    <div class="feed-main">
                        <a href="{{ route('contacts.show', $f->contact) }}">{{ $f->contact->full_name }}</a>
                        <span class="muted">{{ $f->contact->company->name }}</span>
                        @if($f->isOverdue())<span class="pill late">Overdue, was {{ $f->due_date->format('j M') }}</span>@else<span class="pill fu">Due today</span>@endif
                        <div class="muted">{{ $f->description }}</div>
                    </div>
                    <form method="POST" action="{{ route('follow-ups.done', $f) }}">
                        @csrf @method('PATCH')
                        <button class="btn outline" type="submit">Mark done</button>
                    </form>
                </div>
            @empty
                <p class="muted" style="margin:.8rem 0 0">No follow-ups due.</p>
            @endforelse
        </div>
    </div>

    <div class="dash-col">
        <div class="panel" data-region="agent-assigned">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">New assignments</h3>
                    <div class="panel-sub">Tickets an admin handed to you</div>
                </div>
                @if($notifications->isNotEmpty())<span class="count-chip info">{{ $notifications->count() }} new</span>@endif
            </div>
            @forelse($notifications as $n)
                @php($p = \App\Support\NotificationPresenter::present($n, isset($visibleTickets[$n->data['ticket_id'] ?? 0])))
                <a class="feed-row feed-link" href="{{ route('notifications.open', $n->id) }}">
                    <div class="feed-main">
                        <div>{{ $p['text'] }}</div>
                        <div class="muted" style="font-size:.82rem">
                            @if(! empty($n->data['priority']) && $p['visible'])<span class="tag {{ $n->data['priority'] }}">{{ $n->data['priority'] }}</span>@endif
                            @if(! empty($n->data['assigned_by']))assigned by {{ $n->data['assigned_by'] }}, @endif{{ $n->created_at->diffForHumans() }}
                        </div>
                    </div>
                </a>
            @empty
                <p class="muted" style="margin:.8rem 0 0">No new assignments.</p>
            @endforelse
            @if($notifications->isNotEmpty())
                <form method="POST" action="{{ route('notifications.read') }}" style="margin-top:.8rem">
                    @csrf
                    <button class="btn outline" type="submit">Mark all as read</button>
                </form>
            @endif
        </div>

        <div class="panel" data-region="agent-priority">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">My tickets by priority</h3>
                    <div class="panel-sub">{{ $openCount }} open</div>
                </div>
            </div>
            @include('partials.dash-bars', ['empty' => 'No open tickets. Nice work.', 'rows' => collect($colors)->map(fn ($c, $p) => [
                'label' => ucfirst($p), 'value' => (int) ($byPriority[$p] ?? 0), 'color' => $c, 'href' => route('tickets.index', ['priority' => $p]),
            ])->values()->all()])
        </div>
    </div>
</div>
@endsection
