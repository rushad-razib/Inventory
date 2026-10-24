<?php

use Symfony\Component\HttpFoundation\BinaryFileResponse;

beforeEach(function () {
    file_put_contents(public_path('spa.html'), '<!doctype html><div id="root">meridian-spa</div>');
});

afterEach(function () {
    $spa = public_path('spa.html');

    if (is_file($spa)) {
        unlink($spa);
    }
});

it('serves the spa for page routes', function () {
    foreach (['/', '/login'] as $path) {
        $response = $this->get($path);
        $response->assertOk();

        $file = $response->baseResponse;
        expect($file)->toBeInstanceOf(BinaryFileResponse::class);
        expect($file->getFile()->getPathname())->toBe(public_path('spa.html'));
        expect(file_get_contents($file->getFile()->getPathname()))->toContain('meridian-spa');
    }
});

it('leaves api, sanctum, health, and docs to laravel', function () {
    $this->get('/up')->assertOk()->assertDontSee('meridian-spa', false);

    $this->getJson('/api/v1/auth/me')->assertUnauthorized();

    $this->get('/sanctum/csrf-cookie')->assertNoContent();

    $docs = $this->get('/docs/api');
    expect($docs->getContent())->not->toContain('meridian-spa');
});

it('returns 404 when the spa build is missing', function () {
    unlink(public_path('spa.html'));

    $this->get('/')->assertNotFound();
    $this->get('/login')->assertNotFound();
});
