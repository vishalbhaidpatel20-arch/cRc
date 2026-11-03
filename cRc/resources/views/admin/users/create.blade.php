@extends('layouts.app')
@section('title', 'New agent')

@section('content')
<div class="card f" style="max-width:520px">
    <h2 style="margin-top:0">New agent</h2>
    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        @include('admin.users._fields')

        <button type="submit" class="btn">Create agent</button>
        <a href="{{ route('admin.users.index') }}" style="margin-left:1rem">Cancel</a>
    </form>
</div>
@endsection
