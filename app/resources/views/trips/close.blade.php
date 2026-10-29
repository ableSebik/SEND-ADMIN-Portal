@extends('layout')
@section('title','Close trip')
@section('subtitle',$trip->reference.' · Phase 2 of 2')
@section('actions')
<a class="button secondary" href="/records/trips/{{ $trip->id }}">Back to trip</a>
@endsection
@section('content')
<div class="trip-phases"><span class="phase done">1. Opening details saved</span><span class="phase active">2. Closing details</span></div>
<section class="panel"><div class="panel-header"><h2>{{ $trip->name }}</h2><span class="badge pending">Pending</span></div><div class="panel-body"><dl class="detail-list"><div><dt>OSO</dt><dd>{{ $driver?->name??'OSO #'.($trip->data['driver_id']??'') }}</dd></div><div><dt>Vehicle</dt><dd>{{ $vehicle?->name??'Vehicle record' }}</dd></div><div><dt>From</dt><dd>{{ $trip->data['from']??$trip->location }}</dd></div><div><dt>Destination</dt><dd>{{ $trip->data['destination']??'Not recorded in legacy log' }}</dd></div><div><dt>Departure</dt><dd>{{ $trip->data['departure'] }}</dd></div><div><dt>Mileage Before</dt><dd>{{ number_format((float)$trip->data['opening'],1) }} km</dd></div></dl></div></section>
<form method="post" action="/records/trips/{{ $trip->id }}/close" class="panel">@csrf<input type="hidden" name="version" value="{{ $trip->version }}"><div class="form-section"><h2>Closing details</h2><div class="form-grid">
<div><label for="closing">Mileage After (km) *</label><input id="closing" name="closing" type="number" step="0.1" min="{{ $trip->data['opening'] }}" value="{{ old('closing') }}" required></div>
<div><label for="return_at">Return date and time *</label><input id="return_at" name="return_at" type="datetime-local" value="{{ old('return_at',now()->timezone('Africa/Accra')->format('Y-m-d\TH:i')) }}" min="{{ $trip->data['departure'] }}" required><p class="help">Filled with the current date and time. Adjust it if needed.</p></div>
</div></div><div class="form-footer"><a class="button secondary" href="/records/trips/{{ $trip->id }}">Cancel</a><button class="button">Complete trip</button></div></form>
@endsection

