@extends('layout')
@section('title','Open a trip')
@section('subtitle','Phase 1 of 2 · Record the opening details before your journey.')
@section('actions')
<a class="button secondary" href="/records/trips">Back to Trips</a>
@endsection
@section('content')
<div class="trip-phases"><span class="phase active">1. Opening details</span><span class="phase">2. Closing details</span></div>
<form method="post" action="/records/trips" class="panel">@csrf
<div class="form-section"><h2>Opening details</h2><div class="form-grid">
@if(auth()->user()->isSuperAdmin())
<div><label for="driver_id">OSO *</label><select id="driver_id" name="driver_id" required><option value="">Choose OSO</option>@foreach($drivers as $driver)<option value="{{ $driver->id }}" @selected(old('driver_id')==$driver->id)>{{ $driver->name }}</option>@endforeach</select><p class="help">As Super Administrator, you can open a trip on behalf of an OSO.</p></div>
@else
<div><label for="driver">OSO</label><input id="driver" value="{{ auth()->user()->name }}" readonly aria-readonly="true"><p class="help">Taken from your signed-in OSO account.</p></div>
@endif
<div><label for="vehicle_id">Vehicle *</label><select id="vehicle_id" name="vehicle_id" required><option value="">Choose vehicle</option>@foreach($vehicles as $vehicle)<option data-mileage="{{ $vehicle->data['odometer']??0 }}" value="{{ $vehicle->id }}" @selected(old('vehicle_id')==$vehicle->id)>{{ $vehicle->name }} · {{ $vehicle->data['registration']??$vehicle->reference }}</option>@endforeach</select>@if(auth()->user()->role==='driver')<p class="help">Available vehicles in your office: {{ auth()->user()->location }}.</p>@if($vehicles->isEmpty())<p class="help">No available vehicles in your office. Contact an administrator.</p>@endif @endif</div>
@foreach(['name'=>'Purpose of journey','from'=>'From','destination'=>'Destination'] as $key=>$label)<div><label for="{{ $key }}">{{ $label }} *</label><input id="{{ $key }}" name="{{ $key }}" value="{{ old($key) }}" required maxlength="{{ $key==='category'?80:180 }}"></div>@endforeach
@include('trips.project-field')
<div><label for="departure">Departure date and time *</label><input id="departure" name="departure" type="datetime-local" value="{{ old('departure',now()->timezone('Africa/Accra')->format('Y-m-d\TH:i')) }}" required></div>
<div><label for="opening">Mileage Before (km) *</label><input id="opening" name="opening" type="number" step="0.1" min="0" value="{{ old('opening') }}" required></div>
@include('trips.mileage-verification')
<div><label for="fuel">Fuel issued (litres)</label><input id="fuel" name="fuel" type="number" step="0.01" min="0" value="{{ old('fuel') }}"></div>
<div><label for="passengers">Number of passengers</label><input id="passengers" name="passengers" type="number" min="0" max="100" value="{{ old('passengers') }}"></div>
<div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" maxlength="3000">{{ old('notes') }}</textarea></div>
</div></div><div class="form-footer"><span class="help">The trip will remain Pending until you add the closing details.</span><a class="button secondary" href="/records/trips">Cancel</a><button class="button">Save opening details</button></div></form>
@endsection


