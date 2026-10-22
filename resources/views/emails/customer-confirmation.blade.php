<!doctype html>
<html lang="hu">
<head><meta charset="utf-8"><title>Ajánlatkérés visszaigazolása</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.6">
<h1>Köszönöm a megkeresésed!</h1>
<p>Kedves {{ $quote->customer_name }}!</p>
<p>Megkaptam az ajánlatkérésedet (azonosító: #{{ $quote->id }}). Átnézem a részleteket, és felveszem veled a kapcsolatot.</p>
<p>Az ajánlatkérésed állapotát az alábbi privát linken követheted:</p>
<p><a href="{{ $portalUrl }}">Projektkövető felületem megnyitása</a></p>
<p>A link személyes hozzáférést ad az ajánlatkérésedhez; kérlek, ne oszd meg másokkal.</p>
<p>Ez automatikus visszaigazolás, nem végleges árajánlat.</p>
<p>Megyeri Márk | Webfejlesztés</p>
</body>
</html>
