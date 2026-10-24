@extends('emails.layouts.aurora')
@section('title','Admin jelszó-visszaállítás')
@section('preheader','Jelszó-visszaállítási hivatkozás az adminfelülethez; 30 percig érvényes.')
@section('eyebrow','BIZTONSÁGI ÉRTESÍTÉS / ADMIN')
@section('headline','Állíts be új jelszót.')
@section('content')
<p style="color:#c7d0ec;line-height:1.8;">{{ $adminName }}, jelszó-visszaállítási kérelmet kaptunk az adminfiókodhoz.</p>
<p style="color:#c7d0ec;line-height:1.8;">Az alábbi gombbal új jelszót állíthatsz be. A hivatkozás <strong style="color:#fff;">30 percig</strong> használható, és egy alkalommal érvényes.</p>
<table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="background:#6151ec;border-radius:10px;"><a href="{{ $resetUrl }}" style="display:inline-block;padding:16px 25px;color:#fff;text-decoration:none;font-size:14px;font-weight:bold;">Jelszó visszaállítása &rarr;</a></td></tr></table>
<p style="color:#a2afd2;font-size:12px;line-height:1.8;margin-top:26px;">Ha nem te kérted a jelszóváltoztatást, nincs további teendőd. Ne továbbítsd ezt az üzenetet.</p>
@endsection