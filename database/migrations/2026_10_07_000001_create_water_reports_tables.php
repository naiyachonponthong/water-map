<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subdistrict_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedInteger('accuracy_m')->nullable();
            $table->unsignedTinyInteger('level')->comment('ระดับน้ำ 1-6');
            $table->string('trend', 10)->nullable()->comment('rising, steady, falling');
            $table->string('place_type', 12)->nullable()->comment('road, house, field, canal, other');
            $table->string('note', 500)->nullable();
            $table->json('photos')->nullable();
            $table->string('reporter_name', 120)->nullable();
            $table->text('reporter_phone')->nullable()->comment('เข้ารหัส');
            $table->string('device_hash', 64)->nullable()->index();
            $table->string('ip_hash', 64)->nullable();
            $table->string('source', 10)->default('web')->comment('web, staff, team, line');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12)->default('published')->comment('pending, published, hidden, rejected');
            $table->boolean('verified')->default(false)->comment('เจ้าหน้าที่/ทีมยืนยันแล้ว');
            $table->unsignedSmallInteger('confirm_count')->default(0);
            $table->unsignedSmallInteger('recede_count')->default(0);
            $table->unsignedSmallInteger('wrong_count')->default(0);
            $table->unsignedSmallInteger('update_count')->default(0)->comment('ผู้แจ้งคนเดิมอัปเดตจุดเดิม');
            $table->boolean('outside_province')->default(false);
            $table->string('hide_reason', 255)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->dateTime('expires_at')->index();
            $table->timestamps();

            $table->index(['province_id', 'status', 'expires_at']);
            $table->index(['province_id', 'lat', 'lng']);
        });

        Schema::create('water_report_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_report_id')->constrained()->cascadeOnDelete();
            $table->string('voter_hash', 64);
            $table->string('kind', 10)->comment('confirm, receded, wrong');
            $table->timestamp('created_at')->nullable();

            $table->unique(['water_report_id', 'voter_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_report_votes');
        Schema::dropIfExists('water_reports');
    }
};
