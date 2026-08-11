<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_lists', function (Blueprint $table) {
            $table->integer('position')->default(0);
            $table->boolean('is_default')->default(false);
            $table->string('type', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('custom_lists', function (Blueprint $table) {
            $table->dropColumn(['position', 'is_default', 'type']);
        });
    }
};
