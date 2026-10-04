<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Support\PublicWeatherService;
use Throwable;

class WeatherController extends Controller
{
    public function index(Province $province, PublicWeatherService $weather)
    {
        abort_unless($province->is_active, 404);
        $center = $weather->center($province);
        $locations = Province::where('is_active', true)->orderBy('name_th')->get()
            ->map(fn ($p) => $this->location($p, $weather))->values()->all();
        $boundaries = ['type' => 'FeatureCollection', 'features' => $province->districts()->whereNotNull('boundary')->get()
            ->map(fn ($d) => ['type' => 'Feature', 'geometry' => $d->boundaryArray(), 'properties' => ['name' => $d->name_th]])->all()];

        return view('public.weather-dashboard', compact('province', 'boundaries', 'center', 'locations'));
    }

    public function context(Province $province, PublicWeatherService $weather)
    {
        abort_unless($province->is_active, 404);

        return response()->json([
            'location' => $this->location($province, $weather),
            'boundaries' => ['type' => 'FeatureCollection', 'features' => $province->districts()->whereNotNull('boundary')->get()
                ->map(fn ($d) => ['type' => 'Feature', 'geometry' => $d->boundaryArray(), 'properties' => ['name' => $d->name_th]])->all()],
        ]);
    }

    private function location(Province $province, PublicWeatherService $weather): array
    {
        return ['slug' => $province->slug, 'name' => $province->name_th, 'center' => $weather->center($province),
            'url' => route('public.weather', $province, false), 'homeUrl' => route('public.province', $province, false),
            'forecastUrl' => route('public.weather.forecast', $province, false),
            'radarUrl' => route('public.weather.radar', $province, false),
            'contextUrl' => route('public.weather.context', $province, false)];
    }

    public function forecast(Province $province, PublicWeatherService $weather)
    {
        abort_unless($province->is_active, 404);
        if (! $weather->center($province)) {
            app(\App\Support\DataSourceHealth::class)->failure('forecast', $province->id);
            return response()->json(['message' => 'ยังไม่ได้กำหนดพิกัดสำหรับพยากรณ์จังหวัดนี้'], 422);
        }
        try {
            return response()->json($weather->forecast($province))->header('Cache-Control', 'no-store');
        } catch (Throwable $e) {
            app(\App\Support\DataSourceHealth::class)->failure('forecast', $province->id);
            return response()->json(['message' => 'ยังโหลดพยากรณ์ไม่ได้ กรุณาลองใหม่ภายหลัง'], 503)
                ->header('Retry-After', '60')->header('Cache-Control', 'no-store');
        }
    }

    public function radar(Province $province, PublicWeatherService $weather)
    {
        abort_unless($province->is_active, 404);
        try {
            return response()->json($weather->radar())->header('Cache-Control', 'no-store');
        } catch (Throwable $e) {
            return response()->json(['message' => 'ยังโหลดเรดาร์ไม่ได้ กรุณาลองใหม่ภายหลัง'], 503)
                ->header('Retry-After', '60')->header('Cache-Control', 'no-store');
        }
    }
}
