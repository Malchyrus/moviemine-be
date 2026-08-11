<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('custom_lists')) {
            return;
        }

        if (! Schema::hasColumn('custom_lists', 'position')) {
            Schema::table('custom_lists', fn (Blueprint $table) => $table->integer('position')->default(0));
        }

        if (! Schema::hasColumn('custom_lists', 'is_default')) {
            Schema::table('custom_lists', fn (Blueprint $table) => $table->boolean('is_default')->default(false));
        }

        if (! Schema::hasColumn('custom_lists', 'type')) {
            Schema::table('custom_lists', fn (Blueprint $table) => $table->string('type', 20)->nullable());
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('custom_lists')) {
            return;
        }

        Schema::table('custom_lists', function (Blueprint $table) {
            if (Schema::hasColumn('custom_lists', 'position')) {
                $table->dropColumn('position');
            }
            if (Schema::hasColumn('custom_lists', 'is_default')) {
                $table->dropColumn('is_default');
            }
            if (Schema::hasColumn('custom_lists', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
