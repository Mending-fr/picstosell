<?php

namespace App\Http\Controllers\Api;

use App\Models\Garage;
use App\Models\Vehicle;

abstract class Controller
{
    protected function garage(): Garage
    {
        return app('garage');
    }

    protected function vehicle(int|string $id): Vehicle
    {
        return $this->garage()->vehicles()->findOrFail($id);
    }
}
