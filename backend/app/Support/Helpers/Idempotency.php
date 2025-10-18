<?php

declare(strict_types=1);

namespace App\Support\Helpers;

use Illuminate\Http\Request;

/**
 * Helper simples para chaves de idempotência.
 * Estratégia:
 * - header 'Idempotency-Key' se existir;
 * - caso contrário, hash de método + path + body normalizado.
 */
final class Idempotency
{
    public static function keyFromRequest(Request $request): string
    {
        $explicit = $request->headers->get('Idempotency-Key');
        if ($explicit) {
            return 'idem:'.hash('sha256', $explicit);
        }

        $payload = [
            'm' => $request->getMethod(),
            'u' => $request->getPathInfo(),
            'q' => $request->query(),
            'b' => $request->all(),
        ];

        return 'idem:'.hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }
}
