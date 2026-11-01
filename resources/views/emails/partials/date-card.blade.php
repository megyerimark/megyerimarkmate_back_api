{{-- Időpont-kártya: nagy dátum + nap és idősáv (a levél nyelvén) --}}
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:22px 0;border-collapse:separate;background:#0f1830;border:1px solid #2a3a66;border-radius:16px;">
<tr>
<td width="96" align="center" valign="middle" style="width:96px;padding:18px 0;background:#4263ff;border-radius:15px 0 0 15px;">
<span style="display:block;color:#dfe7ff;font-size:11px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;">{{ mb_strtoupper(rtrim($start->translatedFormat('M'), '.')) }}</span>
<span style="display:block;color:#ffffff;font-size:38px;font-weight:bold;line-height:1.1;">{{ $start->format('j') }}</span>
<span style="display:block;color:#dfe7ff;font-size:11px;">{{ $start->format('Y') }}</span>
</td>
<td valign="middle" style="padding:18px 22px;">
<span style="display:block;color:#8fb0ff;font-size:11px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;">{{ $start->translatedFormat('l') }}</span>
<span style="display:block;margin-top:4px;color:#ffffff;font-size:26px;font-weight:bold;letter-spacing:-.5px;">{{ $start->format('H:i') }} – {{ $end->format('H:i') }}</span>
<span style="display:block;margin-top:4px;color:#aab4ca;font-size:12px;">{{ mt(':min perc · budapesti idő szerint', ['min' => $booking->slot->duration_minutes]) }}</span>
</td>
</tr>
</table>
