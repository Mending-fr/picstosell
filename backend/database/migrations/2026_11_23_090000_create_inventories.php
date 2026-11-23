<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un tour du parc : une personne marche avec le téléphone, chaque image est lue pour y trouver des plaques.
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('running'); // running, done
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        Schema::create('inventory_frames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('status', 20)->default('pending'); // pending, done, failed
            $table->timestamps();
        });
        Schema::create('inventory_plates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->string('plate', 20);
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('frame_id')->nullable()->constrained('inventory_frames')->nullOnDelete();
            $table->unsignedSmallInteger('seen')->default(1);
            $table->boolean('sure')->default(false);   // au moins une lecture nette
            $table->boolean('dismissed')->default(false);
            $table->timestamps();
            $table->unique(['inventory_id', 'plate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_plates');
        Schema::dropIfExists('inventory_frames');
        Schema::dropIfExists('inventories');
    }
};
