<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Support\DataSourceHealth;
use App\Support\PublicAreaReference;
use App\Support\PublicWeatherService;
use Illuminate\Http\Request;
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

        $areas = app(PublicAreaReference::class)->areas($province);

        return view('public.weather-dashboard', compact('province', 'boundaries', 'center', 'locations', 'areas'));
    }

    public function context(Province $province, PublicWeatherService $weather)
    {
        abort_unless($province->is_active, 404);

        return response()->json([
            'location' => $this->location($province, $weather),
            'areas' => app(PublicAreaReference::class)->areas($province),
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

    public function forecast(Request $request, Province $province, PublicWeatherService $weather)
    {
        abort_unless($province->is_active, 404);
        $input = $request->validate(['district' => 'nullable|string|max:12', 'subdistrict' => 'nullable|string|max:12']);
        $point = null;
        if (! empty($input['district']) || ! empty($input['subdistrict'])) {
            $area = collect(app(PublicAreaReference::class)->areas($province))->firstWhere('code', $input['district'] ?? '');
            $sub = collect($area['subdistricts'] ?? [])->firstWhere('code', $input['subdistrict'] ?? '');
            abort_unless($area && $sub && $sub['center'], 422, 'กรุณาเลือกตำบลในจังหวัดที่มีพิกัดพยากรณ์');
            $point = ['center' => $sub['center'], 'name' => $sub['name'].' · '.$area['name'].' · '.$province->name_th];
        }
        if (! $point && ! $weather->center($province)) {
            app(DataSourceHealth::class)->failure('forecast', $province->id);

            return response()->json(['message' => 'ยังไม่ได้กำหนดพิกัดสำหรับพยากรณ์จังหวัดนี้'], 422);
        }
        try {
            return response()->json($weather->forecast($province, $point))->header('Cache-Control', 'no-store');
        } catch (Throwable $e) {
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
