<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schools') || Schema::hasColumn('schools', 'mail_from')) {
            return;
        }

        Schema::table('schools', function (Blueprint $table): void {
            $table->string('mail_from')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('schools') || ! Schema::hasColumn('schools', 'mail_from')) {
            return;
        }

        Schema::table('schools', function (Blueprint $table): void {
            $table->dropColumn('mail_from');
        });
    }
};
