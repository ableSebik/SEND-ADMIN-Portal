@extends('layout')
@section('title','Account')
@section('subtitle','Update your password and revoke other signed-in sessions.')
@section('content')
<section class="panel"><div class="panel-header"><h2>Change password</h2></div>
<form class="panel-body stack" method="post" action="/account/password">@csrf
@foreach(['current_password'=>'Current password','password'=>'New password (8+ characters)','password_confirmation'=>'Confirm new password'] as $key=>$label)
<div><label for="{{ $key }}">{{ $label }}</label><input id="{{ $key }}" type="password" name="{{ $key }}" required @if($key!=='current_password')minlength="8" autocomplete="new-password"@else autocomplete="current-password"@endif></div>
@endforeach
<p class="help">Changing your password signs out your other sessions.</p><button class="button">Update password</button></form></section>
@endsection

