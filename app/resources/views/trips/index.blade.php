@extends('layout')
@section('title','Trips')
@section('subtitle','Open a trip before departure, then add closing details when you return.')
@section('actions')
@if(\App\Services\Access::allows(auth()->user(),'export'))<a class="button secondary" href="/records/trips/export">Export CSV</a>@endif
@if(\App\Services\Access::allows(auth()->user(),'trip'))<a class="button" href="/records/trips/new"><svg><use href="#plus"/></svg>Open a trip</a>@endif
@endsection
@section('content')
<section class="panel"><form class="filters" method="get"><input name="q" aria-label="Search trips" placeholder="Search purpose or reference…" value="{{ request('q') }}"><select name="status" aria-label="Filter by status"><option value="">All statuses</option>@foreach(['Pending','Completed','Rejected'] as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select><button class="button secondary">Filter trips</button>@if(request('q')||request('status'))<a class="link" href="/records/trips">Clear</a>@endif</form>
<div class="table-wrap"><table><thead><tr><th>Journey / reference</th><th>Department/Project</th><th>From</th><th>Destination</th><th>Mileage Before</th><th>Mileage After</th><th>Status</th><th></th></tr></thead><tbody>
@foreach($records as $trip)
@php $canClose=$trip->status==='Pending'&&(auth()->user()->isSuperAdmin()||(auth()->user()->role==='driver'&&(string)($trip->data['driver_id']??'')===(string)auth()->id())); @endphp
<tr><td><strong><a href="/records/trips/{{ $trip->id }}">{{ $trip->name }}</a></strong><small>{{ $trip->reference }}</small></td><td>{{ $trip->category }}</td><td>{{ $trip->data['from']??$trip->location }}</td><td>{{ $trip->data['destination']??'—' }}</td><td>{{ number_format((float)($trip->data['opening']??0),1) }}</td><td>{{ $trip->status==='Completed'?number_format((float)$trip->data['closing'],1):'—' }}</td><td><span class="badge {{ $trip->status==='Pending'?'pending':($trip->status==='Completed'?'completed':'rejected') }}">{{ $trip->status }}</span></td><td>@if($canClose)<a class="button secondary" href="/records/trips/{{ $trip->id }}/close">Add closing details</a>@else<a class="link" href="/records/trips/{{ $trip->id }}">View trip</a>@endif</td></tr>
@endforeach
</tbody></table></div>
@if($records->isEmpty())<div class="empty"><div class="empty-icon"><svg><use href="#vehicles"/></svg></div><strong>{{ request('q')||request('status')?'No matching trips.':'No trips recorded yet.' }}</strong><p>{{ auth()->user()->role==='driver'?'Open your first trip before starting your journey.':'Trips opened by OSOs will appear here.' }}</p></div>@endif
<div class="pagination">{{ $records->links() }}<p>{{ $records->total() }} trips</p></div></section>
@endsection

