@extends('layout')
@section('title','Send vehicle for servicing')
@section('subtitle','Record the service request and mark the vehicle as Maintenance.')
@section('actions')<a class="button secondary" href="/operational-history?vehicle={{ $vehicle->id }}">Back to operational history</a>@endsection
@section('content')
<section class="panel"><div class="panel-header"><h2>Vehicle details</h2><span class="badge">{{ $vehicle->status }}</span></div><div class="panel-body"><dl class="detail-list"><div><dt>Vehicle / make / model</dt><dd>{{ $vehicle->name }}</dd></div><div><dt>Registration</dt><dd>{{ $vehicle->data['registration']??$vehicle->reference }}</dd></div><div><dt>Current mileage</dt><dd>{{ number_format($vehicle->data['odometer']??0,1) }} km</dd></div></dl></div></section>
<form class="panel" action="/vehicles/{{ $vehicle->id }}/service" method="post">@csrf<input type="hidden" name="version" value="{{ $vehicle->version }}"><div class="form-section"><h2>Service request</h2><div class="form-grid">
<div><label for="project">Project / Admin *</label><select id="project" name="project" required><option value="">Choose department or project</option>@foreach(\App\Models\User::DEPARTMENTS as $name)<option @selected(old('project')===$name)>{{ $name }}</option>@endforeach @foreach(\Illuminate\Support\Facades\DB::table('asset_projects')->where('status','Active')->orderBy('name')->get() as $project)<option @selected(old('project')===$project->name)>{{ $project->name }}</option>@endforeach</select></div>
<div><label for="requested_on">Request date *</label><input id="requested_on" name="requested_on" type="date" required value="{{ old('requested_on',now()->format('Y-m-d')) }}"></div>
<div><label for="service_type">Type of service *</label><select name="service_type" id="service_type" required>@foreach(['Routine','Repair','Accident','Other'] as $type)<option @selected(old('service_type')===$type)>{{ $type }}</option>@endforeach</select></div>
<div><label for="provider">Service provider</label><input id="provider" name="provider" maxlength="180" value="{{ old('provider') }}"></div>
<div class="full"><label for="reason">Reason / problem identified *</label><textarea id="reason" name="reason" required maxlength="3000">{{ old('reason') }}</textarea></div>
<div><label for="procurement_method">Procurement method *</label><select id="procurement_method" name="procurement_method" required>@foreach(['RFQ','Contract/LTA','Other'] as $method)<option @selected(old('procurement_method')===$method)>{{ $method }}</option>@endforeach</select></div>
<div><label for="diagnostic_report">Diagnostic report available? *</label><select id="diagnostic_report" name="diagnostic_report" required>@foreach(['Yes','No','N/A'] as $answer)<option @selected(old('diagnostic_report','N/A')===$answer)>{{ $answer }}</option>@endforeach</select></div>
<div class="full"><p class="help">Requested by {{ auth()->user()->name }}. Saving immediately marks the vehicle as Maintenance and prevents new trips. Record completed servicing in Operational history to retain the work completion details.</p></div>
</div></div><div class="form-footer"><button class="button">Save request & send for servicing</button></div></form>
@endsection
