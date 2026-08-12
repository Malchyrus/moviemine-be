<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('custom_list_movies')) {
            Schema::create('custom_list_movies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('list_id')->constrained('custom_lists')->cascadeOnDelete();
                $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
                $table->integer('position')->default(0);
                $table->timestamps();

                $table->unique(['list_id', 'movie_id']);
                $table->index('movie_id');
            });
        } elseif (! Schema::hasColumn('custom_list_movies', 'position')) {
            Schema::table('custom_list_movies', function (Blueprint $table) {
                $table->integer('position')->default(0);
            });
        }

        if (
            Schema::hasTable('custom_lists')
            && Schema::hasColumn('custom_lists', 'is_default')
            && Schema::hasColumn('custom_lists', 'type')
            && Schema::hasColumn('custom_lists', 'position')
        ) {
            DB::table('custom_lists')
                ->where('is_default', false)
                ->whereNull('type')
                ->where('position', 0)
                ->whereIn('name', ['Plan to Watch', 'Watching', 'Watched', 'Dropped', 'On Hold'])
                ->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_list_movies');
    }
};
