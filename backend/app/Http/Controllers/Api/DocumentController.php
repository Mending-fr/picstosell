<?php

namespace App\Http\Controllers\Api;

use App\Services\Documents\VehicleDocuments;
use Illuminate\Support\Str;

/** Affiche prix, étiquette CO2 et fiche offre d'une voiture, en PDF. */
class DocumentController extends Controller
{
    public function show(int $id, string $type, VehicleDocuments $docs)
    {
        $v = $this->vehicle($id)->load('garage');
        $name = Str::slug(VehicleDocuments::TYPES[$type].' '.$v->plate).'.pdf';

        return response($docs->pdf($v, $type), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }
}
