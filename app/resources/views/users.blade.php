@extends('layout')
@section('title','User Management')
@section('subtitle','Individual accounts for your organization.')
@section('actions')
<a class="button" href="/users/new"><svg><use href="#plus"/></svg>Add User</a>
@endsection
@section('content')
<section class="panel"><div class="panel-header"><h2>Organisation accounts</h2><span class="subtle">{{ $users->count() }} users</span></div><div class="table-wrap"><table><thead><tr><th>User</th><th>Role</th><th>Department</th><th>Location</th><th>Status</th></tr></thead><tbody>
@foreach($users as $user)<tr><td><strong>@if(\App\Services\Access::canManageUser(auth()->user(),$user))<a class="link" href="/users/{{ $user->id }}/edit">{{ $user->name }}</a>@else{{ $user->name }}@endif</strong><small>{{ $user->email }}</small></td><td>{{ $user->role_label }}</td><td>{{ $user->department }}</td><td>{{ $user->location }}</td><td><span class="badge {{ $user->active?'':'rejected' }}">{{ $user->active?'Active':'Disabled' }}</span></td></tr>@endforeach
</tbody></table></div></section>
@endsection
