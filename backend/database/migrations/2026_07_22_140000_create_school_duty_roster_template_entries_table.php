<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_duty_roster_template_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('location')->nullable();
            $table->string('time_slot')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['school_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_duty_roster_template_entries');
    }
};
