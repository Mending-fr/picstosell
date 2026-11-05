<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Décors proposés par Mending à tous les garages (un par défaut), et rendus d'essai
 * qui ne remplacent pas les photos de la voiture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backdrops', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('image_path');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
        Schema::table('renders', function (Blueprint $table) {
            $table->boolean('is_test')->default(false)->after('comparison_id');
            $table->string('backdrop', 20)->nullable()->after('is_test');
        });
    }

    public function down(): void
    {
        Schema::table('renders', function (Blueprint $table) {
            $table->dropColumn(['is_test', 'backdrop']);
        });
        Schema::dropIfExists('backdrops');
    }
};
