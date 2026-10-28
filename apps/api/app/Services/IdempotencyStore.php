<?php

namespace App\Services;

use App\Exceptions\LedgerException;
use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class IdempotencyStore
{
    /**
     * @param  callable(): array{status: int, data: array<string, mixed>}  $callback
     * @return array{status: int, data: array<string, mixed>}
     */
    public function once(User $actor, string $operation, string $key, string $hash, callable $callback): array
    {
        return DB::transaction(function () use ($actor, $operation, $key, $hash, $callback) {
            $existing = IdempotencyKey::query()
                ->where('actor_id', $actor->id)
                ->where('operation', $operation)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->request_hash !== $hash) {
                    throw new LedgerException(
                        'idempotency-conflict',
                        'This Idempotency-Key was already used with a different request.',
                    );
                }

                return [
                    'status' => $existing->status_code,
                    'data' => $existing->response,
                ];
            }

            $result = $callback();

            IdempotencyKey::query()->create([
                'actor_id' => $actor->id,
                'operation' => $operation,
                'key' => $key,
                'request_hash' => $hash,
                'status_code' => $result['status'],
                'response' => $result['data'],
                'created_at' => now(),
            ]);

            return $result;
        });
    }
}
