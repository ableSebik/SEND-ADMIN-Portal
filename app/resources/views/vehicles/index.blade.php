@extends('layout')
@section('title','Vehicles')
@section('subtitle','Shared vehicle list · Readable by all users')
@section('actions')
@if(\App\Services\Access::allows(auth()->user(),'export'))<a class="button secondary" href="/records/vehicles/export">Export CSV</a>@endif
@if(\App\Services\Access::allows(auth()->user(),'vehicles.write'))<a class="button" href="/records/vehicles/new"><svg><use href="#plus"/></svg>Add vehicle</a>@endif
@endsection
@section('content')
<section class="panel"><form class="filters" method="get"><input name="q" aria-label="Search vehicles" placeholder="Search vehicle or reference…" value="{{ request('q') }}"><select name="status" aria-label="Filter by status"><option value="">All statuses</option>@foreach(['Available','Assigned','In use','Under maintenance','Out of service','Disposed'] as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select><button class="button secondary">Filter vehicles</button>@if(request('q')||request('status'))<a class="link" href="/records/vehicles">Clear</a>@endif</form>
<div class="table-wrap"><table><thead><tr><th>Vehicle / reference</th><th>Registration</th><th>Office / location</th><th>Mileage (km)</th><th>Status</th><th></th></tr></thead><tbody>@foreach($records as $vehicle)<tr><td><strong><a href="/records/vehicles/{{ $vehicle->id }}">{{ $vehicle->name }}</a></strong><small>{{ $vehicle->reference }}</small></td><td>{{ $vehicle->data['registration']??'—' }}</td><td>{{ $vehicle->location }}</td><td>{{ number_format((float)($vehicle->data['odometer']??0),1) }}</td><td><span class="badge">{{ $vehicle->status }}</span></td><td><a class="link" href="/records/vehicles/{{ $vehicle->id }}">View vehicle</a></td></tr>@endforeach</tbody></table></div>
@if($records->isEmpty())<div class="empty"><strong>No vehicles found.</strong><p>{{ \App\Services\Access::allows(auth()->user(),'vehicles.write')?'Add a vehicle to start the fleet register.':'Vehicles added by your administrator will appear here.' }}</p></div>@endif
<div class="pagination">{{ $records->links() }}<p>{{ $records->total() }} vehicles</p></div></section>
@endsection
