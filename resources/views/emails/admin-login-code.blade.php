@extends('emails.layouts.aurora')
@section('nohero', '1')
@section('title','Admin bejelentkezési kód')
@section('preheader','Egyszer használatos biztonsági kód; 10 percig érvényes.')
@section('eyebrow','BIZTONSÁGI ÉRTESÍTÉS / ADMIN')
@section('headline','Erősítsd meg a bejelentkezést.')
@section('content')
<x-mail.text>{{ $adminName }}, egy adminbejelentkezést kell megerősítened az alábbi kóddal:</x-mail.text>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:22px 0;border-collapse:separate;background:#0f1830;border:1px solid #2a3a66;border-radius:14px;"><tr><td align="center" style="padding:22px;color:#ffffff;font-family:'Courier New',monospace;font-size:34px;font-weight:bold;letter-spacing:10px;">{{ $code }}</td></tr></table>
<x-mail.note>A kód 10 percig érvényes. Ha nem te próbálsz belépni, változtasd meg a jelszavadat.</x-mail.note>
@endsection
