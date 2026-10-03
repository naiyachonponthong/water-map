<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // เลขรันนิ่ง (ใช้ lockForUpdate) เช่น เลขเคส FL24-6910-0001
        Schema::create('running_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->unsignedInteger('value')->default(0);
            $table->timestamps();
        });

        Schema::create('help_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subdistrict_id')->nullable()->constrained()->nullOnDelete();

            // ตำแหน่ง
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('location_source', 20)->default('gps')->comment('gps, map, link, staff');
            $table->string('location_raw', 500)->nullable()->comment('ลิงก์/ข้อความพิกัดที่ผู้แจ้งวาง');
            $table->unsignedSmallInteger('accuracy_m')->nullable();
            $table->boolean('outside_province')->default(false);
            $table->string('address_text', 255)->nullable();
            $table->string('landmark', 255)->nullable();
            $table->unsignedTinyInteger('floor_level')->nullable()->comment('อยู่ชั้นที่');

            // สถานการณ์
            $table->unsignedTinyInteger('water_level')->comment('1-6');
            $table->unsignedSmallInteger('people_count')->default(1);
            $table->json('vulnerable')->nullable();
            $table->json('needs')->nullable();
            $table->text('needs_note')->nullable();
            $table->json('photos')->nullable();

            // ผู้แจ้ง / ผู้ติดต่อในพื้นที่
            $table->string('requester_name', 120);
            $table->text('requester_phone')->comment('เข้ารหัส');
            $table->char('phone_hash', 64)->index();
            $table->char('phone_last4', 4);
            $table->boolean('on_behalf')->default(false)->comment('แจ้งแทนคนอื่น');
            $table->string('contact_name', 120)->nullable();
            $table->text('contact_phone')->nullable()->comment('เข้ารหัส');

            // สถานะ
            $table->string('source', 10)->default('web')->comment('web, phone, line, staff');
            $table->string('status', 20)->default('new');
            $table->unsignedSmallInteger('priority_score')->default(0);
            $table->string('priority', 10)->default('medium');
            $table->boolean('priority_locked')->default(false)->comment('เจ้าหน้าที่กำหนดเอง ไม่คำนวณใหม่');
            $table->foreignId('duplicate_of_id')->nullable()->constrained('help_requests')->nullOnDelete();
            $table->foreignId('possible_duplicate_of_id')->nullable()->constrained('help_requests')->nullOnDelete();
            $table->unsignedSmallInteger('report_count')->default(1);
            $table->string('outcome', 20)->nullable();
            $table->unsignedSmallInteger('people_rescued')->nullable();
            $table->string('close_note', 255)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('screened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('screened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('requester_updated_at')->nullable();
            $table->string('device_hash', 64)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['province_id', 'status', 'priority_score']);
            $table->index(['province_id', 'lat', 'lng']);
            $table->index(['province_id', 'created_at']);
        });

        Schema::create('help_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor', 12)->default('staff')->comment('staff, requester, system');
            $table->string('type', 20)->comment('created, status, note, update, merged, priority, call, edit');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->json('data')->nullable();
            $table->boolean('public')->default(false)->comment('แสดงในหน้าติดตามของผู้แจ้ง');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['help_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_request_events');
        Schema::dropIfExists('help_requests');
        Schema::dropIfExists('running_numbers');
    }
};
