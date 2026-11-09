<?php

namespace App\Http\Controllers;

use App\Http\Responses\ProblemResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    protected function forbidden(string $detail = 'You do not have permission to perform this action.'): JsonResponse
    {
        return ProblemResponse::make(
            'forbidden',
            'Forbidden',
            403,
            $detail,
        );
    }

    protected function conflict(string $detail): JsonResponse
    {
        return ProblemResponse::make(
            'conflict',
            'Conflict',
            409,
            $detail,
        );
    }

    protected function perPage(Request $request): int
    {
        return min(100, max(1, (int) $request->integer('per_page', 25)));
    }

    protected function term(Request $request): ?string
    {
        if (! $request->filled('q')) {
            return null;
        }

        $term = trim($request->string('q')->toString());

        return $term === '' ? null : $term;
    }

    protected function like(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $columns
     */
    protected function whereLike($query, Request $request, array $columns): void
    {
        $term = $this->term($request);

        if ($term === null || $columns === []) {
            return;
        }

        $like = $this->like($term);
        $query->where(function ($inner) use ($like, $columns): void {
            foreach ($columns as $column) {
                $inner->orWhere($column, 'like', $like);
            }
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function whereOn($query, Request $request, string $column): void
    {
        if ($request->filled('filter.on')) {
            $query->whereDate($column, $request->string('filter.on')->toString());
        }
    }
}
