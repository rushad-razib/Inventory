<?php

use App\Support\LargestRemainder;

it('splits redeemed and earned points for soap and a mug', function () {
    $redeemed = LargestRemainder::split([
        ['id' => 'soap', 'weight' => 100],
        ['id' => 'mug', 'weight' => 150],
    ], 30);

    $earned = LargestRemainder::split([
        ['id' => 'soap', 'weight' => 88],
        ['id' => 'mug', 'weight' => 132],
    ], 2);

    expect($redeemed)->toBe(['soap' => 12, 'mug' => 18])
        ->and($earned)->toBe(['soap' => 1, 'mug' => 1]);
});

it('gives a partial return only the next slice of a line', function () {
    expect(LargestRemainder::cumulativeSlice(101, 2, 0, 1))->toBe(50)
        ->and(LargestRemainder::cumulativeSlice(101, 2, 1, 1))->toBe(51);
});
