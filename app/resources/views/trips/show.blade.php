@extends('layout')
@section('title',$record->name)
@section('subtitle',$record->reference.' · Trip')
@php
 $driver=\App\Models\User::find($record->data['driver_id']??null);
 $vehicle=\App\Models\AdminRecord::withTrashed()->find($record->data['vehicle_id']??null);
 $canClose=$record->status==='Pending'&&(auth()->user()->isSuperAdmin()||(auth()->user()->role==='driver'&&(string)($record->data['driver_id']??'')===(string)auth()->id()));
@endphp
@section('actions')
@if($canClose)<a class="button" href="/records/trips/{{ $record->id }}/close">Add closing details</a>@endif
<a class="button secondary" href="/records/trips">Back to Trips</a>
@endsection
@section('content')
<div class="trip-phases"><span class="phase done">1. Opening details saved</span><span class="phase {{ $record->status==='Completed'?'done':'' }}">2. Closing details{{ $record->status==='Completed'?' saved':'' }}</span><span class="badge {{ $record->status==='Pending'?'pending':'completed' }}">{{ $record->status }}</span></div>
<div class="details-grid"><section class="panel"><div class="panel-header"><h2>Opening details</h2><span class="badge completed">Saved</span></div><div class="panel-body"><dl class="detail-list">
<div><dt>OSO</dt><dd>{{ $driver?->name??'OSO #'.($record->data['driver_id']??'') }}</dd></div><div><dt>Vehicle</dt><dd>{{ $vehicle?->name??'Vehicle record' }}</dd></div>
@if(isset($record->data['mileage_gap'])&&(float)$record->data['mileage_gap']!==0)<div><dt>Mileage difference</dt><dd>{{ $record->data['mileage_gap'] }} km (previous mileage: {{ $record->data['expected_mileage'] }} km). {{ $record->data['mileage_note']??'' }}</dd></div>@endif
<div><dt>Department/Project</dt><dd>{{ $record->category }}</dd></div><div><dt>Purpose of journey</dt><dd>{{ $record->name }}</dd></div>
<div><dt>From</dt><dd>{{ $record->data['from']??$record->location }}</dd></div><div><dt>Destination</dt><dd>{{ $record->data['destination']??'Not recorded in legacy log' }}</dd></div>
<div><dt>Departure date and time</dt><dd>{{ $record->data['departure']??'Not recorded' }}</dd></div><div><dt>Mileage Before</dt><dd>{{ number_format((float)($record->data['opening']??0),1) }} km</dd></div>
@foreach(['fuel'=>'Fuel issued (litres)','passengers'=>'Number of passengers','notes'=>'Notes'] as $key=>$label)@if(isset($record->data[$key]))<div><dt>{{ $label }}</dt><dd>{{ $record->data[$key] }}</dd></div>@endif @endforeach
</dl></div></section><section class="panel"><div class="panel-header"><h2>Closing details</h2><span class="badge {{ $record->status==='Completed'?'completed':'pending' }}">{{ $record->status==='Completed'?'Saved':'Awaiting return' }}</span></div>
@if($record->status==='Completed')<div class="panel-body"><dl class="detail-list"><div><dt>Mileage After</dt><dd>{{ number_format((float)$record->data['closing'],1) }} km</dd></div><div><dt>Return date and time</dt><dd>{{ $record->data['return_at'] }}</dd></div><div><dt>Distance travelled</dt><dd>{{ number_format((float)$record->data['distance'],1) }} km</dd></div></dl></div>
@else<div class="empty"><div class="empty-icon"><svg><use href="#vehicles"/></svg></div><strong>This trip is awaiting closing details.</strong><p>Both opening and closing details are required for a complete trip record.</p>@if($canClose)<a class="button" style="margin-top:20px" href="/records/trips/{{ $record->id }}/close">Add closing details</a>@else<p class="help">Only the OSO who opened this trip or a Super Administrator can close it.</p>@endif</div>@endif
</section></div>
<section class="panel"><div class="panel-header"><h2>Additional notes</h2></div><div class="panel-body">
@forelse($tripNotes as $note)<article><p style="white-space:pre-wrap">{{ $note->note }}</p><small class="subtle">{{ $note->name }} · {{ $note->created_at }}</small></article>@empty<p>No additional notes yet.</p>@endforelse
@if(\App\Services\Access::canAddTripNote(auth()->user(),$record))<form class="stack" method="post" action="/records/trips/{{ $record->id }}/notes">@csrf<div><label for="additional-note">Add a note</label><textarea id="additional-note" name="note" required maxlength="3000"></textarea></div><button class="button">Add note</button></form>@endif
</div></section>
@include('support')
@endsection


