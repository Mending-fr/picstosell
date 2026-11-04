<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $q = $this->garage()->leads()->with('vehicle')->latest();
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }

        return [
            'data' => $q->limit(300)->get()->map->toApi()->values(),
            'new_count' => $this->garage()->leads()->where('status', 'new')->count(),
        ];
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['new', 'done'])],
            'note' => 'sometimes|nullable|string|max:2000',
        ]);
        $lead = $this->garage()->leads()->findOrFail($id);
        $lead->update($data);

        return $lead->toApi();
    }
}
