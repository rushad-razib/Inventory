<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
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
    Storage::fake('public');

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
        ->and($productImage['sort_order'])->toBe(1)
        ->and($productImage['url'])->toStartWith('/storage/');

    Storage::disk('public')->assertExists(ltrim(substr($productImage['url'], strlen('/storage/')), '/'));

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

it('deletes unused catalog records and blocks records that still have associations', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $cashier = User::factory()->create();
    $cashier->assignRole('Cashier');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $category = Category::query()->create(['name' => 'Seasonal']);
    $this->actingAs($admin)
        ->deleteJson('/api/v1/categories/'.$category->id)
        ->assertOk()
        ->assertJsonPath('data', null);
    expect(Category::query()->whereKey($category->id)->exists())->toBeFalse();

    $kitchen = Category::query()->where('name', 'Kitchen')->firstOrFail();
    $this->actingAs($admin)
        ->deleteJson('/api/v1/categories/'.$kitchen->id)
        ->assertStatus(409)
        ->assertJsonPath('detail', 'Cannot delete this category. Please edit/delete associated products before deleting this category.');
    expect(Category::query()->whereKey($kitchen->id)->exists())->toBeTrue();

    $parent = Category::query()->create(['name' => 'Season']);
    Category::query()->create(['name' => 'Summer', 'parent_id' => $parent->id]);
    $this->actingAs($admin)
        ->deleteJson('/api/v1/categories/'.$parent->id)
        ->assertStatus(409)
        ->assertJsonPath('detail', 'Cannot delete this category. Please edit/delete child categories before deleting this category.');

    $this->actingAs($cashier)
        ->deleteJson('/api/v1/categories/'.$kitchen->id)
        ->assertForbidden();

    $supplierId = $this->actingAs($admin)
        ->postJson('/api/v1/suppliers', [
            'code' => 'TEMP-SUP',
            'name' => 'Temp Supplier',
            'supply_mode' => 'outright',
            'status' => 'active',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin)
        ->deleteJson('/api/v1/suppliers/'.$supplierId)
        ->assertOk();
    expect(Supplier::query()->whereKey($supplierId)->exists())->toBeFalse();

    $hart = Supplier::query()->where('code', 'HART-MILL')->firstOrFail();
    $this->actingAs($admin)
        ->deleteJson('/api/v1/suppliers/'.$hart->id)
        ->assertStatus(409)
        ->assertJsonPath('detail', 'Cannot delete this supplier. Please edit/delete associated products before deleting this supplier.');
    expect(Supplier::query()->whereKey($hart->id)->exists())->toBeTrue();

    $warehouseId = $this->actingAs($admin)
        ->postJson('/api/v1/warehouses', [
            'code' => 'TEMP',
            'name' => 'Temp Store',
            'is_default' => false,
            'is_active' => true,
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin)
        ->deleteJson('/api/v1/warehouses/'.$warehouseId)
        ->assertOk();
    expect(Warehouse::query()->whereKey($warehouseId)->exists())->toBeFalse();

    $dc = Warehouse::query()->where('code', 'DC')->firstOrFail();
    $this->actingAs($admin)
        ->deleteJson('/api/v1/warehouses/'.$dc->id)
        ->assertStatus(409)
        ->assertJsonPath('detail', 'Cannot delete this warehouse. There are products in this warehouse.');
    expect(Warehouse::query()->whereKey($dc->id)->exists())->toBeTrue();

    $productId = $this->actingAs($admin)
        ->postJson('/api/v1/products', [
            'sku' => 'TEMP-001',
            'name' => 'Temp Item',
            'sell_price' => 10,
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin)
        ->deleteJson('/api/v1/products/'.$productId)
        ->assertOk();
    expect(Product::query()->whereKey($productId)->exists())->toBeFalse();

    $mug = Product::query()->where('sku', 'MUG-001')->firstOrFail();
    $this->actingAs($admin)
        ->deleteJson('/api/v1/products/'.$mug->id)
        ->assertStatus(409)
        ->assertJsonPath('detail', 'Cannot delete this product. It has stock or transaction history.');

    $departmentId = $this->actingAs($admin)
        ->postJson('/api/v1/departments', [
            'name' => 'Temp Dept',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin)
        ->deleteJson('/api/v1/departments/'.$departmentId)
        ->assertOk();
    expect(Department::query()->whereKey($departmentId)->exists())->toBeFalse();

    $store = Department::query()->where('name', 'Store')->firstOrFail();
    $this->actingAs($admin)
        ->deleteJson('/api/v1/departments/'.$store->id)
        ->assertStatus(409)
        ->assertJsonPath('detail', 'Cannot delete this department. Please reassign associated users before deleting this department.');

    $customerId = $this->actingAs($admin)
        ->postJson('/api/v1/customers', [
            'mobile' => '01712349999',
            'name' => 'Temp Customer',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin)
        ->deleteJson('/api/v1/customers/'.$customerId)
        ->assertOk();
    expect(Customer::query()->whereKey($customerId)->exists())->toBeFalse();

    $rahim = Customer::query()->where('mobile', '+8801711000001')->firstOrFail();
    $this->actingAs($admin)
        ->deleteJson('/api/v1/customers/'.$rahim->id)
        ->assertStatus(409)
        ->assertJsonPath('detail', 'Cannot delete this customer. This customer has sales or point history.');

    $spare = User::factory()->create();
    $spare->assignRole('Cashier');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($admin)
        ->deleteJson('/api/v1/users/'.$spare->id)
        ->assertOk();
    expect(User::query()->whereKey($spare->id)->exists())->toBeFalse();

    $this->actingAs($admin)
        ->deleteJson('/api/v1/users/'.$admin->id)
        ->assertStatus(409)
        ->assertJsonPath('detail', 'Cannot delete your own user.');
});
