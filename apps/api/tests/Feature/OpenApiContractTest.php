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
        ->and($document['paths'])->toHaveKey('/v1/settings');
});
