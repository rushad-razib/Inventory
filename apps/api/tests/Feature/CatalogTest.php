<?php

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\ImageService;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    $this->seed(CatalogSeeder::class);
});

it('allows a cashier to get settings but forbids patching them', function () {
    $user = User::factory()->create();
    $user->assignRole('Cashier');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($user)
        ->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('data.currency', 'BDT');

    $this->actingAs($user)
        ->patchJson('/api/v1/settings', [
            'po_approval_threshold' => 99999,
        ])
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', '/problems/forbidden');
});

it('looks up a product by barcode', function () {
    $user = User::factory()->create();
    $user->assignRole('Cashier');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $product = Product::query()->where('barcode', '8901001001001')->firstOrFail();

    $this->actingAs($user)
        ->getJson('/api/v1/products/lookup?barcode=8901001001001')
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.sku', 'MUG-001');

    $this->actingAs($user)
        ->getJson('/api/v1/products/lookup?barcode=missing')
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('normalizes bangladesh mobiles and rejects foreign numbers', function () {
    $cashier = User::factory()->create();
    $cashier->assignRole('Cashier');
    $manager = User::factory()->create();
    $manager->assignRole('Admin');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($manager)
        ->postJson('/api/v1/customers', [
            'mobile' => '01712345678',
            'name' => 'Walk-in Friend',
        ])
        ->assertCreated()
        ->assertJsonPath('data.mobile', '+8801712345678')
        ->assertJsonPath('data.points_balance', 0);

    $this->actingAs($cashier)
        ->getJson('/api/v1/customers/lookup?mobile=01712345678')
        ->assertOk()
        ->assertJsonPath('data.mobile', '+8801712345678');

    $this->actingAs($cashier)
        ->getJson('/api/v1/customers/lookup?mobile=+15551234567')
        ->assertStatus(422)
        ->assertJsonPath('type', '/problems/validation-failed');

    $this->actingAs($manager)
        ->postJson('/api/v1/customers', [
            'mobile' => '+441234567890',
        ])
        ->assertStatus(422);
});

it('attaches images to products and users through ImageService', function () {
    Storage::fake('local');

    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $product = Product::query()->where('sku', 'MUG-001')->firstOrFail();
    $file = UploadedFile::fake()->image('mug.jpg', 100, 100);

    $productImage = $this->actingAs($admin)
        ->post('/api/v1/products/'.$product->id.'/images', [
            'image' => $file,
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data');

    expect($productImage['id'])->not->toBeEmpty()
        ->and($productImage['sort_order'])->toBe(1);

    $userFile = UploadedFile::fake()->image('avatar.png', 80, 80);

    $userImage = $this->actingAs($admin)
        ->post('/api/v1/users/'.$admin->id.'/images', [
            'image' => $userFile,
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data');

    expect($userImage['id'])->not->toBeEmpty();

    $this->actingAs($admin)
        ->getJson('/api/v1/auth/me/image')
        ->assertOk()
        ->assertJsonPath('data.id', $userImage['id']);

    expect(app(ImageService::class)->list($product))->toHaveCount(1)
        ->and(app(ImageService::class)->list($admin->fresh()))->toHaveCount(1);
});

it('writes audit rows when settings or product price change', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($admin)
        ->patchJson('/api/v1/settings', [
            'po_approval_threshold' => 25000,
        ])
        ->assertOk()
        ->assertJsonPath('data.po_approval_threshold', 25000);

    expect(AuditLog::query()->where('action', 'settings.updated')->count())->toBe(1);

    $product = Product::query()->where('sku', 'MUG-001')->firstOrFail();

    $this->actingAs($admin)
        ->patchJson('/api/v1/products/'.$product->id, [
            'sell_price' => 175,
        ])
        ->assertOk()
        ->assertJsonPath('data.sell_price', 175);

    expect(AuditLog::query()->where('action', 'product.price_changed')->count())->toBe(1);
    expect(CompanySetting::current()->po_approval_threshold)->toBe(25000);
    expect(Customer::query()->count())->toBe(0);
});
