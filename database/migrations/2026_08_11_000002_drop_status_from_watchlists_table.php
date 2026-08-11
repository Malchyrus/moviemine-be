<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('watchlists') || ! Schema::hasColumn('watchlists', 'status')) {
            return;
        }

        Schema::table('watchlists', fn (Blueprint $table) => $table->dropColumn('status'));
    }

    public function down(): void
    {
        if (Schema::hasTable('watchlists') && ! Schema::hasColumn('watchlists', 'status')) {
            Schema::table('watchlists', function (Blueprint $table) {
                $table->string('status', 20)->default('planning')
                    ->check("status IN ('planning', 'watching', 'completed', 'dropped', 'on_hold')");
            });
        }
    }
};
