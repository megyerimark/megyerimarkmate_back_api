@extends('emails.layouts.aurora')
@section('title','Új ügyfélüzenet')
@section('eyebrow','ADMIN / ÜZENET')
@section('headline','Üzenetet kaptál.')
@section('content')
<p style="color:#cad3ed;line-height:1.85;"><strong style="color:white;">{{ $quote->customer_name }}</strong> üzenetet írt a <strong>#{{ $quote->id }}</strong> számú projekt oldalán.</p>
<table role="presentation" style="background:#152344;border:1px solid #344578;border-radius:12px;" width="100%"><tr><td style="padding:20px;color:#e5e9f7;line-height:1.8;white-space:pre-line;">{{ \Illuminate\Support\Str::limit($projectMessage->body, 600) }}</td></tr></table>
<p style="margin:26px 0;"><a href="{{ $adminUrl }}" style="display:inline-block;padding:13px 22px;border-radius:999px;background:linear-gradient(120deg,#a78bfa,#7dd3fc);color:#0b1020;font-weight:700;text-decoration:none;">Megnyitás az adminban</a></p>
<p style="color:#8f9bbd;font-size:12px;line-height:1.7;">Ha az ügyfél több üzenetet küld egymás után, 10 percenként legfeljebb egy értesítőt kapsz; az összes üzenet az adminban olvasható.</p>
@endsection
