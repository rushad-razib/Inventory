<?php

use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
});

it('logs in with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.test',
        'password' => 'password',
    ]);
    $user->assignRole('Admin');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.test',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.email', 'admin@example.test')
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'roles', 'permissions', 'department', 'warehouse_id'],
        ]);

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials with a problem response', function () {
    User::factory()->create([
        'email' => 'admin@example.test',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.test',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', '/problems/validation-failed');
});

it('returns the authenticated user from me', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.permissions.0', 'users.manage');
});

it('returns 401 for me when logged out', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', '/problems/unauthenticated');
});

it('logs out the session', function () {
    $user = User::factory()->create([
        'email' => 'cashier@example.test',
        'password' => 'password',
    ]);
    $user->assignRole('Cashier');

    $this->postJson('/api/v1/auth/login', [
        'email' => 'cashier@example.test',
        'password' => 'password',
    ])->assertOk();

    $this->postJson('/api/v1/auth/logout')->assertOk();

    $this->assertGuest('web');
});

it('allows a cashier to read settings without settings.manage', function () {
    $this->seed(CatalogSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Cashier');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $response = $this->actingAs($user)->getJson('/api/v1/settings');

    $response->assertOk()
        ->assertJsonPath('data.currency', 'BDT');
});

it('forbids a cashier from patching settings without settings.manage', function () {
    $this->seed(CatalogSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Cashier');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $response = $this->actingAs($user)->patchJson('/api/v1/settings', [
        'points_earn_per' => 50,
    ]);

    $response->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', '/problems/forbidden');
});

it('allows settings for an admin', function () {
    $this->seed(CatalogSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Admin');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $response = $this->actingAs($user)->getJson('/api/v1/settings');

    $response->assertOk()
        ->assertJsonPath('data.currency', 'BDT');
});

it('creates and revokes a personal access token', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $create = $this->actingAs($user)->postJson('/api/v1/auth/tokens', [
        'name' => 'docs',
        'abilities' => ['settings.manage'],
    ]);

    $create->assertCreated()
        ->assertJsonPath('data.name', 'docs')
        ->assertJsonStructure(['data' => ['id', 'token', 'abilities']]);

    $tokenId = $create->json('data.id');

    $this->actingAs($user)
        ->deleteJson("/api/v1/auth/tokens/{$tokenId}")
        ->assertOk();

    expect($user->fresh()->tokens)->toHaveCount(0);
});
