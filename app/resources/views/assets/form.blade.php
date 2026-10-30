@extends('layout')
@section('title',$record?'Edit asset':'Add Asset')
@section('subtitle','Step 2 of 2 · '.\App\Services\AssetFields::TYPES[$type])
@section('actions')<a class="button secondary" href="/records/assets">Back to assets</a>@endsection
@section('content')
@php $data=$record?->data??[]; $base=$record?'/records/assets/'.$record->id.'/edit':'/records/assets/new'; @endphp
<div class="trip-phases"><a class="phase done" href="{{ $base }}?choose_type=1">1. {{ \App\Services\AssetFields::TYPES[$type] }}</a><span class="phase active">2. Asset details</span></div>
<form class="panel" method="post" action="{{ $record?'/records/assets/'.$record->id.'/edit':'/records/assets' }}">@csrf<input type="hidden" name="asset_type" value="{{ $type }}">@if($record)<input type="hidden" name="version" value="{{ $record->version }}">@endif
<div class="form-section"><div class="split-head"><h2>{{ \App\Services\AssetFields::TYPES[$type] }}</h2><a class="link" href="{{ $base }}?choose_type=1">Change type</a></div><div class="form-grid">
<div><label for="name">Name of asset *</label><input id="name" name="name" required maxlength="180" value="{{ old('name',$record?->name) }}"></div>
<div><label for="year">Year *</label><input id="year" name="year" type="number" min="1900" max="2100" required value="{{ old('year',$data['year']??(!empty($data['purchase_date'])?substr($data['purchase_date'],0,4):($record?null:now()->year))) }}"></div>
<div class="full"><label for="description">Description</label><textarea id="description" name="description" maxlength="3000">{{ old('description',$data['description']??'') }}</textarea></div>
<div><label for="office_id">{{ $type==='motor_vehicle'?'Registration no. / Office ID no.':'Office ID no.' }}</label><input id="office_id" name="office_id" maxlength="100" value="{{ old('office_id',$data['office_id']??$data['registration']??$data['asset_tag']??'') }}"></div>
<div><label for="purchase_date">Date of purchase</label><input id="purchase_date" name="purchase_date" type="date" value="{{ old('purchase_date',$data['purchase_date']??'') }}"></div>
<div><label for="supplier">Supplier</label><input id="supplier" name="supplier" maxlength="180" value="{{ old('supplier',$data['supplier']??'') }}"></div>
@if($type==='motor_vehicle')<div><label for="origin">Origin</label><input id="origin" name="origin" maxlength="100" value="{{ old('origin',$data['origin']??'') }}"></div>@endif
<div><label for="model">Model no.</label><input id="model" name="model" maxlength="180" value="{{ old('model',$data['model']??'') }}"></div>
<div><label for="manufacturer">Make</label><input id="manufacturer" name="manufacturer" maxlength="180" value="{{ old('manufacturer',$data['manufacturer']??'') }}"></div>
<div><label for="manufacture_year">Year of manufacture</label><input id="manufacture_year" name="manufacture_year" type="number" min="1900" max="2100" value="{{ old('manufacture_year',$data['manufacture_year']??'') }}"></div>
<div><label for="serial">{{ $type==='motor_vehicle'?'Chassis no. / Serial no.':'Serial no.' }}</label><input id="serial" name="serial" maxlength="180" value="{{ old('serial',$data['serial']??'') }}"></div>
<div><label for="location">Office / location *</label><select id="location" name="location" required><option value="">Choose office / location</option>@foreach(\App\Models\User::OFFICES as $office)<option value="{{ $office }}" @selected(old('location',$record?->location)===$office)>{{ $office }}</option>@endforeach</select></div>
<div><label for="staff_responsible">Staff responsible</label><input id="staff_responsible" name="staff_responsible" maxlength="180" value="{{ old('staff_responsible',$data['staff_responsible']??'') }}"><p class="help">Record the responsible staff or team. Formal custody is managed through approved handovers.</p></div>
<div><label for="invoice">Invoice no.</label><input id="invoice" name="invoice" maxlength="100" value="{{ old('invoice',$data['invoice']??'') }}"></div>
<div><label for="price">Price (GH¢) *</label><input id="price" name="price" type="number" min="0" max="9999999999.99" step="0.01" required value="{{ old('price',$data['price']??$data['cost']??'') }}"></div>
</div></div>
<div class="form-section"><h2>Funding and department</h2><div class="form-grid">
@foreach(['project'=>'Project','donor'=>'Donor'] as $field=>$label)<div><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" maxlength="180" value="{{ old($field,$data[$field]??'') }}"></div>@endforeach
<div><label for="department">Department (optional)</label><select id="department" name="department"><option value="">Choose department</option>@foreach(\App\Models\User::DEPARTMENTS as $department)<option value="{{ $department }}" @selected(old('department',$record?->department)===$department)>{{ $department }}</option>@endforeach</select></div>
<div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" maxlength="3000">{{ old('notes',$data['notes']??'') }}</textarea></div>
</div></div><div class="form-footer">@if($record)<p class="help">Changes require independent approval before they take effect.</p>@endif<a class="button secondary" href="/records/assets">Cancel</a><button class="button">{{ $record?'Submit changes':'Add Asset' }}</button></div></form>
@endsection
