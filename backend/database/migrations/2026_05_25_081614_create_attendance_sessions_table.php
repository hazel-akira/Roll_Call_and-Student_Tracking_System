<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->date('session_date');
            $table->timestamp('started_at');
            $table->timestamp('closed_at')->nullable();
            $table->enum('status', ['draft', 'open', 'closed'])->default('open');
            $table->string('source')->default('web');
            $table->enum('dynamics_sync_status', ['pending', 'queued', 'synced', 'failed', 'skipped'])->default('pending');
            $table->timestamps();

            $table->index(['session_date', 'class_id']);
            $table->index(['teacher_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
