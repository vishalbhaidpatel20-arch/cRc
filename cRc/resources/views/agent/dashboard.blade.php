@extends('layouts.app')
@section('title', 'Agent dashboard')

@section('content')
<div class="card">
    <h2 style="margin-top:0">Agent dashboard</h2>
    <p>Welcome, {{ auth()->user()->full_name }}. Your tickets, conversations and follow-ups will appear here (coming in the next milestones).</p>
</div>
@endsection
