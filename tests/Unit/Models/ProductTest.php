<?php

namespace Tests\Unit\Models;

use App\Models\License;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_has_many_licenses()
    {
        $product = Product::factory()->create();
        $license1 = License::factory()->create(['product_id' => $product->id]);
        $license2 = License::factory()->create(['product_id' => $product->id]);

        $this->assertCount(2, $product->licenses);
        $this->assertTrue($product->licenses->contains($license1));
        $this->assertTrue($product->licenses->contains($license2));
    }

    public function test_product_has_many_active_licenses()
    {
        $product = Product::factory()->create();
        $activeLicense = License::factory()->create([
            'product_id' => $product->id,
            'status' => License::STATUS_ACTIVE,
            'expires_at' => now()->addDays(30)
        ]);
        $expiredLicense = License::factory()->create([
            'product_id' => $product->id,
            'status' => License::STATUS_EXPIRED
        ]);

        $activeLicenses = $product->activeLicenses;
        $this->assertCount(1, $activeLicenses);
        $this->assertTrue($activeLicenses->contains($activeLicense));
        $this->assertFalse($activeLicenses->contains($expiredLicense));
    }

    public function test_product_can_check_if_active()
    {
        $activeProduct = Product::factory()->create(['is_active' => true]);
        $inactiveProduct = Product::factory()->create(['is_active' => false]);

        $this->assertTrue($activeProduct->isActive());
        $this->assertFalse($inactiveProduct->isActive());
    }

    public function test_product_active_scope()
    {
        $activeProduct = Product::factory()->create(['is_active' => true]);
        $inactiveProduct = Product::factory()->create(['is_active' => false]);

        $activeProducts = Product::active()->get();

        $this->assertTrue($activeProducts->contains($activeProduct));
        $this->assertFalse($activeProducts->contains($inactiveProduct));
    }

    public function test_product_casts_attributes_correctly()
    {
        $product = Product::factory()->create([
            'price' => '99.99',
            'is_active' => 1,
            'metadata' => ['key' => 'value']
        ]);

        $this->assertIsFloat($product->price);
        $this->assertEquals(99.99, $product->price);
        $this->assertIsBool($product->is_active);
        $this->assertTrue($product->is_active);
        $this->assertIsArray($product->metadata);
        $this->assertEquals(['key' => 'value'], $product->metadata);
    }
}