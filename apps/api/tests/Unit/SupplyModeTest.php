<?php

use App\Enums\SupplyMode;

it('exposes supply mode labels', function () {
    expect(SupplyMode::Outright->label())->toBe('outright')
        ->and(SupplyMode::Consignment->label())->toBe('consignment')
        ->and(SupplyMode::fromLabel('consignment'))->toBe(SupplyMode::Consignment);
});
