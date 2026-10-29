@extends('layout')
@section('title',$record?'Choose asset type':'Add Asset')
@section('subtitle','Step 1 of 2 · Select an asset group to continue.')
@section('actions')<a class="button secondary" href="/records/assets">Back to assets</a>@endsection
@section('content')
<div class="trip-phases"><span class="phase active">1. Type</span><span class="phase">2. Asset details</span></div>
<form method="get" action="{{ $record?'/records/assets/'.$record->id.'/edit':'/records/assets/new' }}" class="panel"><div class="panel-header"><h2>Asset type</h2></div><div class="panel-body stack"><div><label for="type">Type *</label><select id="type" name="type" required><option value="">Choose an asset type</option>@foreach(\App\Services\AssetFields::TYPES as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div><p>All groups share the same register. The next form includes the fields for your chosen group.</p><div><button class="button">Continue</button></div></div></form>
@endsection
