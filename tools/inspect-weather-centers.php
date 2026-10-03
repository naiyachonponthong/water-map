<?php
// Read-only provenance check for the bundled weather reference points.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$aliases = ['10' => 'Bangkok', '14' => 'Phra Nakhon Si Ayutthaya', '20' => 'Chon Buri', '31' => 'Buri Ram', '33' => 'Si Sa Ket'];
foreach (Database\Seeders\ProvinceSeeder::PROVINCES as [$code, $slug, $th, $en]) {
    try {
        $data = Illuminate\Support\Facades\Http::withOptions(['verify' => config('weather.ca_bundle')])->timeout(10)
            ->get('https://geocoding-api.open-meteo.com/v1/search', ['name' => $aliases[$code] ?? $en, 'count' => 30, 'countryCode' => 'TH', 'language' => 'en'])->throw()->json();
        $candidates = array_values(array_filter($data['results'] ?? [], fn ($r) => in_array($r['feature_code'] ?? '', ['PPLA', 'PPLC'], true)));
        echo json_encode(['code' => $code, 'province' => $en, 'cities' => array_map(fn ($r) => [$r['name'], $r['latitude'], $r['longitude'], $r['id'], $r['admin1'] ?? ''], $candidates)], JSON_UNESCAPED_UNICODE).PHP_EOL;
    } catch (Throwable $e) {
        echo json_encode(['code' => $code, 'error' => $e->getMessage()]).PHP_EOL;
    }
}
