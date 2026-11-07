@extends('layout')
@section('title','Delete '.$kind)
@section('subtitle',$record->name)
@section('content')
<form method="post" action="/reference-data/{{ $kind }}/{{ $record->id }}" class="panel">
@csrf @method('DELETE')
<div class="panel-header"><h2>Delete {{ $record->name }}?</h2></div>
<div class="panel-body stack">
<p>This removes the {{ $kind }} from the shared list. Existing asset and trip history is retained. Records with linked donor or project assignments cannot be deleted.</p>
<div><label for="confirmation">Type DELETE to confirm *</label><input id="confirmation" name="confirmation" required autocomplete="off" pattern="DELETE"></div>
<div><label for="current_password">Your administrator password *</label><input type="password" id="current_password" name="current_password" required autocomplete="current-password"></div>
</div>
<div class="form-footer"><a class="button secondary" href="/reference-data">Cancel</a><button class="button">Delete {{ $kind }}</button></div>
</form>
@endsection
