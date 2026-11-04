<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Garage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GarageAdminController
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'city' => 'nullable|string|max:80',
            'status' => ['nullable', Rule::in(Garage::STATUSES)],
            'vehicle_quota' => 'nullable|integer|min:0',
        ]);
        $g = Garage::create($data + ['status' => $data['status'] ?? 'onboarding']);

        return response()->json($g->fresh()->toApi(), 201);
    }

    public function update(Request $request, Garage $garage)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'status' => ['sometimes', Rule::in(Garage::STATUSES)],
            'vehicle_quota' => 'sometimes|nullable|integer|min:0',
            'ai_config_id' => 'sometimes|nullable|exists:ai_configs,id',
            'domain' => ['sometimes', 'nullable', 'string', 'max:190', Rule::unique('garages', 'domain')->ignore($garage->id)],
        ]);
        $garage->update($data);

        return $garage->fresh()->toApi();
    }
}
