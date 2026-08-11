<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('movies') || Schema::hasColumn('movies', 'genres')) {
            return;
        }

        Schema::table('movies', fn (Blueprint $table) => $table->jsonb('genres')->nullable());
    }

    public function down(): void
    {
        if (Schema::hasTable('movies') && Schema::hasColumn('movies', 'genres')) {
            Schema::table('movies', fn (Blueprint $table) => $table->dropColumn('genres'));
        }
    }
};
