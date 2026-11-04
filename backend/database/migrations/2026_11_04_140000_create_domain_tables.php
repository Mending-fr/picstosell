<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider');           // fake, openai, gemini, fal
            $table->string('model');              // identifiant du modèle chez le fournisseur
            $table->string('method', 1)->default('B'); // A génératif, B détourage + décor
            $table->string('quality', 4)->default('2K');
            $table->text('prompt')->nullable();
            $table->decimal('unit_cost_usd', 8, 4)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('garages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('onboarding'); // pilot, onboarding, demo, active, paused
            $table->string('city')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('hours')->nullable();
            $table->text('about')->nullable();
            $table->string('brand_color', 7)->default('#B8222A');
            $table->string('logo_path')->nullable();
            $table->string('domain')->nullable()->unique();
            $table->unsignedInteger('vehicle_quota')->nullable();
            $table->json('settings')->nullable();
            $table->foreignId('ai_config_id')->nullable()->constrained('ai_configs')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('showrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garage_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('shots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('showroom_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('image_path')->nullable();
            $table->json('placement')->nullable();
            $table->timestamps();
            $table->unique(['showroom_id', 'key']);
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garage_id')->constrained()->cascadeOnDelete();
            $table->string('plate', 20);
            $table->string('vin', 32)->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('version')->nullable();
            $table->date('first_registration')->nullable();
            $table->string('fuel', 30)->nullable();
            $table->string('gearbox', 20)->nullable();
            $table->unsignedSmallInteger('power_hp')->nullable();
            $table->unsignedSmallInteger('fiscal_hp')->nullable();
            $table->string('color')->nullable();
            $table->unsignedTinyInteger('doors')->nullable();
            $table->unsignedTinyInteger('seats')->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->unsignedInteger('price')->nullable();
            $table->text('description')->nullable();
            $table->json('equipment')->nullable();
            $table->string('history_url')->nullable();
            $table->string('status', 20)->default('to_photograph')->index();
            $table->foreignId('photographer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['garage_id', 'plate']);
        });

        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('shot_key')->nullable();
            $table->string('kind', 20)->default('exterior');
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('comparisons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('garage_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('running');
            $table->json('ai_config_ids');
            $table->json('vehicle_ids');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('renders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_config_id')->nullable()->constrained('ai_configs')->nullOnDelete();
            $table->foreignId('comparison_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('model');
            $table->string('method', 1);
            $table->string('status', 20)->default('pending')->index();
            $table->string('output_path')->nullable();
            $table->decimal('cost_usd', 8, 4)->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->string('rating', 20)->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('other');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('message')->nullable();
            $table->string('trade_in_plate')->nullable();
            $table->unsignedInteger('trade_in_mileage')->nullable();
            $table->string('status', 10)->default('new')->index();
            $table->text('note')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();
        });

        Schema::create('plate_lookups', function (Blueprint $table) {
            $table->id();
            $table->string('plate', 20)->unique();
            $table->string('provider');
            $table->boolean('found');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['plate_lookups', 'leads', 'renders', 'comparisons', 'photos', 'vehicles', 'shots', 'showrooms', 'garages', 'ai_configs'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
