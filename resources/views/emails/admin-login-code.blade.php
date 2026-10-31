@extends('emails.layouts.aurora')
@section('title','Admin bejelentkezési kód')
@section('preheader','Egyszer használatos biztonsági kód; 10 percig érvényes.')
@section('eyebrow','BIZTONSÁGI ÉRTESÍTÉS / ADMIN')
@section('headline','Erősítsd meg a bejelentkezést.')
@section('content')
<p style="color:#c7d0ec;line-height:1.8;">{{ $adminName }}, egy adminbejelentkezést kell megerősítened az alábbi kóddal:</p>
<p style="font-size:32px;font-weight:800;letter-spacing:7px;text-align:center;padding:18px;color:#a9c0ff;">{{ $code }}</p>
<p style="color:#a2afd2;font-size:12px;line-height:1.8;">A kód 10 percig érvényes. Ha nem te próbálsz belépni, változtasd meg a jelszavadat.</p>
@endsection
