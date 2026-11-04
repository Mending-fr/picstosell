<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** « gpt-image-2.5 » n'existe pas chez OpenAI : les modèles 2.5 s'appellent gpt-image-2.5-flare et gpt-image-2.5-sunburst. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_configs')->where('model', 'gpt-image-2.5')->update(['model' => 'gpt-image-2.5-flare']);
        DB::table('ai_configs')->where('name', 'GPT Image 2.5, méthode B')->update(['name' => 'GPT Image 2.5 Flare, méthode B']);
    }

    public function down(): void
    {
        DB::table('ai_configs')->where('name', 'GPT Image 2.5 Flare, méthode B')->update(['name' => 'GPT Image 2.5, méthode B']);
        DB::table('ai_configs')->where('model', 'gpt-image-2.5-flare')->update(['model' => 'gpt-image-2.5']);
    }
};
