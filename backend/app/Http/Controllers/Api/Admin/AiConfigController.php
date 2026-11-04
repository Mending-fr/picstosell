<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\AiConfig;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiConfigController
{
    public function index()
    {
        return ['data' => AiConfig::orderBy('id')->get()->map->toApi()->values()];
    }

    public function store(Request $request)
    {
        return response()->json(AiConfig::create($this->rules($request, true))->toApi(), 201);
    }

    public function update(Request $request, AiConfig $aiConfig)
    {
        $aiConfig->update($this->rules($request, false));

        return $aiConfig->toApi();
    }

    private function rules(Request $request, bool $creating): array
    {
        $r = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'name' => "$r|string|max:120",
            'provider' => [$r, Rule::in(AiConfig::PROVIDERS)],
            'model' => "$r|string|max:160",
            'method' => [$r, Rule::in(['A', 'B'])],
            'quality' => ['sometimes', Rule::in(['1K', '2K'])],
            'prompt' => 'sometimes|nullable|string|max:4000',
            'unit_cost_usd' => 'sometimes|numeric|min:0|max:10',
            'active' => 'sometimes|boolean',
        ]);
    }
}
