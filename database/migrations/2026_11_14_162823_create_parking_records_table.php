<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parking_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_area_id')->constrained('parking_areas')->restrictOnDelete();
            $table->string('plate_number', 20);
            $table->string('vehicle_type', 10); // motor | mobil
            $table->dateTime('check_in_at');
            $table->dateTime('check_out_at')->nullable();
            $table->unsignedInteger('fee')->nullable();
            $table->string('status', 10)->default('ACTIVE'); // ACTIVE | DONE
            $table->timestamps();

            $table->index(['plate_number', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parking_records');
    }
};
