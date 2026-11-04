<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un seul décor par showroom : c'est la voiture qui tourne, pas le fond.
 * On garde la photo d'un angle déjà réglé (avant gauche de préférence) comme décor unique « main ».
 * Les photos reçoivent un libellé (« Tableau de bord », « Coffre »…) et l'origine de leur classement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->string('label', 60)->nullable()->after('kind');
            $table->string('classified', 10)->nullable()->after('label');
        });

        $prefer = ['front_left', 'front_right', 'front', 'left', 'right', 'rear'];
        foreach (DB::table('showrooms')->pluck('id') as $showroomId) {
            $shots = DB::table('shots')->where('showroom_id', $showroomId)->get();
            if ($shots->isEmpty()) {
                continue;
            }
            $keep = $shots->firstWhere('key', 'main')
                ?? collect($prefer)->map(fn ($k) => $shots->first(fn ($s) => $s->key === $k && $s->image_path))->filter()->first()
                ?? $shots->first(fn ($s) => $s->image_path)
                ?? $shots->first();
            DB::table('shots')->where('showroom_id', $showroomId)->where('id', '!=', $keep->id)->delete();
            DB::table('shots')->where('id', $keep->id)->update([
                'key' => 'main',
                'label' => 'Le showroom',
                'position' => 0,
                'placement' => json_encode(['height' => 42, 'bottom' => 12, 'offset_x' => 0]),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn(['label', 'classified']);
        });
    }
};
