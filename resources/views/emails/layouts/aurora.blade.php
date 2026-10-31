<!doctype html>
<html lang="{{ trim($__env->yieldContent('en')) !== '' ? 'en' : 'hu' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="dark"><meta name="supported-color-schemes" content="dark"><title>@yield('title','Megyeri Márk | Webfejlesztés')</title></head>
{{-- Kobalt arculat (mint a weboldal): éjfekete-kék alap, #4263FF kobalt, #00D9FF cián. A sablonok ezt a keretet használják. --}}
<body style="margin:0;padding:0;background:#05080f;color:#eef2fb;font-family:'Space Grotesk','Manrope',Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
<div style="display:none;font-size:1px;line-height:1px;color:#05080f;max-height:0;max-width:0;opacity:0;overflow:hidden;">@yield('preheader','Megyeri Márk Máté – Webfejlesztés')</div>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;background:#05080f;"><tr><td align="center" style="padding:34px 14px 40px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:100%;max-width:600px;border-collapse:separate;background:#0b1222;border:1px solid #1f2b47;border-radius:20px;overflow:hidden;">
<tr><td style="height:4px;background:#4263ff;background-image:linear-gradient(90deg,#4263ff,#00d9ff);font-size:1px;line-height:4px;">&nbsp;</td></tr>
<tr><td style="padding:30px 34px 20px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td width="53" valign="top"><table role="presentation" cellpadding="0" cellspacing="0" style="border-radius:12px;background:#0f1627;border:1px solid #2a3a66;"><tr><td style="color:#ffffff;font-size:21px;font-weight:800;letter-spacing:-1px;text-align:center;width:44px;height:44px;">M<span style="color:#00d9ff;">.</span></td></tr></table></td><td style="padding-left:12px;color:#ffffff;font-size:15px;font-weight:bold;line-height:20px;letter-spacing:1px;">MEGYERI MÁRK MÁTÉ<br><span style="font-size:10px;letter-spacing:2.4px;color:#8c98b4;font-weight:normal;">WEBFEJLESZTÉS · DIGITÁLIS RENDSZEREK</span></td></tr></table>
</td></tr>
@sectionMissing('nohero')
{{-- A márka 3D identitása: a kristályszikla (JPG, hogy minden levelezőprogram megjelenítse; a szöveg nélküle is teljes). --}}
<tr><td style="padding:4px 34px 6px;"><img src="https://megyerimarkmate.hu/brand/email-hero.jpg" width="532" alt="" style="display:block;width:100%;max-width:532px;height:auto;border:0;border-radius:14px;background:#0b1222;"></td></tr>
@endif
<tr><td style="padding:14px 34px 38px;">
<p style="font-size:11px;letter-spacing:2.6px;font-weight:bold;color:#6f8bff;margin:0 0 14px;">@yield('eyebrow','MEGYERI / AUTOMATIKUS ÉRTESÍTÉS')</p>
<h1 style="font-size:31px;line-height:1.12;letter-spacing:-1.2px;font-weight:700;color:#ffffff;margin:0 0 24px;">@yield('headline')</h1>
@yield('content')
</td></tr>
<tr><td style="padding:23px 34px;border-top:1px solid #1f2b47;background:#080d19;color:#8c98b4;font-size:12px;line-height:1.8;">
@hasSection('en')
<span style="font-size:13px;font-weight:bold;color:#eef2fb;">Márk Máté Megyeri</span> &nbsp;·&nbsp; Web development<br>
This message was sent by megyerimarkmate.hu to the address you provided.<br>
@else
<span style="font-size:13px;font-weight:bold;color:#eef2fb;">Megyeri Márk Máté</span> &nbsp;·&nbsp; Webfejlesztés<br>
Ezt az üzenetet a megyerimarkmate.hu rendszere küldte a megadott e-mail-címre.<br>
@endif
<a style="color:#6f8bff;text-decoration:none;" href="https://megyerimarkmate.hu/">megyerimarkmate.hu</a>
</td></tr>
</table>
@sectionMissing('en')
<p style="max-width:570px;margin:17px 0 0;color:#6f7b96;font-size:11px;line-height:1.6;text-align:center;">A projektadatokhoz tartozó privát hivatkozásokat biztonságos helyen őrizd meg.</p>
@endif
</td></tr></table></body></html>
