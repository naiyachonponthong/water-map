<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shelters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subdistrict_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('type', 12)->default('other')->comment('temple, school, hall, gov, other');
            $table->string('address', 255)->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedInteger('capacity')->default(0);
            $table->unsignedInteger('occupancy')->default(0)->comment('คำนวณจากผู้อพยพที่ยังอยู่');
            $table->string('status', 10)->default('preparing')->comment('preparing, open, full, closed');
            $table->json('facilities')->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['province_id', 'status']);
        });

        Schema::create('shelter_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shelter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['shelter_id', 'user_id']);
        });

        Schema::create('evacuees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shelter_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('family_code', 20)->nullable()->index()->comment('คนในครอบครัวเดียวกันใช้รหัสเดียวกัน');
            $table->string('name', 120);
            $table->text('phone')->nullable()->comment('เข้ารหัส');
            $table->string('phone_hash', 64)->nullable()->index();
            $table->string('age_group', 10)->nullable()->comment('infant, child, adult, elderly');
            $table->string('gender', 10)->nullable();
            $table->json('needs')->nullable()->comment('medical, disabled, pregnant, bedridden, infant_care, pets');
            $table->string('address', 255)->nullable()->comment('ที่อยู่เดิม');
            $table->text('note')->nullable();
            $table->boolean('allow_lookup')->default(true)->comment('ยินยอมให้ญาติค้นหาด้วยเบอร์โทร');
            $table->string('status', 12)->default('in')->comment('in, out, transferred');
            $table->foreignId('help_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vulnerable_household_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();
            $table->string('out_reason', 120)->nullable();
            $table->timestamps();

            $table->index(['shelter_id', 'status']);
        });

        Schema::create('supply_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('category', 12)->default('other')->comment('food, water, medicine, hygiene, baby, bedding, other');
            $table->string('unit', 20)->default('ชิ้น');
            $table->unsignedInteger('min_stock')->default(0)->comment('ต่ำกว่านี้เตือน (ต่อคลัง)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['province_id', 'name']);
        });

        Schema::create('supply_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supply_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shelter_id')->nullable()->constrained()->nullOnDelete()->comment('null = คลังกลาง');
            $table->integer('qty')->comment('+ รับเข้า, - จ่ายออก');
            $table->string('kind', 12)->comment('in, out, transfer_in, transfer_out, adjust');
            $table->string('ref', 40)->nullable()->comment('ใช้จับคู่รายการโอน');
            $table->string('donor', 150)->nullable();
            $table->foreignId('help_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['supply_item_id', 'shelter_id']);
        });

        Schema::create('shelter_needs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shelter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supply_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item', 120);
            $table->unsignedInteger('qty')->nullable();
            $table->string('unit', 20)->nullable();
            $table->string('priority', 8)->default('normal')->comment('normal, urgent');
            $table->string('status', 10)->default('open')->comment('open, fulfilled');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alert_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->string('level', 10)->default('info')->comment('info, warning, urgent');
            $table->string('audience', 10)->default('public')->comment('public, staff');
            $table->json('district_ids')->nullable();
            $table->boolean('pinned')->default(false);
            $table->boolean('send_line')->default(false);
            $table->string('line_status', 10)->nullable()->comment('pending, sent, failed, skipped');
            $table->string('line_error', 255)->nullable();
            $table->unsignedInteger('line_recipients')->nullable();
            $table->timestamp('line_sent_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['province_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('shelter_needs');
        Schema::dropIfExists('supply_movements');
        Schema::dropIfExists('supply_items');
        Schema::dropIfExists('evacuees');
        Schema::dropIfExists('shelter_staff');
        Schema::dropIfExists('shelters');
    }
};
