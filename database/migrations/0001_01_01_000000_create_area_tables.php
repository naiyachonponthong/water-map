<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->char('code', 2)->unique()->comment('รหัสจังหวัดกรมการปกครอง');
            $table->string('slug', 60)->unique()->comment('ใช้ใน URL เช่น chonburi');
            $table->string('name_th', 100);
            $table->string('name_en', 100)->nullable();
            $table->string('region', 30)->nullable();
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->unsignedTinyInteger('default_zoom')->default(10);
            $table->boolean('is_active')->default(true)->comment('แสดงในรายชื่อจังหวัดหน้าเว็บ');
            $table->boolean('command_open')->default(false)->comment('เปิดศูนย์สั่งการ');
            $table->boolean('web_help_open')->default(false)->comment('เปิดรับแจ้งขอความช่วยเหลือทางเว็บ');
            $table->timestamp('command_opened_at')->nullable();
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('code', 4)->nullable()->unique();
            $table->string('name_th', 100);
            $table->string('name_en', 100)->nullable();
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->longText('boundary')->nullable()->comment('GeoJSON geometry (Polygon/MultiPolygon)');
            $table->decimal('bbox_south', 10, 7)->nullable();
            $table->decimal('bbox_west', 10, 7)->nullable();
            $table->decimal('bbox_north', 10, 7)->nullable();
            $table->decimal('bbox_east', 10, 7)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['province_id', 'sort']);
        });

        Schema::create('subdistricts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->string('code', 6)->nullable()->unique();
            $table->string('name_th', 100);
            $table->string('name_en', 100)->nullable();
            $table->string('postcode', 5)->nullable();
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->longText('boundary')->nullable();
            $table->decimal('bbox_south', 10, 7)->nullable();
            $table->decimal('bbox_west', 10, 7)->nullable();
            $table->decimal('bbox_north', 10, 7)->nullable();
            $table->decimal('bbox_east', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['district_id', 'name_th']);
            $table->index(['province_id', 'bbox_south', 'bbox_north']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subdistricts');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('provinces');
    }
};
