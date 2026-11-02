@extends('emails.layouts.aurora')
@php($M = \App\Support\MailText::class)
@section('title', 'Utánkövetés')
@section('preheader', 'Ügyfelek, akiknek érdemes ma írnod vagy telefonálnod.')
@section('eyebrow', 'ADMIN / UTÁNKÖVETÉS')
@section('headline', 'Ők várnak rád.')
@section('content')
<x-mail.text>Az alábbi ügyfeleknél már egy ideje nem történt semmi. Egy rövid e-mail vagy telefon sokszor eldönti, hogy lesz-e munka belőle.</x-mail.text>
@if ($unanswered->isNotEmpty())
<x-mail.details title="MEGVÁLASZOLATLAN MEGKERESÉS ({{ $unanswered->count() }})">
@foreach ($unanswered as $q)
<x-mail.row :label="'#'.$q->id.' · '.$q->created_at->copy()->timezone('Europe/Budapest')->format('m. d.')" :first="$loop->first"><a href="{{ $adminUrl.$q->id }}" style="color:#ffffff;text-decoration:none;">{{ $q->customer_name }}@if($q->company_name) <span style="color:#8c98b4;font-weight:normal;">({{ $q->company_name }})</span>@endif</a><br><span style="color:#8c98b4;font-size:12px;font-weight:normal;">{{ $q->email }}@if($q->phone) · {{ $q->phone }}@endif · {{ (int) $q->created_at->diffInDays(now()) }} napja érkezett</span></x-mail.row>
@endforeach
</x-mail.details>
@endif
@if ($silent->isNotEmpty())
<x-mail.details title="AJÁNLAT ELKÜLDVE, NINCS VÁLASZ ({{ $silent->count() }})">
@foreach ($silent as $q)
<x-mail.row :label="'#'.$q->id.' · '.$M::money($q->quoted_price_huf)" :first="$loop->first"><a href="{{ $adminUrl.$q->id }}" style="color:#ffffff;text-decoration:none;">{{ $q->customer_name }}@if($q->company_name) <span style="color:#8c98b4;font-weight:normal;">({{ $q->company_name }})</span>@endif</a><br><span style="color:#8c98b4;font-size:12px;font-weight:normal;">{{ $q->email }}@if($q->phone) · {{ $q->phone }}@endif · ajánlat {{ (int) $q->quoted_at->diffInDays(now()) }} napja @if($q->proposal_valid_until)· érvényes: {{ $M::date($q->proposal_valid_until) }}@endif</span></x-mail.row>
@endforeach
</x-mail.details>
@endif
<x-mail.button href="{{ rtrim((string) config('portfolio.frontend_url'), '/') }}/admin">Admin megnyitása</x-mail.button>
<x-mail.note>Tételenként csak egyszer jelzek. Ha egy megkeresés nem aktuális, állítsd „elutasítva” állapotba.</x-mail.note>
@endsection
