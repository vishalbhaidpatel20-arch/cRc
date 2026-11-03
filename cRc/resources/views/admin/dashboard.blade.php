@extends('layouts.app')
@section('title', 'Admin dashboard')

@section('content')
<div class="card">
    <h2 style="margin-top:0">Admin dashboard</h2>
    <p>Welcome, {{ auth()->user()->full_name }}. You can manage users, companies and product modules here (coming in the next milestones).</p>
    <p><a href="{{ route('admin.users.index') }}">Manage agents</a></p>
</div>
@endsection
