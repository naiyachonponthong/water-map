<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ทีมขอกำลังเสริม / ทีมเกิดเหตุเอง
        Schema::create('team_sos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('help_request_id')->nullable()->constrained()->nullOnDelete()->comment('งานที่ทำอยู่ตอนกด');
            $table->string('kind', 20)->default('backup')->comment('backup, injured, boat_trouble, other');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('status', 12)->default('open')->comment('open, ack, resolved');
            $table->foreignId('acked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acked_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolve_note', 500)->nullable();
            $table->timestamps();

            $table->index(['province_id', 'status']);
        });

        // กันการกดซ้ำเมื่อแอปภาคสนามส่งคิวที่ค้างตอนออฟไลน์ (ส่งซ้ำได้ ทำงานครั้งเดียว)
        Schema::create('field_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('key')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->boolean('ok')->default(true);
            $table->timestamp('client_at')->nullable()->comment('เวลาที่กดบนเครื่อง');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_actions');
        Schema::dropIfExists('team_sos');
    }
};
