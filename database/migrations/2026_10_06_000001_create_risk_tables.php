<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subdistrict_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->comment('flood_prone, road, hazard, landslide, facility, other');
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->longText('zone')->nullable()->comment('GeoJSON Polygon ถ้าเป็นโซน');
            $table->decimal('bbox_south', 10, 7)->nullable();
            $table->decimal('bbox_west', 10, 7)->nullable();
            $table->decimal('bbox_north', 10, 7)->nullable();
            $table->decimal('bbox_east', 10, 7)->nullable();
            $table->unsignedInteger('radius_m')->nullable()->comment('ว่าง = ใช้ค่าตั้งต้นของจังหวัด');
            $table->unsignedTinyInteger('trigger_level')->default(2)->comment('ระดับน้ำ 1-6 ที่ทำให้ขึ้นเตือน');
            $table->string('severity', 10)->default('medium')->comment('low, medium, high');
            $table->boolean('is_public')->default(true);
            $table->string('status', 12)->default('normal')->comment('normal, threatened, inactive');
            $table->string('review', 12)->default('approved')->comment('pending, approved, rejected');
            $table->string('source', 12)->default('staff')->comment('staff, import, citizen, system');
            $table->string('proposer_name', 120)->nullable();
            $table->text('proposer_phone')->nullable()->comment('เข้ารหัส');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('threatened_at')->nullable();
            $table->unsignedTinyInteger('threat_level')->nullable();
            $table->string('threat_reason', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['province_id', 'status']);
            $table->index(['province_id', 'review']);
            $table->index(['province_id', 'lat', 'lng']);
        });

        Schema::create('vulnerable_households', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subdistrict_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('head_name', 120)->comment('ชื่อผู้ที่ต้องดูแล หรือหัวหน้าครัวเรือน');
            $table->text('phone')->nullable()->comment('เข้ารหัส');
            $table->unsignedSmallInteger('members')->default(1);
            $table->json('conditions')->nullable();
            $table->text('note')->nullable();
            $table->string('caretaker_name', 120)->nullable()->comment('ผู้ดูแล / อสม. ประจำบ้าน');
            $table->text('caretaker_phone')->nullable()->comment('เข้ารหัส');
            $table->timestamp('consent_at')->nullable()->comment('วันที่ได้รับความยินยอมเก็บข้อมูล');
            $table->foreignId('consent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('open_case_id')->nullable()->constrained('help_requests')->nullOnDelete()->comment('เคสตรวจเยี่ยมเชิงรุกที่เปิดอยู่');
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_check_status', 20)->nullable()->comment('safe, needs_help, evacuated, not_home');
            $table->foreignId('last_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['province_id', 'is_active']);
            $table->index(['province_id', 'subdistrict_id']);
        });

        Schema::create('household_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vulnerable_household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20);
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // เจ้าหน้าที่ประกาศระดับน้ำรายตำบล (ใช้ก่อนมีรายงานจากประชาชน/สถานีวัดน้ำ)
        Schema::create('area_water_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subdistrict_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('note', 255)->nullable();
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->index(['province_id', 'expires_at']);
        });

        Schema::table('help_requests', function (Blueprint $table) {
            $table->foreignId('household_id')->nullable()->after('assignment_id')->constrained('vulnerable_households')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('help_requests', fn (Blueprint $t) => $t->dropConstrainedForeignId('household_id'));
        Schema::dropIfExists('area_water_levels');
        Schema::dropIfExists('household_checks');
        Schema::dropIfExists('vulnerable_households');
        Schema::dropIfExists('risk_points');
    }
};
