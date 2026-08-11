<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'preferences')) {
            return;
        }

        Schema::table('users', fn (Blueprint $table) => $table->jsonb('preferences')->nullable());
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'preferences')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('preferences'));
        }
    }
};
