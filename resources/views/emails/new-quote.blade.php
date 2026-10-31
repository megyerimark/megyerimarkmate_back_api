@extends('emails.layouts.aurora')
@php($types = ['landing' => 'Landing oldal', 'business' => 'Céges weboldal', 'webshop' => 'Webshop', 'custom' => 'Egyedi webes rendszer'])
@section('title','Új ajánlatkérés')
@section('preheader', 'Új ajánlatkérés: '.$quote->customer_name.' – '.($types[$quote->website_type] ?? $quote->website_type))
@section('eyebrow','ADMIN / ÚJ MEGKERESÉS')
@section('headline','Új ajánlatkérés érkezett.')
@section('content')
@if(str_starts_with($quote->project_description ?? '', 'AURORA KVÍZ – IGÉNYFELMÉRÉS'))
<p style="display:inline-block;margin:0 0 6px;padding:8px 12px;border:1px solid #4263ff;border-radius:9px;background:#0f1a3a;color:#a9c0ff;font-size:11px;font-weight:bold;letter-spacing:1px;">✦ INTERAKTÍV KVÍZBŐL ÉRKEZETT</p>
@endif
<x-mail.details title="#{{ $quote->id }} / AZ ÉRDEKLŐDŐ">
<x-mail.row label="Név" :first="true">{{ $quote->customer_name }}</x-mail.row>
<x-mail.row label="E-mail"><a href="mailto:{{ $quote->email }}" style="color:#8fb0ff;text-decoration:none;">{{ $quote->email }}</a></x-mail.row>
@if($quote->phone)<x-mail.row label="Telefon"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $quote->phone) }}" style="color:#8fb0ff;text-decoration:none;">{{ $quote->phone }}</a></x-mail.row>@endif
<x-mail.row label="Cég">{{ $quote->company_name ?: 'Nincs megadva' }}</x-mail.row>
<x-mail.row label="Típus">{{ $types[$quote->website_type] ?? $quote->website_type }}</x-mail.row>
@if($quote->industry)<x-mail.row label="Iparág">{{ $quote->industry }}</x-mail.row>@endif
@if($quote->layout_style)<x-mail.row label="Elrendezés">{{ $quote->layout_style }}</x-mail.row>@endif
@if($quote->font_style)<x-mail.row label="Betűvilág">{{ $quote->font_style }}</x-mail.row>@endif
<x-mail.row label="Költségkeret">{{ $quote->budget_range ?: 'Nincs megadva' }}</x-mail.row>
@if($quote->promo_percent)<x-mail.row label="Akció">−{{ $quote->promo_percent }}%@if($quote->promo_title) ({{ $quote->promo_title }})@endif</x-mail.row>@endif
</x-mail.details>
@if($quote->project_description)
<x-mail.details title="A PROJEKT LEÍRÁSA">
<tr><td colspan="2" style="padding:6px 20px 18px;color:#c7d0ec;font-size:14px;line-height:1.75;white-space:pre-line;word-break:break-word;">{{ $quote->project_description }}</td></tr>
</x-mail.details>
@endif
<x-mail.note>Erre a levélre válaszolva közvetlenül az érdeklődőnek írsz. A további adatok a védett adminfelületen tekinthetők meg.</x-mail.note>
@endsection
