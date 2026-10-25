<?php

use App\Support\BdMobile;

it('normalizes local bangladesh mobiles', function () {
    expect(BdMobile::normalize('01712345678'))->toBe('+8801712345678');
});

it('accepts e164 bangladesh mobiles', function () {
    expect(BdMobile::normalize('+8801912345678'))->toBe('+8801912345678');
});

it('rejects foreign mobiles', function () {
    BdMobile::normalize('+15551234567');
})->throws(InvalidArgumentException::class);
