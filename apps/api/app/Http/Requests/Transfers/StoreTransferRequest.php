<?php

namespace App\Http\Requests\Transfers;

use App\Models\StockBalance;
use App\Models\Transfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Transfer::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_warehouse_id' => ['required', 'uuid', 'exists:warehouses,id', 'different:destination_warehouse_id'],
            'destination_warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', 'distinct', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $sourceId = $this->string('source_warehouse_id')->toString();

            foreach ($this->input('lines', []) as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $onHand = (int) StockBalance::query()
                    ->where('warehouse_id', $sourceId)
                    ->where('product_id', $line['product_id'] ?? null)
                    ->value('on_hand');

                if ((int) ($line['quantity'] ?? 0) > $onHand) {
                    $validator->errors()->add(
                        "lines.{$index}.quantity",
                        "Only {$onHand} on hand at the source warehouse.",
                    );
                }
            }
        });
    }
}
