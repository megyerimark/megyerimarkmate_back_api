@extends('emails.layouts.aurora')
@section('title','Új ajánlatkérés')
@section('eyebrow','ADMIN / ÚJ MEGKERESÉS')
@section('headline','Új ajánlatkérés érkezett.')
@section('content')
<p style="color:#c7d0ec;line-height:1.8;">Azonosító: <strong style="color:#fff;">#{{ $quote->id }}</strong></p>
@if(str_starts_with($quote->project_description ?? '', 'AURORA KVÍZ – IGÉNYFELMÉRÉS'))
<p style="display:inline-block;padding:8px 12px;border:1px solid #4263ff;border-radius:9px;background:#29215c;color:#a9c0ff;font-size:11px;font-weight:bold;letter-spacing:1px;">✦ INTERAKTÍV KVÍZBŐL ÉRKEZETT</p>
@endif
<table role="presentation" style="background:#151e3d;border:1px solid #34426f;border-radius:12px;" width="100%"><tr><td style="padding:18px;color:#d5dafa;line-height:2;">
<strong>Név:</strong> {{ $quote->customer_name }}<br><strong>E-mail:</strong> {{ $quote->email }}<br>
<strong>Cég:</strong> {{ $quote->company_name ?: 'Nincs megadva' }}<br><strong>Típus:</strong> {{ $quote->website_type }}<br>
@if($quote->industry)<strong>Iparág:</strong> {{ $quote->industry }}<br>@endif
@if($quote->layout_style)<strong>Elrendezés:</strong> {{ $quote->layout_style }}<br>@endif
@if($quote->font_style)<strong>Betűvilág:</strong> {{ $quote->font_style }}<br>@endif
<strong>Költségkeret:</strong> {{ $quote->budget_range ?: 'Nincs megadva' }}</td></tr></table>
<p style="color:#b5c5e7;line-height:1.8;white-space:pre-wrap;overflow-wrap:anywhere;">{{ $quote->project_description }}</p>
<p style="color:#9baad0;font-size:12px;">A további adatok a védett adminfelületen tekinthetők meg.</p>
@endsection
