@extends('layout')
@section('title',$record?'Edit vehicle':'Add vehicle')
@section('subtitle','Vehicle details · Shared fleet register')
@section('actions')
<a class="button secondary" href="/records/vehicles">Back to vehicles</a>
@endsection
@section('content')
<form class="panel" method="post" action="{{ $record?'/records/vehicles/'.$record->id.'/edit':'/records/vehicles' }}">
@csrf
@if($record)<input type="hidden" name="version" value="{{ $record->version }}">@endif
<div class="form-section"><h2>Vehicle record</h2><div class="form-grid">
<div><label for="name">Vehicle name *</label><input id="name" name="name" required maxlength="180" value="{{ old('name',$record?->name) }}"></div>
<div><label for="registration">Registration number *</label><input id="registration" name="registration" required maxlength="40" value="{{ old('registration',$record?->data['registration']??'') }}"></div>
<div><label for="location">Office / location *</label><select id="location" name="location" required><option value="">Choose office / location</option>@foreach(\App\Models\User::OFFICES as $office)<option value="{{ $office }}" @selected(old('location',$record?->location??'Accra')===$office)>{{ $office }}</option>@endforeach</select>@if($record&&!in_array($record->location,\App\Models\User::OFFICES,true))<p class="help">Current value: {{ $record->location }}. Choose an office to update this vehicle.</p>@endif</div>
<div><label for="serial">Chassis / VIN</label><input id="serial" name="serial" maxlength="100" value="{{ old('serial',$record?->data['serial']??'') }}"></div>
<div><label for="driver_id">Default OSO (optional)</label><select id="driver_id" name="driver_id"><option value="">No default OSO</option>@foreach($drivers as $driver)<option value="{{ $driver->id }}" @selected(old('driver_id',$record?->data['driver_id']??'')==$driver->id)>{{ $driver->name }}</option>@endforeach</select></div>
<div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" maxlength="3000">{{ old('notes',$record?->data['notes']??'') }}</textarea></div>
</div></div><div class="form-footer"><a class="button secondary" href="/records/vehicles">Cancel</a><button class="button">{{ $record?'Save vehicle':'Add vehicle' }}</button></div></form>
@endsection


