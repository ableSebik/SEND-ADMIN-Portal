@extends('layout')
@section('title','Delete vehicle')
@section('subtitle',$record->reference.' · '.$record->name)
@section('content')
<section class="panel"><div class="panel-header"><h2>Confirm vehicle deletion</h2></div><form class="panel-body stack" method="post" action="/records/vehicles/{{ $record->id }}/delete">@csrf
<input type="hidden" name="version" value="{{ $record->version }}">
<p>This removes the vehicle from the active fleet. Linked trip history is retained. Close any unfinished trips first.</p>
<div><label for="current_password">Your password</label><input id="current_password" type="password" name="current_password" required autocomplete="current-password"></div>
<div><label for="confirmation_word">Type {{ $word }} to confirm</label><input id="confirmation_word" name="confirmation_word" required autocomplete="off" spellcheck="false"><p class="help">This confirmation expires in 10 minutes.</p></div>
<div><button class="button danger">Delete vehicle</button> <a class="button secondary" href="/records/vehicles/{{ $record->id }}">Cancel</a></div></form></section>
@endsection
