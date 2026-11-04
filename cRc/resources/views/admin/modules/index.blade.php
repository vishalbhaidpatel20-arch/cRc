@extends('layouts.app')
@section('title', 'Modules')

@push('assets')
@include('partials.grid-assets')
@endpush

@section('content')
<div class="card">
    <h2 style="margin:0">Product modules</h2>
    <p class="muted" style="margin:.3rem 0 0">The modules your clients use. They appear as tags on contacts. Names are entered here, never fixed in the app.</p>
    @include('partials.flash')

<div data-region="results">
    <form method="POST" action="{{ route('admin.modules.store') }}" class="filters">
        @csrf
        <input name="name" value="{{ old('name') }}" placeholder="New module name" required maxlength="100" style="flex:1;min-width:180px">
        <input name="description" value="{{ old('description') }}" placeholder="Description (optional)" maxlength="255" style="flex:2;min-width:200px">
        <button class="btn" type="submit">Add module</button>
    </form>
    @error('name')<div class="error">{{ $message }}</div>@enderror

    <x-grid-skeleton :total="$modules->count()" />
    <table class="list" data-grid>
        <tr><th data-flex="2">Name</th><th data-flex="3">Description</th><th>Contacts</th><th data-nosort></th></tr>
        @forelse($modules as $m)
            {{-- The inputs belong to the row's form through form="..." (a grid cell cannot hold a form that spans cells). --}}
            <tr id="module-row-{{ $m->module_id }}">
                <td><input form="module-form-{{ $m->module_id }}" name="name" value="{{ $m->name }}" required maxlength="100" style="padding:.4rem;border:1px solid #cbd2d9;border-radius:6px;width:100%"></td>
                <td><input form="module-form-{{ $m->module_id }}" name="description" value="{{ $m->description }}" maxlength="255" style="padding:.4rem;border:1px solid #cbd2d9;border-radius:6px;width:100%"></td>
                <td>{{ $m->contacts_count }}</td>
                <td><button form="module-form-{{ $m->module_id }}" class="btn outline" type="submit">Save</button></td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No modules yet. Add the first one above.</td></tr>
        @endforelse
    </table>
    @foreach($modules as $m)
        <form id="module-form-{{ $m->module_id }}" method="POST" action="{{ route('admin.modules.update', $m) }}">@csrf @method('PUT')</form>
    @endforeach
</div>
</div>
@endsection
