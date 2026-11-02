<?php

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
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

it('updates the signed-in user name without changing their role', function () {
    $user = User::factory()->create(['name' => 'Old Name']);
    $user->assignRole('Cashier');

    $response = $this->actingAs($user)->patchJson('/api/v1/auth/me', [
        'name' => 'New Name',
        'role' => 'Admin',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.email', $user->email);

    $fresh = $user->fresh();
    expect($fresh->name)->toBe('New Name')
        ->and($fresh->hasRole('Cashier'))->toBeTrue()
        ->and($fresh->hasRole('Admin'))->toBeFalse();
});

it('updates email and password when the current password matches', function () {
    $user = User::factory()->create([
        'email' => 'cashier@example.test',
        'password' => 'password',
    ]);
    $user->assignRole('Cashier');

    $response = $this->actingAs($user)->patchJson('/api/v1/auth/me', [
        'email' => 'cashier.new@example.test',
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.email', 'cashier.new@example.test');

    $fresh = $user->fresh();
    expect($fresh->email)->toBe('cashier.new@example.test')
        ->and(Hash::check('new-password', $fresh->password))->toBeTrue();
});

it('rejects a profile update with the wrong current password', function () {
    $user = User::factory()->create([
        'email' => 'cashier@example.test',
        'password' => 'password',
    ]);

    $response = $this->actingAs($user)->patchJson('/api/v1/auth/me', [
        'email' => 'cashier.new@example.test',
        'current_password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', '/problems/validation-failed');

    expect($user->fresh()->email)->toBe('cashier@example.test');
});

it('rejects a profile email that is already taken', function () {
    User::factory()->create(['email' => 'taken@example.test']);
    $user = User::factory()->create([
        'email' => 'cashier@example.test',
        'password' => 'password',
    ]);

    $response = $this->actingAs($user)->patchJson('/api/v1/auth/me', [
        'email' => 'taken@example.test',
        'current_password' => 'password',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('type', '/problems/validation-failed');
});

it('returns 401 when updating the profile logged out', function () {
    $this->patchJson('/api/v1/auth/me', [
        'name' => 'Nobody',
    ])->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', '/problems/unauthenticated');
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
    $this->seed(DemoSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Cashier');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $response = $this->actingAs($user)->getJson('/api/v1/settings');

    $response->assertOk()
        ->assertJsonPath('data.currency', 'BDT');
});

it('forbids a cashier from patching settings without settings.manage', function () {
    $this->seed(DemoSeeder::class);

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
    $this->seed(DemoSeeder::class);

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
