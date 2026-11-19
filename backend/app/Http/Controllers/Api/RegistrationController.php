<?php

namespace App\Http\Controllers\Api;

use App\Services\Imaging;
use App\Services\Vehicle\Emissions;
use App\Services\Vehicle\RegistrationReader;
use Carbon\Carbon;
use Illuminate\Http\Request;
use RuntimeException;

/** Lecture d'une photo de carte grise : renvoie les champs à reprendre dans la fiche, sans rien garder de l'image. */
class RegistrationController extends Controller
{
    public function read(Request $request, RegistrationReader $reader)
    {
        $request->validate(['file' => 'required|file|image|max:15360'], ['file.image' => 'Envoyez une photo de la carte grise.']);
        $bytes = Imaging::jpeg(Imaging::fit(Imaging::load($request->file('file')->get()), 2000), 90);
        try {
            $fields = $reader->read($bytes);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $first = isset($fields['first_registration']) ? Carbon::parse($fields['first_registration']) : null;

        return [
            'fields' => $fields,
            'critair' => Emissions::critair($fields['fuel'] ?? null, $fields['euro_norm'] ?? null, $first),
            'co2_class' => Emissions::co2Class($fields['co2'] ?? null),
        ];
    }
}
