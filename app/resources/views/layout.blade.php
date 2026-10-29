<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('title','Dashboard') · SEND-AMS</title><link rel="stylesheet" href="{{ asset('portal.css') }}"><script src="{{ asset('portal.js') }}" defer></script></head><body>
<svg class="sprite" xmlns="http://www.w3.org/2000/svg"><defs>
<symbol id="dashboard" viewBox="0 0 24 24"><path d="m3 10 9-7 9 7v10H3zM9 20v-7h6v7"/></symbol>
<symbol id="vendors" viewBox="0 0 24 24"><circle cx="9" cy="7" r="3"/><path d="M2 21v-3a7 7 0 0 1 14 0v3M17 4a3 3 0 0 1 0 6m2 4a6 6 0 0 1 3 5v2"/></symbol>
<symbol id="trips" viewBox="0 0 24 24"><path d="M16 3a5 5 0 0 0-5 5c0 4 5 8 5 8s5-4 5-8a5 5 0 0 0-5-5Z"/><circle cx="16" cy="8" r="1.5"/><path d="M15 21H6a3 3 0 0 1 0-6h1a3 3 0 0 0 0-6H3" stroke-dasharray="2 2"/></symbol><symbol id="vehicles" viewBox="0 0 24 24"><path d="M2 5h13v13H2zM15 10h4l3 4v4h-7"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="19" r="2"/></symbol>
<symbol id="assets" viewBox="0 0 24 24"><path d="m12 2 10 5v10l-10 5-10-5V7zm0 10L2 7m10 5L22 7M12 12v10M7 4.5l10 5"/></symbol>
<symbol id="approvals" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m7 12 3 3 7-7"/></symbol>
<symbol id="reports" viewBox="0 0 24 24"><path d="M4 20V10m8 10V3m8 17V7"/></symbol>
<symbol id="audit" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 7h8M8 12h8M8 17h5"/></symbol>
<symbol id="arrow" viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></symbol>
<symbol id="plus" viewBox="0 0 24 24"><path d="M12 4v16M4 12h16"/></symbol>
<symbol id="bell" viewBox="0 0 24 24"><path d="M5 17h14l-2-4V8a5 5 0 0 0-10 0v5zm5 4h4"/></symbol>
</defs></svg>
@auth
<aside class="sidebar" id="navigation"><button class="sidebar-close" aria-label="Close sidebar" type="button">×</button>@include('brand', ['variant' => 'mark'])<nav aria-label="Main navigation">
@php
    $nav=[];
    if(auth()->user()->role==='driver') {
        $nav['/records/trips']=['trips','Trips'];
    } else {
        $nav['/']=['dashboard','Dashboard'];
        if(\App\Services\Access::allows(auth()->user(),'view')) foreach(App\Http\Controllers\PortalController::MODULES as $key=>$label) $nav['/records/'.$key]=[$key,$label];
        if(\App\Services\Access::allows(auth()->user(),'approve')) $nav['/approvals']=['approvals','Approvals'];
        if(\App\Services\Access::allows(auth()->user(),'export')) $nav['/reports']=['reports','Reports'];
        if(\App\Services\Access::allows(auth()->user(),'audit')) $nav['/audit']=['audit','Audit Trail'];
        if(\App\Services\Access::allows(auth()->user(),'users')) $nav['/users']=['vendors','Users'];
        $nav['/notifications']=['bell','Notifications'];
    }
@endphp
@foreach($nav as $url=>[$icon,$label])<a href="{{ $url }}" title="{{ $label }}" class="{{ request()->getPathInfo()===$url||($url!=='/'&&str_starts_with(request()->getPathInfo(),$url))?'selected':'' }}"><svg><use href="#{{ $icon }}"/></svg><span class="nav-label">{{ $label }}</span></a>@endforeach
</nav><div class="sidebar-bottom"><div class="avatar">SG</div><div><strong>SEND Ghana</strong><small>Administration</small></div></div></aside>
<div class="workspace"><header class="topbar"><button class="menu-toggle" aria-label="Toggle sidebar" aria-controls="navigation" aria-expanded="true">☰</button><span class="system-name">Administrative Management System</span><div class="account"><span class="avatar light">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span><details class="account-menu"><summary><span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role_label }}</small></span><svg class="chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary><div class="account-dropdown"><a href="/account">Account</a><form method="post" action="/logout">@csrf<button type="submit">Sign out</button></form></div></details></div></header><main>
<div class="page-heading"><div><h1>@yield('title','Dashboard')</h1><p>@yield('subtitle',now()->timezone('Africa/Accra')->format('l, j F Y'))</p></div><div class="heading-actions">@yield('actions')</div></div>
@if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert"><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')</main><footer>SEND-AMS <span>Secure administration. Accountable resources.</span><span>Africa/Accra · GHS</span></footer></div>
@else @yield('content') @endauth
</body></html>

