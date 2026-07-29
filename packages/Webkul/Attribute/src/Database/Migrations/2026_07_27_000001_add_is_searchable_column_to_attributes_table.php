<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attributes', 'is_searchable')) {
            Schema::table('attributes', function (Blueprint $table) {
                $table->boolean('is_searchable')->default(0)->after('is_filterable');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attributes', 'is_searchable')) {
            Schema::table('attributes', function (Blueprint $table) {
                $table->dropColumn('is_searchable');
            });
        }
    }
};
