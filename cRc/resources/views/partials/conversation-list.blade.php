{{-- Conversation history rows. Expects $interactions; optional $showContact, $showTicket. --}}
@forelse($interactions as $i)
    <div class="hist-item">
        <div class="hist-icon">@include('partials.channel-icon', ['channel' => $i->channel])</div>
        <div style="flex:1;min-width:0">
            <div class="hist-top">
                <strong>{{ \App\Models\Interaction::channelLabel($i->channel) }}, {{ $i->direction }}@if($showContact ?? false) <span class="muted" style="font-weight:400">&middot; {{ $i->contact->full_name }}</span>@endif</strong>
                <span class="muted" style="white-space:nowrap" title="{{ $i->interaction_at->format('d M Y H:i') }}">{{ $i->whenLabel() }}</span>
            </div>
            <div style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $i->summary }}</div>
            <div class="hist-tags">
                <span class="pill {{ $i->topic }}">{{ ucfirst(str_replace('_', ' ', $i->topic)) }}</span>
                @if($i->followUp && $i->followUp->status === 'pending')
                    @if($i->followUp->isOverdue())
                        <span class="pill late">Follow-up overdue</span>
                    @else
                        <span class="pill fu">Follow up {{ $i->followUp->dueLabel() }}</span>
                    @endif
                @elseif($i->followUp && $i->followUp->status === 'done')
                    <span class="pill done">Follow-up done</span>
                @endif
                @if($showTicket ?? false)
                    @if($i->ticket)
                        <a class="pill" href="{{ route('tickets.show', $i->ticket) }}">{{ $i->ticket->ticket_no }}</a>
                    @elseif($i->ticket_id)
                        <span class="pill">another agent's ticket</span>
                    @endif
                @endif
                <span class="muted" style="font-size:.8rem">by {{ $i->user->full_name }}</span>
            </div>
        </div>
    </div>
@empty
    <p class="muted" style="margin:1rem 0">No conversations logged yet.</p>
@endforelse
