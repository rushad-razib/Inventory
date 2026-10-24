<?php

declare(strict_types=1);
use Symfony\Component\Yaml\Yaml;

require __DIR__.'/../vendor/autoload.php';

$jsonPath = __DIR__.'/../storage/app/openapi.json';
$yamlPath = __DIR__.'/../../../openapi/openapi.v1.yaml';

$document = json_decode(file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);

$document['info']['title'] = 'Meridian API';
$document['info']['version'] = '1.0.0';
$document['servers'] = [
    ['url' => 'http://localhost:8000/api'],
];

$yaml = Yaml::dump(
    $document,
    12,
    2,
    Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE,
);

if (! is_dir(dirname($yamlPath))) {
    mkdir(dirname($yamlPath), 0777, true);
}

file_put_contents($yamlPath, $yaml);

echo "Exported {$yamlPath}\n";
