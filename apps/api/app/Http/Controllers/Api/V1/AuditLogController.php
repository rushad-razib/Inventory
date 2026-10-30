<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Responses\ApiResponse;
use App\Models\AuditLog;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    #[QueryParameter('filter[actor_id]', description: 'Actor user id', type: 'string')]
    #[QueryParameter('filter[action]', description: 'Audit action', type: 'string')]
    #[QueryParameter('filter[auditable_type]', description: 'Model name, such as Sale', type: 'string')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = AuditLog::query()->with('actor')->orderByDesc('created_at');

        if ($request->filled('filter.actor_id')) {
            $query->where('actor_id', $request->input('filter.actor_id'));
        }

        if ($request->filled('filter.action')) {
            $query->where('action', $request->input('filter.action'));
        }

        if ($request->filled('filter.auditable_type')) {
            $type = class_basename(str_replace('/', '\\', (string) $request->input('filter.auditable_type')));
            $query->where('auditable_type', 'like', '%'.$type);
        }

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request)),
            AuditLogResource::class,
        );
    }
}
