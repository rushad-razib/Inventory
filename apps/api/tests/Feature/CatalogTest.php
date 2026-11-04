<?php

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\ImageService;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoSeeder::class);
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
        ->getJson('/api/v1/branding')
        ->assertOk()
        ->assertJsonPath('data.name', 'Hart & Field');

    $this->actingAs($user)
        ->post('/api/v1/settings/logo', [
            'image' => UploadedFile::fake()->image('logo.png'),
        ], ['Accept' => 'application/json'])
        ->assertForbidden();

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

    $product = Product::query()->where('barcode', '8941000000024')->firstOrFail();

    $this->actingAs($user)
        ->getJson('/api/v1/products/lookup?barcode=8941000000024')
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
    expect(Customer::query()->count())->toBe(2);
});

it('updates branding files and audits the change', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($admin)
        ->patchJson('/api/v1/settings', [
            'name' => 'Northwind',
            'title' => 'Northwind Desk',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Northwind')
        ->assertJsonPath('data.title', 'Northwind Desk');

    $this->actingAs($admin)
        ->patchJson('/api/v1/settings', [
            'title' => '',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', null);

    $this->getJson('/api/v1/branding')
        ->assertOk()
        ->assertJsonPath('data.name', 'Northwind')
        ->assertJsonPath('data.title', null);

    $logo = UploadedFile::fake()->createWithContent(
        'mark.svg',
        '<svg xmlns="http://www.w3.org/2000/svg" width="8" height="8"></svg>',
    );

    $this->actingAs($admin)
        ->post('/api/v1/settings/logo', [
            'image' => $logo,
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.logo_url', fn (string $url) => str_starts_with($url, '/storage/branding/'));

    $logoPath = CompanySetting::current()->logo_path;
    expect($logoPath)->not->toBeNull();
    Storage::disk('public')->assertExists($logoPath);

    $favicon = UploadedFile::fake()->image('icon.png', 16, 16);

    $this->actingAs($admin)
        ->post('/api/v1/settings/favicon', [
            'image' => $favicon,
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.favicon_url', fn (string $url) => str_starts_with($url, '/storage/branding/'));

    Storage::disk('public')->assertExists(CompanySetting::current()->favicon_path);

    $this->actingAs($admin)
        ->deleteJson('/api/v1/settings/logo')
        ->assertOk()
        ->assertJsonPath('data.logo_url', null);

    Storage::disk('public')->assertMissing($logoPath);

    $this->actingAs($admin)
        ->deleteJson('/api/v1/settings/favicon')
        ->assertOk()
        ->assertJsonPath('data.favicon_url', null);

    expect(AuditLog::query()->where('action', 'settings.updated')->count())->toBe(6);
    expect(CompanySetting::current()->logo_path)->toBeNull()
        ->and(CompanySetting::current()->favicon_path)->toBeNull();
});

it('searches products, suppliers, customers, and purchase orders', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $order = PurchaseOrder::query()->firstOrFail();

    $this->actingAs($admin)
        ->getJson('/api/v1/products?q=SOAP-001')
        ->assertOk()
        ->assertJsonPath('data.0.sku', 'SOAP-001')
        ->assertJsonCount(1, 'data');

    $this->actingAs($admin)
        ->getJson('/api/v1/suppliers?q=Hart Mill')
        ->assertOk()
        ->assertJsonPath('data.0.code', 'HART-MILL');

    $this->actingAs($admin)
        ->getJson('/api/v1/customers?q=Rahim')
        ->assertOk()
        ->assertJsonPath('data.0.mobile', '+8801711000001');

    $this->actingAs($admin)
        ->getJson('/api/v1/purchase-orders?q='.$order->number)
        ->assertOk()
        ->assertJsonPath('data.0.number', $order->number)
        ->assertJsonCount(1, 'data');
});
