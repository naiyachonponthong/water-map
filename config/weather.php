<?php

return [
    // Optional local CA bundle for PHP installations without a configured trust store.
    // Never disable TLS verification for these public providers.
    'ca_bundle' => env('WEATHER_CA_BUNDLE') ?: true,
    // Verified provincial city coordinates from Open-Meteo / GeoNames.
    // Weather-only fallback: does not alter operational boundaries or alert settings.
    'city_centers' => require __DIR__.'/weather-city-centers.php',
];
