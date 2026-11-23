<?php

namespace App\Services\Ai;

use Anthropic\Client;
use RuntimeException;
use Throwable;

/**
 * Pose une question sur une image à Claude et reçoit une réponse JSON imposée par un schéma
 * (output_config.format). Les modèles récents refusent l'outil forcé (tool_choice) : c'est la façon fiable.
 */
class ClaudeJson
{
    public static function available(): bool
    {
        return (bool) config('services.anthropic.key');
    }

    /**
     * @param  array<string, mixed>  $schema  schéma JSON de la réponse (objet)
     * @return array<string, mixed>|null null si l'appel échoue ou si la réponse n'est pas du JSON valable
     */
    public static function ask(string $model, string $system, string $jpegBytes, string $question, array $schema, int $maxTokens = 2000, ?string $effort = null): ?array
    {
        if (! self::available()) {
            return null;
        }
        $output = ['format' => ['type' => 'json_schema', 'schema' => $schema]];
        if ($effort) {
            $output['effort'] = $effort;
        }
        try {
            $res = (new Client(apiKey: config('services.anthropic.key')))->messages->create(
                model: $model,
                maxTokens: $maxTokens,
                system: $system,
                outputConfig: $output,
                messages: [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/jpeg', 'data' => base64_encode($jpegBytes)]],
                        ['type' => 'text', 'text' => $question],
                    ],
                ]],
            );
        } catch (Throwable $e) {
            report($e);

            return null;
        }
        if ($res->stopReason === 'end_turn') {
            foreach ($res->content as $block) {
                if ($block->type === 'text') {
                    $data = json_decode($block->text, true);
                    if (is_array($data)) {
                        return $data;
                    }
                }
            }
        }
        report(new RuntimeException('Réponse IA sans JSON exploitable (stop_reason: '.$res->stopReason.')'));

        return null;
    }
}
