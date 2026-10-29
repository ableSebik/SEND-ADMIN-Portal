@extends('layout')
@section('title','Reports & Exports')
@section('subtitle','Download permission-controlled registers for review and reconciliation.')
@section('content')<div class="report-grid">@foreach(App\Http\Controllers\PortalController::MODULES as $key=>$label)<section class="panel"><div class="panel-body"><span class="icon-tile"><svg><use href="#{{ $key==='trips'?'vehicles':$key }}"/></svg></span><h2 style="margin-top:20px">{{ $label }}</h2><p>Export the current authorized register as CSV.</p><a style="margin-top:20px" class="button secondary" href="/records/{{ $key }}/export">Download CSV</a></div></section>@endforeach</div><p class="help" style="margin-top:20px">Exports are logged in the audit trail. CSV files can be opened in Excel.</p>@endsection
