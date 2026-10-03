<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subdistrict_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 40)->nullable();
            $table->string('name', 150);
            $table->string('river', 120)->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('unit', 20)->default('ม.รทก.')->comment('หน่วยที่แสดง');
            $table->decimal('bank_level', 8, 2)->nullable()->comment('ระดับตลิ่ง');
            $table->decimal('watch_level', 8, 2)->nullable()->comment('เฝ้าระวัง');
            $table->decimal('warning_level', 8, 2)->nullable()->comment('เตือนภัย');
            $table->decimal('critical_level', 8, 2)->nullable()->comment('วิกฤต');
            $table->unsignedInteger('influence_radius_m')->default(3000)->comment('รัศมีที่ใช้เตือนจุดเสี่ยง');
            $table->string('fetch_mode', 10)->default('manual')->comment('manual, json');
            $table->string('fetch_url', 500)->nullable();
            $table->string('value_path', 120)->nullable()->comment('เช่น data.0.waterlevel_msl');
            $table->string('time_path', 120)->nullable();
            $table->decimal('value_offset', 8, 2)->default(0)->comment('บวกเพิ่มหลังอ่านค่า (แปลงหน่วย/ฐาน)');
            $table->string('link_url', 500)->nullable()->comment('ลิงก์หน้าเว็บต้นทาง');
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->decimal('last_value', 8, 2)->nullable();
            $table->timestamp('last_at')->nullable();
            $table->string('status', 10)->default('unknown')->comment('unknown, normal, watch, warning, critical, offline');
            $table->decimal('trend_per_hour', 8, 3)->nullable()->comment('ซม./ชม. เป็นเมตร');
            $table->string('fetch_error', 255)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['province_id', 'status']);
        });

        Schema::create('station_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_station_id')->constrained()->cascadeOnDelete();
            $table->decimal('value', 8, 2);
            $table->dateTime('measured_at');
            $table->string('source', 10)->default('manual')->comment('manual, json, import');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['water_station_id', 'measured_at']);
        });

        Schema::create('rain_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('rain_mm', 7, 1)->default(0);
            $table->unsignedTinyInteger('rain_prob')->nullable();
            $table->decimal('temp_max', 4, 1)->nullable();
            $table->string('source', 20)->default('open-meteo');
            $table->dateTime('fetched_at');

            $table->unique(['province_id', 'district_id', 'date']);
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('key', 120)->nullable()->comment('กันเตือนซ้ำ เช่น station:5, rain:2026-10-03:d12');
            $table->string('kind', 12)->comment('station, rain, risk, manual');
            $table->string('level', 10)->comment('watch, warning, critical');
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->json('district_ids')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('is_public')->default(true);
            $table->string('status', 10)->default('active')->comment('active, resolved');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['province_id', 'status']);
            $table->index(['province_id', 'key']);
        });

        Schema::create('cameras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('water_station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('type', 10)->default('image')->comment('image, hls, iframe, link');
            $table->string('url', 500);
            $table->unsignedSmallInteger('refresh_sec')->default(60);
            $table->string('owner', 120)->nullable()->comment('หน่วยงานเจ้าของกล้อง');
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cameras');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('rain_forecasts');
        Schema::dropIfExists('station_readings');
        Schema::dropIfExists('water_stations');
    }
};
