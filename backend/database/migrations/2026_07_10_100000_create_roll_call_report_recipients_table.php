<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roll_call_report_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('label')->nullable();
            $table->string('grade_level')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'grade_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roll_call_report_recipients');
    }
};
