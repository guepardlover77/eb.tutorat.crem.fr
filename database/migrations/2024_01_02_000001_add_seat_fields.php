<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedInteger('seat_number')->nullable()->after('amphitheater_id');
        });

        Schema::table('amphitheaters', function (Blueprint $table) {
            $table->json('seat_layout')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('seat_number');
        });
        Schema::table('amphitheaters', function (Blueprint $table) {
            $table->dropColumn('seat_layout');
        });
    }
};
