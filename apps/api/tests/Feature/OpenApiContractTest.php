<?php

use Symfony\Component\Yaml\Yaml;

it('keeps auth routes in the committed openapi document', function () {
    $spec = realpath(base_path('../../openapi/openapi.v1.yaml'));

    expect($spec)->not->toBeFalse();

    $document = Yaml::parseFile($spec);

    expect($document['openapi'])->toStartWith('3.')
        ->and($document['paths'])->toHaveKey('/v1/auth/login')
        ->and($document['paths'])->toHaveKey('/v1/auth/me')
        ->and($document['paths'])->toHaveKey('/v1/auth/logout')
        ->and($document['paths'])->toHaveKey('/v1/settings')
        ->and($document['paths'])->toHaveKey('/v1/branding')
        ->and($document['paths'])->toHaveKey('/v1/settings/logo')
        ->and($document['paths'])->toHaveKey('/v1/settings/favicon')
        ->and($document['paths'])->toHaveKey('/v1/cash-movements')
        ->and($document['paths'])->toHaveKey('/v1/drawers')
        ->and($document['paths'])->toHaveKey('/v1/drawers/current')
        ->and($document['paths'])->toHaveKey('/v1/drawers/{drawer}/close');
});
