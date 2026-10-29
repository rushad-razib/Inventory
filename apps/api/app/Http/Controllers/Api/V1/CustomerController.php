<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Http\Resources\CustomerPointEntryResource;
use App\Http\Resources\CustomerResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ProblemResponse;
use App\Models\Customer;
use App\Models\CustomerPointEntry;
use App\Support\BdMobile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('customers.manage')) {
            return $this->forbidden('You do not have permission to list customers.');
        }

        $query = Customer::query()->orderBy('mobile');

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($builder) use ($q) {
                $builder->where('mobile', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            });
        }

        return ApiResponse::paginated($query->paginate($this->perPage($request)), CustomerResource::class);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        if (! $request->user()->can('customers.manage')) {
            return $this->forbidden('You do not have permission to create customers.');
        }

        $customer = Customer::query()->create([
            ...$request->validated(),
            'points_balance' => 0,
        ]);

        return ApiResponse::data(new CustomerResource($customer), 201);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        if (! $request->user()->can('customers.manage')) {
            return $this->forbidden('You do not have permission to view customers.');
        }

        return ApiResponse::data(new CustomerResource($customer));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        if (! $request->user()->can('customers.manage')) {
            return $this->forbidden('You do not have permission to update customers.');
        }

        $customer->update($request->validated());

        return ApiResponse::data(new CustomerResource($customer->refresh()));
    }

    public function lookup(Request $request): JsonResponse
    {
        if (! $request->user()->can('sales.create')) {
            return $this->forbidden('You do not have permission to look up customers.');
        }

        $raw = $request->query('mobile');

        if (! is_string($raw) || $raw === '') {
            return ApiResponse::data(null);
        }

        try {
            $mobile = BdMobile::normalize($raw);
        } catch (InvalidArgumentException $e) {
            return ProblemResponse::make(
                'validation-failed',
                'Validation Failed',
                422,
                'One or more fields failed validation.',
                ['mobile' => [$e->getMessage()]],
            );
        }

        $customer = Customer::query()->where('mobile', $mobile)->first();

        return ApiResponse::data($customer ? new CustomerResource($customer) : null);
    }

    public function pointEntries(Request $request, Customer $customer): JsonResponse
    {
        if (! $request->user()->can('customers.manage') && ! $request->user()->can('sales.create')) {
            return $this->forbidden('You do not have permission to view point entries.');
        }

        return ApiResponse::paginated(
            CustomerPointEntry::query()
                ->where('customer_id', $customer->id)
                ->orderByDesc('occurred_at')
                ->paginate($this->perPage($request)),
            CustomerPointEntryResource::class,
        );
    }
}
