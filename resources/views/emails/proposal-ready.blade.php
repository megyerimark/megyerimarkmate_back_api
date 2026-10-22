<!doctype html>
<html lang="hu">
<head><meta charset="utf-8"><title>Egyedi ajánlat</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.7;color:#172033">
<h1>Elkészült az ajánlatod</h1>
<p>Kedves {{ $quote->customer_name }}!</p>
<p>Az ajánlatkérésed (#{{ $quote->id }}) alapján elkészítettem a személyre szabott javaslatomat.</p>
<p><strong>Ajánlati összeg:</strong> {{ number_format($quote->quoted_price_huf, 0, ',', ' ') }} Ft</p>
<p><strong>Ajánlat érvényes:</strong> {{ $quote->proposal_valid_until->format('Y. m. d.') }} napjáig</p>
<p><strong>A javasolt tartalom:</strong></p>
<p style="white-space:pre-line">{{ $quote->proposal_summary }}</p>
<p><a href="{{ $portalUrl }}">Ajánlat megtekintése és visszajelzés</a></p>
<p>Ez a privát link az ajánlatodat tartalmazza; kérlek, ne továbbítsd másnak. Az ajánlat elfogadása a projektfelületen lehetséges.</p>
<p>Megyeri Márk Máté | Webfejlesztés</p>
</body>
</html>
