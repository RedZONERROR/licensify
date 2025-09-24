<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_setting_has_type_constants()
    {
        $this->assertEquals('string', Setting::TYPE_STRING);
        $this->assertEquals('integer', Setting::TYPE_INTEGER);
        $this->assertEquals('boolean', Setting::TYPE_BOOLEAN);
        $this->assertEquals('json', Setting::TYPE_JSON);
        $this->assertEquals('encrypted', Setting::TYPE_ENCRYPTED);
    }

    public function test_setting_has_category_constants()
    {
        $this->assertEquals('general', Setting::CATEGORY_GENERAL);
        $this->assertEquals('email', Setting::CATEGORY_EMAIL);
        $this->assertEquals('storage', Setting::CATEGORY_STORAGE);
        $this->assertEquals('integrations', Setting::CATEGORY_INTEGRATIONS);
        $this->assertEquals('devops', Setting::CATEGORY_DEVOPS);
        $this->assertEquals('security', Setting::CATEGORY_SECURITY);
    }

    public function test_setting_encrypts_sensitive_values()
    {
        $setting = Setting::create([
            'key' => 'smtp_password',
            'value' => 'secret-password',
            'category' => Setting::CATEGORY_EMAIL
        ]);

        $this->assertTrue($setting->is_encrypted);
        $this->assertEquals(Setting::TYPE_ENCRYPTED, $setting->type);

        // Value should be encrypted in database but decrypted when accessed
        $this->assertEquals('secret-password', $setting->value);
        $this->assertNotEquals('secret-password', $setting->getRawOriginal('value'));
    }

    public function test_setting_get_and_set_methods()
    {
        // Test setting a string value
        $setting = Setting::set('site_name', 'My Site', Setting::CATEGORY_GENERAL, 'Site name');

        $this->assertEquals('My Site', Setting::get('site_name'));
        $this->assertEquals('default', Setting::get('non_existent', 'default'));

        // Test setting different types
        Setting::set('max_users', 100);
        Setting::set('maintenance_mode', true);
        Setting::set('features', ['feature1', 'feature2']);

        $this->assertEquals(100, Setting::get('max_users'));
        $this->assertTrue(Setting::get('maintenance_mode'));
        $this->assertEquals(['feature1', 'feature2'], Setting::get('features'));
    }

    public function test_setting_determines_type_automatically()
    {
        $stringSetting = Setting::set('site_name', 'My Site');
        $integerSetting = Setting::set('max_users', 100);
        $booleanSetting = Setting::set('maintenance_mode', true);
        $jsonSetting = Setting::set('features', ['feature1', 'feature2']);

        $this->assertEquals(Setting::TYPE_STRING, $stringSetting->type);
        $this->assertEquals(Setting::TYPE_INTEGER, $integerSetting->type);
        $this->assertEquals(Setting::TYPE_BOOLEAN, $booleanSetting->type);
        $this->assertEquals(Setting::TYPE_JSON, $jsonSetting->type);
    }

    public function test_setting_casts_values_correctly()
    {
        Setting::create([
            'key' => 'string_value',
            'value' => 'test',
            'type' => Setting::TYPE_STRING
        ]);

        Setting::create([
            'key' => 'integer_value',
            'value' => '123',
            'type' => Setting::TYPE_INTEGER
        ]);

        Setting::create([
            'key' => 'boolean_value',
            'value' => '1',
            'type' => Setting::TYPE_BOOLEAN
        ]);

        Setting::create([
            'key' => 'json_value',
            'value' => '{"key": "value"}',
            'type' => Setting::TYPE_JSON
        ]);

        $this->assertIsString(Setting::get('string_value'));
        $this->assertIsInt(Setting::get('integer_value'));
        $this->assertIsBool(Setting::get('boolean_value'));
        $this->assertIsArray(Setting::get('json_value'));

        $this->assertEquals('test', Setting::get('string_value'));
        $this->assertEquals(123, Setting::get('integer_value'));
        $this->assertTrue(Setting::get('boolean_value'));
        $this->assertEquals(['key' => 'value'], Setting::get('json_value'));
    }

    public function test_setting_get_category_method()
    {
        Setting::set('site_name', 'My Site', Setting::CATEGORY_GENERAL);
        Setting::set('admin_email', 'admin@example.com', Setting::CATEGORY_GENERAL);
        Setting::set('smtp_host', 'smtp.example.com', Setting::CATEGORY_EMAIL);

        $generalSettings = Setting::getCategory(Setting::CATEGORY_GENERAL);
        $emailSettings = Setting::getCategory(Setting::CATEGORY_EMAIL);

        $this->assertArrayHasKey('site_name', $generalSettings);
        $this->assertArrayHasKey('admin_email', $generalSettings);
        $this->assertArrayNotHasKey('smtp_host', $generalSettings);

        $this->assertArrayHasKey('smtp_host', $emailSettings);
        $this->assertArrayNotHasKey('site_name', $emailSettings);
    }

    public function test_setting_masked_value_for_encrypted_settings()
    {
        $encryptedSetting = Setting::set('smtp_password', 'secret-password');
        $normalSetting = Setting::set('site_name', 'My Site');

        $this->assertEquals('********', $encryptedSetting->getMaskedValue());
        $this->assertEquals('My Site', $normalSetting->getMaskedValue());
    }

    public function test_setting_has_and_forget_methods()
    {
        Setting::set('test_key', 'test_value');

        $this->assertTrue(Setting::has('test_key'));
        $this->assertFalse(Setting::has('non_existent_key'));

        $this->assertTrue(Setting::forget('test_key'));
        $this->assertFalse(Setting::has('test_key'));
        $this->assertFalse(Setting::forget('non_existent_key'));
    }

    public function test_setting_scopes()
    {
        $generalSetting = Setting::set('site_name', 'My Site', Setting::CATEGORY_GENERAL);
        $emailSetting = Setting::set('smtp_host', 'smtp.example.com', Setting::CATEGORY_EMAIL);
        $encryptedSetting = Setting::set('smtp_password', 'secret');

        // Test category scope
        $generalSettings = Setting::category(Setting::CATEGORY_GENERAL)->get();
        $this->assertTrue($generalSettings->contains($generalSetting));
        $this->assertFalse($generalSettings->contains($emailSetting));

        // Test encrypted scope
        $encryptedSettings = Setting::encrypted()->get();
        $this->assertTrue($encryptedSettings->contains($encryptedSetting));
        $this->assertFalse($encryptedSettings->contains($generalSetting));

        // Test not encrypted scope
        $notEncryptedSettings = Setting::notEncrypted()->get();
        $this->assertTrue($notEncryptedSettings->contains($generalSetting));
        $this->assertFalse($notEncryptedSettings->contains($encryptedSetting));
    }

    public function test_setting_cache_invalidation()
    {
        // Set initial value
        Setting::set('cached_value', 'initial');
        $this->assertEquals('initial', Setting::get('cached_value'));

        // Update value - cache should be invalidated
        Setting::set('cached_value', 'updated');
        $this->assertEquals('updated', Setting::get('cached_value'));

        // Delete setting - cache should be invalidated
        Setting::forget('cached_value');
        $this->assertNull(Setting::get('cached_value'));
    }

    public function test_setting_get_defaults_structure()
    {
        $defaults = Setting::getDefaults();

        $this->assertIsArray($defaults);
        $this->assertArrayHasKey(Setting::CATEGORY_GENERAL, $defaults);
        $this->assertArrayHasKey(Setting::CATEGORY_EMAIL, $defaults);
        $this->assertArrayHasKey(Setting::CATEGORY_STORAGE, $defaults);
        $this->assertArrayHasKey(Setting::CATEGORY_INTEGRATIONS, $defaults);
        $this->assertArrayHasKey(Setting::CATEGORY_DEVOPS, $defaults);

        // Check some expected keys
        $this->assertArrayHasKey('site_name', $defaults[Setting::CATEGORY_GENERAL]);
        $this->assertArrayHasKey('smtp_host', $defaults[Setting::CATEGORY_EMAIL]);
        $this->assertArrayHasKey('s3_access_key', $defaults[Setting::CATEGORY_STORAGE]);
    }

    public function test_setting_casts_attributes_correctly()
    {
        $setting = Setting::factory()->create([
            'is_encrypted' => 1
        ]);

        $this->assertIsBool($setting->is_encrypted);
        $this->assertTrue($setting->is_encrypted);
    }
}