<?php

namespace App\Services\Ai;

use InvalidArgumentException;

class Generators
{
    public static function for(string $provider): ImageGenerator
    {
        return match ($provider) {
            'fake' => new FakeGenerator,
            'openai' => new OpenAiGenerator,
            'gemini' => new GeminiGenerator,
            'fal' => new FalGenerator,
            default => throw new InvalidArgumentException("Fournisseur inconnu : {$provider}"),
        };
    }
}
