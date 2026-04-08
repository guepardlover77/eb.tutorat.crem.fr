<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->index('deleted_at');
            $table->index('crem_number');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['crem_number']);
            $table->dropIndex(['email']);
        });
    }
};
