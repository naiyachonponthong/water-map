<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->nullable()->constrained()->cascadeOnDelete()->comment('null = ค่าระดับระบบ');
            $table->string('key', 80);
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['province_id', 'key']);
        });

        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('phone', 30);
            $table->string('note')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('external_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->nullable()->constrained()->cascadeOnDelete()->comment('null = แสดงทุกจังหวัด');
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('url', 500);
            $table->string('source_name', 100)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40)->comment('created, updated, deleted, login, approved ...');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['province_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('external_links');
        Schema::dropIfExists('emergency_contacts');
        Schema::dropIfExists('settings');
    }
};
