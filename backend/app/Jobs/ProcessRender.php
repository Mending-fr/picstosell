<?php

namespace App\Jobs;

use App\Models\Render;
use App\Services\RenderPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessRender implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $renderId) {}

    public function handle(RenderPipeline $pipeline): void
    {
        $render = Render::find($this->renderId);
        if ($render && in_array($render->status, ['pending', 'processing'], true)) {
            $pipeline->run($render);
        }
    }
}
