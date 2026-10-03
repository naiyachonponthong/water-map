<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_claims', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subdistrict_id')->nullable()->constrained()->nullOnDelete();
            $table->string('head_name', 120);
            $table->text('phone')->comment('เข้ารหัส');
            $table->string('phone_hash', 64)->index();
            $table->string('address', 255);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedSmallInteger('members')->default(1);
            $table->string('tenure', 10)->default('own')->comment('own, rent, other');
            $table->string('house_damage', 12)->default('none')->comment('none, minor, major, destroyed');
            $table->unsignedTinyInteger('water_level')->nullable()->comment('1-6 ที่ท่วมสูงสุด');
            $table->unsignedSmallInteger('flood_days')->nullable();
            $table->json('losses')->nullable()->comment('furniture, appliances, vehicle, crops, livestock, business, tools');
            $table->decimal('crop_rai', 8, 2)->nullable();
            $table->unsignedInteger('livestock')->nullable();
            $table->json('photos')->nullable();
            $table->text('note')->nullable();
            $table->string('source', 10)->default('web')->comment('web, staff');
            $table->string('status', 12)->default('submitted')->comment('submitted, surveying, surveyed, approved, paid, rejected');
            $table->json('evidence')->nullable()->comment('หลักฐานที่ระบบตรวจพบเอง');
            $table->unsignedTinyInteger('evidence_score')->default(0);
            $table->foreignId('help_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('surveyor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('surveyed_at')->nullable();
            $table->string('verified_damage', 12)->nullable();
            $table->text('survey_note')->nullable();
            $table->json('survey_photos')->nullable();
            $table->decimal('suggested_amount', 12, 2)->nullable();
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->string('payment_ref', 60)->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('reject_reason', 255)->nullable();
            $table->string('device_hash', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['province_id', 'status']);
        });

        Schema::create('claim_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('note', 500)->nullable();
            $table->boolean('public')->default(true)->comment('ผู้ยื่นเห็นในหน้าติดตาม');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_events');
        Schema::dropIfExists('damage_claims');
    }
};
