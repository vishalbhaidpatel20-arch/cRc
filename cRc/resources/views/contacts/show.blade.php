@extends('layouts.app')
@section('title', $contact->full_name)
@php($isAdmin = auth()->user()->isAdmin())
@php($prefers = trim(\App\Models\Interaction::channelLabel($contact->preferred_channel).($contact->profile?->preferred_time ? ', '.$contact->profile->preferred_time : '')))

@section('content')
@if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
<div class="card">
    <div class="contact-grid">
        <aside>
            <div style="display:flex;gap:.9rem;align-items:center">
                <div class="avatar">{{ $contact->initials() }}</div>
                <div style="min-width:0">
                    <div style="font-size:1.15rem;font-weight:600">{{ $contact->full_name }}</div>
                    <div class="muted">{{ $contact->position ?: 'No position' }}</div>
                </div>
            </div>
            <div class="hist-tags" style="margin-top:.7rem">
                @if($contact->is_primary)<span class="pill green">Main contact</span>@endif
                @unless($contact->is_active)<span class="pill late">Inactive</span>@endunless
            </div>
            <hr class="soft">

            <dl class="kv">
                <dt>Company</dt><dd><a href="{{ route('companies.show', $contact->company) }}">{{ $contact->company->name }}</a></dd>
                <dt>Business</dt><dd>{{ $contact->business_name ?: '-' }}</dd>
                <dt>Department</dt><dd>{{ $contact->department ?: '-' }}</dd>
                <dt>Phone</dt><dd>{{ $contact->phone ?: '-' }}</dd>
                <dt>WhatsApp</dt><dd>{{ $contact->whatsapp_no ? ($contact->whatsapp_no === $contact->phone ? 'Same as phone' : $contact->whatsapp_no) : '-' }}</dd>
                <dt>Email</dt><dd>{{ $contact->email }}</dd>
                <dt>Role</dt><dd>{{ ucfirst(str_replace('_', ' ', $contact->product_role)) }}</dd>
                <dt>Prefers</dt><dd>{{ $prefers }}</dd>
                <dt>Last contact</dt><dd>{{ $contact->last_contacted_at?->format('j M Y, H:i') ?? 'Never' }}</dd>
            </dl>

            <div class="muted" style="font-size:.9rem;margin-bottom:.4rem">Uses these modules</div>
            <div class="hist-tags" style="margin:0 0 1rem">
                @forelse($contact->modules as $m)<span class="pill teal">{{ $m->name }}</span>
                @empty<span class="muted" style="font-size:.9rem">None recorded</span>@endforelse
            </div>

            @if($isAdmin)
                <a class="btn outline block" id="edit-contact-link" data-modal="contact-edit-modal" href="{{ route('contacts.edit', $contact) }}">Edit contact</a>
                <form method="POST" action="{{ route('contacts.active', $contact) }}" style="margin-top:.5rem">
                    @csrf @method('PATCH')
                    <button class="btn outline block" type="submit">{{ $contact->is_active ? 'Deactivate' : 'Activate' }}</button>
                </form>
            @endif
            <a class="btn outline block" style="margin-top:.5rem" href="{{ route('tickets.index', ['new' => 1, 'contact_id' => $contact->contact_id]) }}">+ New ticket</a>
        </aside>

        <section>
            @if($contact->is_active)
                @include('partials.conversation-panel', [
                    'action' => route('contacts.interactions.store', $contact),
                    'anchorUrl' => route('contacts.show', $contact).'#log-conversation',
                    'ticketChoices' => $linkableTickets,
                ])
            @endif

            <div class="hist-head" id="conversations">
                <h3>Conversation history</h3>
                <span class="muted">{{ $interactionCount }} {{ \Illuminate\Support\Str::plural('conversation', $interactionCount) }}</span>
            </div>
            @include('partials.conversation-list', ['interactions' => $interactions, 'showTicket' => true])
            @if($interactionCount > $interactions->count())
                <p style="margin-top:.8rem"><a href="{{ route('interactions.index', ['contact_id' => $contact->contact_id]) }}">See all {{ $interactionCount }} conversations with {{ $contact->full_name }}</a></p>
            @endif
        </section>
    </div>
</div>

@if($isAdmin)
    <x-modal id="contact-edit-modal" marker="contact-edit" title="Edit contact" :action="route('contacts.update', $contact)" method="PUT" submit="Save changes" wide>
        @include('contacts._fields')
    </x-modal>
@endif
@endsection
