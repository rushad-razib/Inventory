<?php

use App\Enums\SupplierStatus;

it('maps labels without magic numbers', function () {
    expect(SupplierStatus::Active->label())->toBe('active')
        ->and(SupplierStatus::fromLabel('inactive'))->toBe(SupplierStatus::Inactive)
        ->and(SupplierStatus::Active->value)->toBeInt();
});
