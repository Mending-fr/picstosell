<?php

namespace App\Http\Controllers\Api;

use App\Services\Imaging;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GarageController extends Controller
{
    public function show()
    {
        return $this->garage()->toApi();
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'city' => 'sometimes|nullable|string|max:80',
            'phone' => 'sometimes|nullable|string|max:30',
            'email' => 'sometimes|nullable|email|max:120',
            'address' => 'sometimes|nullable|string|max:200',
            'hours' => 'sometimes|nullable|string|max:300',
            'about' => 'sometimes|nullable|string|max:3000',
            'brand_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings' => 'sometimes|array',
            'settings.hide_plate' => 'boolean',
            'settings.add_logo' => 'boolean',
            'settings.review_before_publish' => 'boolean',
            'settings.export_size' => 'in:marketplace,large',
        ]);
        $g = $this->garage();
        if (isset($data['settings'])) {
            $data['settings'] = array_merge($g->settings ?? [], $data['settings']);
        }
        $g->update($data);

        return $g->fresh()->toApi();
    }

    public function logo(Request $request)
    {
        $request->validate(['file' => 'required|file|image|max:5120']);
        $g = $this->garage();
        $img = Imaging::fit(Imaging::load($request->file('file')->get()), 800);
        $path = "garages/{$g->id}/logo-".Str::random(6).'.png';
        Media::put($path, Imaging::png($img), 'image/png');
        $g->update(['logo_path' => $path]);

        return $g->toApi();
    }
}
