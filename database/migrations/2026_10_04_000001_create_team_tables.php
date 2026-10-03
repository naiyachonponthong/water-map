<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('type', 20)->default('foundation');
            $table->foreignId('leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone', 20)->nullable()->comment('เบอร์กลางของทีม');
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete()->comment('พื้นที่ประจำ');
            $table->string('base_address', 255)->nullable();
            $table->decimal('base_lat', 10, 7)->nullable();
            $table->decimal('base_lng', 10, 7)->nullable();
            $table->string('status', 20)->default('available')->comment('available, busy, resting, offline');
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['province_id', 'status']);
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete()->comment('หนึ่งคนอยู่ได้ทีมเดียว');
            $table->string('role_in_team', 20)->default('member')->comment('leader, member');
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->comment('boat, high_truck, ambulance, pickup, drone, other');
            $table->string('name', 120);
            $table->string('plate', 40)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable()->comment('จำนวนคนที่บรรทุกได้');
            $table->string('status', 20)->default('ready')->comment('ready, in_use, maintenance');
            $table->timestamps();
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('offered')->comment('offered, accepted, en_route, on_site, done, declined, cancelled, expired');
            $table->string('via', 20)->default('dispatch')->comment('dispatch, self, phone');
            $table->unsignedInteger('distance_m')->nullable();
            $table->unsignedSmallInteger('eta_minutes')->nullable();
            $table->string('decline_reason', 255)->nullable();
            $table->string('note', 500)->nullable();
            $table->unsignedSmallInteger('people_rescued')->nullable();
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['help_request_id', 'status']);
        });

        Schema::table('help_requests', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('subdistrict_id')->constrained()->nullOnDelete();
            $table->foreignId('assignment_id')->nullable()->after('team_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('help_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignment_id');
            $table->dropConstrainedForeignId('team_id');
        });
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
    }
};
