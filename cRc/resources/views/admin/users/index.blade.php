@extends('layouts.app')
@section('title', 'Users')

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Users</h2>
        <a href="{{ route('admin.users.create') }}">+ New agent</a>
    </div>
    @if(session('status'))<p style="color:#067647">{{ session('status') }}</p>@endif
    <table style="width:100%;border-collapse:collapse;margin-top:1rem">
        <tr style="text-align:left;border-bottom:1px solid #cbd2d9"><th>Name</th><th>Email</th><th>Role</th><th>Companies</th><th>Status</th></tr>
        @foreach($users as $u)
            <tr style="border-bottom:1px solid #eee">
                <td>{{ $u->full_name }}</td>
                <td>{{ $u->email }}</td>
                <td>{{ $u->role }}</td>
                <td>{{ $u->all_companies ? 'All' : 'Selected' }}</td>
                <td>{{ $u->is_active ? 'Active' : 'Inactive' }}</td>
            </tr>
        @endforeach
    </table>
</div>
@endsection
