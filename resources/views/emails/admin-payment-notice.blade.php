@extends('emails.layouts.aurora')
@section('title', $headline)
@section('eyebrow','ADMIN / FIZETÉS')
@section('headline', $headline)
@section('content')
<table role="presentation" style="background:#0f1830;border:1px solid #24345e;border-radius:12px;" width="100%"><tr><td style="padding:20px;color:#e5e9f7;line-height:1.9;">
@foreach ($lines as $line){{ $line }}<br>@endforeach
</td></tr></table>
<p style="color:#8f9bbd;font-size:12px;line-height:1.7;">A Stripe-befizetés nem számla. A számlát a számlázóprogramodban (pl. Számlázz.hu, Billingo) állítsd ki.</p>
@endsection
