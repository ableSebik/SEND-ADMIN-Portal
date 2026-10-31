@extends('layout')
@section('title',$record->name)
@section('subtitle',$record->reference.' · Vehicle record')
@section('actions')
<a class="button secondary" href="/operational-history?vehicle={{ $record->id }}">Operational history</a>
@if(auth()->user()->isSuperAdmin())<a class="button secondary danger" href="/records/vehicles/{{ $record->id }}/delete">Delete vehicle</a>@endif
@if(\App\Services\Access::allows(auth()->user(),'vehicles.write'))<a class="button" href="/records/vehicles/{{ $record->id }}/edit">Edit vehicle</a>@endif
<a class="button secondary" href="/records/vehicles">Back to vehicles</a>
@endsection
@section('content')
<section class="panel"><div class="panel-header"><h2>Vehicle details</h2><span class="badge">{{ $record->status }}</span></div><div class="panel-body"><dl class="detail-list"><div><dt>Reference</dt><dd>{{ $record->reference }}</dd></div><div><dt>Registration number</dt><dd>{{ $record->data['registration']??'—' }}</dd></div><div><dt>Office / location</dt><dd>{{ $record->location }}</dd></div><div><dt>Chassis / VIN</dt><dd>{{ $record->data['serial']??'—' }}</dd></div><div><dt>Mileage (km)</dt><dd>{{ number_format((float)($record->data['odometer']??0),1) }}</dd></div><div><dt>Default OSO</dt><dd>{{ $people->firstWhere('id',$record->data['driver_id']??null)?->name??'Not assigned' }}</dd></div>@if(!empty($record->data['notes']))<div><dt>Notes</dt><dd>{{ $record->data['notes'] }}</dd></div>@endif</dl></div></section>
@include('support')
@if(\App\Services\Access::allows(auth()->user(),'vehicles.write'))
<section class="panel"><div class="panel-header"><h2>Vehicle availability</h2></div><form class="panel-body stack" method="post" action="/vehicles/{{ $record->id }}/status">@csrf<input type="hidden" name="version" value="{{ $record->version }}"><div><label for="status">Status</label><select name="status" id="status">@foreach(\App\Services\FleetOperations::STATUSES as $status)<option @selected($record->status===$status)>{{ $status }}</option>@endforeach</select></div><p class="help">Choose Maintenance when sending for servicing, or Unavailable for an unusable vehicle. Only Available vehicles can start trips.</p><div><label for="reason">Reason *</label><textarea id="reason" name="reason" required maxlength="1000"></textarea></div><button class="button">Update status</button></form></section>
@endif
@endsection


