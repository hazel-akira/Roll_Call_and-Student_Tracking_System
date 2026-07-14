<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_roll_call_settings', function (Blueprint $table): void {
            $table->foreignId('school_id')->primary()->constrained()->cascadeOnDelete();
            $table->boolean('notify_school_admins')->default(true);
            $table->boolean('notify_homeroom_teacher')->default(true);
            $table->boolean('notify_grade_master')->default(true);
            $table->boolean('notify_session_teacher')->default(true);
            $table->boolean('notify_duty_roster')->default(true);
            $table->boolean('assigned_recipients_only')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_roll_call_settings');
    }
};
