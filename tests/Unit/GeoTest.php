<?php

namespace Tests\Unit;

use App\Support\Geo;
use App\Support\Theme;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    private array $square = ['type' => 'Polygon', 'coordinates' => [
        [[100, 13], [101, 13], [101, 14], [100, 14], [100, 13]],
        [[100.4, 13.4], [100.6, 13.4], [100.6, 13.6], [100.4, 13.6], [100.4, 13.4]],
    ]];

    public function test_bbox(): void
    {
        $this->assertSame([100.0, 13.0, 101.0, 14.0], Geo::bbox($this->square));
    }

    public function test_point_in_polygon_respects_holes(): void
    {
        $this->assertTrue(Geo::pointInGeometry(13.2, 100.2, $this->square));
        $this->assertFalse(Geo::pointInGeometry(13.5, 100.5, $this->square));
        $this->assertFalse(Geo::pointInGeometry(15, 100.5, $this->square));
    }

    public function test_parse_lat_lng_from_links(): void
    {
        $this->assertSame([13.6904, 101.078], Geo::parseLatLng('https://www.google.com/maps/@13.6904,101.078,15z'));
        $this->assertSame([13.69, 101.07], Geo::parseLatLng('https://maps.google.com/?q=13.69,101.07'));
        $this->assertSame([13.6904, 101.078], Geo::parseLatLng('13.6904, 101.0780'));
        $this->assertNull(Geo::parseLatLng('ไม่มีพิกัด'));
    }

    public function test_distance(): void
    {
        $d = Geo::distance(13.6904, 101.0780, 13.3611, 100.9847);
        $this->assertEqualsWithDelta(37980, $d, 100);
    }

    public function test_theme_mix(): void
    {
        $this->assertSame('#ffffff', Theme::mix('#000000', '#ffffff', 1));
        $this->assertSame('#1565C0', Theme::normalize('1565c0'));
        $this->assertSame('#1565C0', Theme::normalize('not-a-color'));
    }
}
