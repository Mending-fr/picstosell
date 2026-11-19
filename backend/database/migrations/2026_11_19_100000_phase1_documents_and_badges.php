<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Carte grise
            $table->string('body_type', 10)->nullable()->after('seats');          // J.1 : VP, CTTE…
            $table->unsignedSmallInteger('co2')->nullable()->after('body_type');  // V.7, g/km
            $table->string('euro_norm', 40)->nullable()->after('co2');           // V.9 : euro 6, euro 5…
            $table->string('critair', 12)->nullable()->after('euro_norm');       // 0 à 5, non_classe
            $table->unsignedSmallInteger('empty_weight')->nullable()->after('critair'); // G.1, kg
            // Commerce
            $table->string('file_number', 30)->nullable()->after('plate');      // dossier VO du garage
            $table->string('origin', 40)->nullable()->after('history_url');     // particulier, reprise, loueur…
            $table->string('warranty', 60)->nullable()->after('origin');        // « OR 12 mois »
            $table->boolean('first_hand')->nullable()->after('warranty');
            $table->json('badges')->nullable()->after('first_hand');            // vignettes choisies
        });
        Schema::table('photos', function (Blueprint $table) {
            // Version publiée d'une photo gardée telle quelle (plaque cachée, logo).
            $table->string('published_path')->nullable()->after('thumb_path');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['body_type', 'co2', 'euro_norm', 'critair', 'empty_weight', 'file_number', 'origin', 'warranty', 'first_hand', 'badges']);
        });
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn('published_path');
        });
    }
};
