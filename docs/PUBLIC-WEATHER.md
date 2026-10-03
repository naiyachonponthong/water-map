# Public rain forecast and radar

- Province homepage: asynchronous 24-hour rain card with an hourly chart and link to `/{province}/weather`.
- Province homepage: wide-screen service shortcuts and information grid, with a compact single-column mobile layout and province picker. Public alerts and actual command-center availability remain visible.
- Weather page: current model temperature/feels-like/humidity/wind, rolling 24-hour rain outlook, seven selectable daily summaries and a detailed hourly table/chart (precipitation, probability, temperature, wind speed/direction). Wide desktop shows forecast and radar side by side; mobile uses a scrollable day strip and forecast/radar views.
- The province picker changes the forecast, city marker, real boundaries, title, home link and URL without a page reload. Old forecast values are cleared immediately; cancelled requests and generation checks prevent a late response from an earlier province overwriting the selected province. Only active provinces appear.
- Forecast: Open-Meteo at the configured province center. Rolling 24-hour window recalculated on every JSON request, Asia/Bangkok, millimetres and km/h. Missing readings remain null; incomplete rain/probability windows are never displayed as zero.
- Open-Meteo hourly precipitation is for the preceding hour, so upcoming rain excludes the bucket that has already ended. Chart timestamps are the ends of the rainfall intervals; the window starts at the current rounded hour.
- Weather-only fallback coordinates for all 77 provincial cities are stored in `config/weather-city-centers.php`, verified against Open-Meteo Geocoding (GeoNames IDs retained). Saved province coordinates take precedence. Forecasts describe a reference point, not a province-wide average. This feature does not change operational centers, boundaries or the existing alert engine. `tools/inspect-weather-centers.php` is a read-only provenance check, not a database seeder.
- Boundaries are shown only when real district geometry has been imported; none are fabricated. The center dot is the province reference point, not the visitor's location.

## Providers and restrictions

- https://open-meteo.com/en/docs
- https://open-meteo.com/en/docs/geocoding-api
- https://www.rainviewer.com/api/weather-maps-api.html
- https://www.rainviewer.com/api/transition-faq.html

RainViewer's 2026 free API supplies past radar only (about 2 hours). No future-radar claim is made. Native zoom is capped at provider zoom 7; larger map zooms enlarge existing images. The documented palette is Universal Blue. Images are loaded lazily with a conservative 80-tile/minute per-page budget (the provider's limit is per IP, so visitors sharing an IP can still reach it). Playback is opt-in and pauses on tab changes, panning, or a hidden browser tab. Radar coverage is not universal: transparent tiles do not prove that there is no rain.

Provider attribution links are visible. Check current licence and quota suitability before production deployment, particularly commercial uses: the public RainViewer API is intended for personal/educational use, and Open-Meteo's free service is non-commercial. For unsupported uses, obtain an appropriate provider/licence instead of bypassing limits.

## Caching and outages

Forecast data: fresh for 15 minutes, stale fallback up to 2 hours. Radar manifest: fresh for 1 minute, stale fallback up to 15 minutes. Both disclose fetch times and stale state; old latest radar images also show a delay warning. Failed provider requests have a 60-second retry backoff. Providers are called only by JSON endpoints, never during homepage rendering. Provider endpoints are throttled (30 requests/minute/client); local province context allows 60. JSON calls use the current origin and a 35-second browser timeout, with readable Thai outage/retry messages. Missing data is not fabricated.

`WEATHER_CA_BUNDLE` optionally points to a trusted CA bundle for a PHP installation without configured certificates. Local development uses the existing XAMPP CA bundle. TLS verification stays enabled. In deployment, use the system trust store or set a valid local path; do not copy the development Windows path blindly.

## Checks

Run `node --test tests/Frontend/public-weather.test.cjs` for in-memory client checks: seven-day rendering, daily selection, immediately cleared province values, cancelled/late responses, small-screen view switching, keyboard tab order and readable network failures. These use a mocked DOM and HTTP, not a real browser.

Run `php artisan test --filter=PublicWeatherTest` for page rendering without network, current-model fields, all 77 offline coordinate fallbacks, province-context responses, cache reuse, moving hourly window, missing data, provider outage/backoff, stale/expired data, malformed responses, radar URL validation, inactive provinces and coordinate precedence. Feature checks use fake HTTP providers and the isolated test database. Live browser verification should additionally check rapid province changes, playback, day selection, homepage navigation, mobile overflow and console errors. Browser verification was unavailable for the v2 redesign because the browser tool denied access; automated checks do not replace visual QA.
