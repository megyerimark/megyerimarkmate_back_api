@extends('emails.layouts.aurora')
@section('title','Haladás a projekteden')
@section('preheader','Elkészült: '.$milestone->title.' ('.$done.'/'.$total.')')
@section('eyebrow','PROJEKT / HALADÁS')
@section('headline','Újabb lépés elkészült.')
@section('content')
<x-mail.text>Kedves {{ $quote->customer_name }}! Jó hírem van: a #{{ $quote->id }} számú projekted egy újabb mérföldkőhöz ért.</x-mail.text>
@php($pct = $total > 0 ? (int) round($done / $total * 100) : 0)
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:22px 0;border-collapse:separate;background:#0f1830;border:1px solid #2a3a66;border-radius:16px;"><tr><td style="padding:20px 22px;">
<span style="display:block;color:#8fb0ff;font-size:11px;font-weight:bold;letter-spacing:2px;">✓ ELKÉSZÜLT</span>
<span style="display:block;margin-top:6px;color:#ffffff;font-size:22px;font-weight:bold;line-height:1.3;">{{ $milestone->title }}</span>
@if ($milestone->description)<span style="display:block;margin-top:8px;color:#aab4ca;font-size:13px;line-height:1.6;white-space:pre-line;">{{ $milestone->description }}</span>@endif
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:18px;"><tr>
<td style="height:8px;border-radius:99px;background:#1a2647;"><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="{{ max(4, $pct) }}%"><tr><td style="height:8px;border-radius:99px;background:#4263ff;background-image:linear-gradient(90deg,#4263ff,#00d9ff);font-size:1px;line-height:8px;">&nbsp;</td></tr></table></td>
</tr></table>
<span style="display:block;margin-top:8px;color:#aab4ca;font-size:12px;">{{ $done }} / {{ $total }} mérföldkő kész · {{ $pct }}%</span>
</td></tr></table>
<x-mail.button :href="$portalUrl">Projekt megnyitása &rarr;</x-mail.button>
<x-mail.note>A részleteket és a további lépéseket a projektoldalon látod. Kérdés esetén ott üzenetben, vagy erre a levélre válaszolva is elérsz.</x-mail.note>
@endsection
