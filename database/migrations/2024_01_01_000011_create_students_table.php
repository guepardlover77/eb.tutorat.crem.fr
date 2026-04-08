<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('helloasso_item_id')->unique();
            $table->unsignedBigInteger('helloasso_order_id')->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 150);
            $table->string('tier_name', 200);
            $table->string('crem_number', 10)->nullable();
            $table->text('crem_photo_url')->nullable();
            $table->boolean('is_excluded')->default(false);
            $table->boolean('has_error')->default(false);
            $table->text('error_message')->nullable();
            $table->foreignId('amphitheater_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('amphitheater_id');
            $table->index('has_error');
            $table->index('is_excluded');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
