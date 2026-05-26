<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamics_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->nullable()->constrained('attendance_sessions')->nullOnDelete();
            $table->string('integration')->default('attendance');
            $table->enum('direction', ['outbound', 'inbound'])->default('outbound');
            $table->enum('status', ['queued', 'synced', 'failed', 'retrying', 'skipped'])->default('queued');
            $table->string('external_reference')->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->unsignedInteger('retries')->default(0);
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamics_syncs');
    }
};
