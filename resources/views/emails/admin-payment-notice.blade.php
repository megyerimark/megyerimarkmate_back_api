@extends('emails.layouts.aurora')
@section('title', $headline)
@section('eyebrow','ADMIN / FIZETÉS')
@section('headline', $headline)
@section('content')
<x-mail.details title="RÉSZLETEK">
@foreach ($lines as $line)
<tr><td colspan="2" style="padding:{{ $loop->first ? '6px' : '10px' }} 20px {{ $loop->last ? '18px' : '0' }};color:#e5e9f7;font-size:14px;line-height:1.6;word-break:break-word;">{{ $line }}</td></tr>
@endforeach
</x-mail.details>
<x-mail.note>A Stripe-befizetés nem számla. A számlát a számlázóprogramodban (pl. Számlázz.hu, Billingo) állítsd ki.</x-mail.note>
@endsection
