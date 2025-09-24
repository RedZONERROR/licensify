<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'category',
        'is_encrypted',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    /**
     * Setting type constants
     */
    public const TYPE_STRING = 'string';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_JSON = 'json';
    public const TYPE_ENCRYPTED = 'encrypted';

    /**
     * Setting category constants
     */
    public const CATEGORY_GENERAL = 'general';
    public const CATEGORY_EMAIL = 'email';
    public const CATEGORY_STORAGE = 'storage';
    public const CATEGORY_INTEGRATIONS = 'integrations';
    public const CATEGORY_DEVOPS = 'devops';
    public const CATEGORY_SECURITY = 'security';

    /**
     * Sensitive settings that should be encrypted
     */
    protected static $encryptedSettings = [
        'smtp_password',
        'telegram_bot_token',
        's3_secret_key',
        'backup_encryption_key',
        'api_secret_key',
        'oauth_client_secret',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($setting) {
            if (in_array($setting->key, self::$encryptedSettings)) {
                $setting->is_encrypted = true;
                $setting->type = self::TYPE_ENCRYPTED;
            }
        });

        static::updating(function ($setting) {
            if (in_array($setting->key, self::$encryptedSettings)) {
                $setting->is_encrypted = true;
                $setting->type = self::TYPE_ENCRYPTED;
            }
        });

        static::saved(function ($setting) {
            Cache::forget("setting.{$setting->key}");
            Cache::forget("settings.{$setting->category}");
        });

        static::deleted(function ($setting) {
            Cache::forget("setting.{$setting->key}");
            Cache::forget("settings.{$setting->category}");
        });
    }

    /**
     * Get the value attribute with proper casting and decryption
     */
    public function getValueAttribute($value)
    {
        if ($this->is_encrypted && $value !== null) {
            try {
                $value = decrypt($value);
            } catch (\Exception $e) {
                // If decryption fails, return the raw value
                return $value;
            }
        }

        return $this->castValue($value);
    }

    /**
     * Set the value attribute with proper encryption
     */
    public function setValueAttribute($value)
    {
        // Check if this key should be encrypted
        $shouldEncrypt = isset($this->attributes['key']) && in_array($this->attributes['key'], self::$encryptedSettings);
        
        if (($this->is_encrypted || $shouldEncrypt) && $value !== null) {
            $this->attributes['value'] = encrypt($value);
        } else {
            $this->attributes['value'] = $value;
        }
    }

    /**
     * Cast value to appropriate type
     */
    protected function castValue($value)
    {
        switch ($this->type) {
            case self::TYPE_INTEGER:
                return (int) $value;
            case self::TYPE_BOOLEAN:
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case self::TYPE_JSON:
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    /**
     * Get setting value by key
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting.{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set setting value by key
     */
    public static function set(string $key, $value, string $category = self::CATEGORY_GENERAL, string $description = null): self
    {
        $setting = self::firstOrNew(['key' => $key]);
        
        // Check if should be encrypted first
        if (in_array($key, self::$encryptedSettings)) {
            $setting->is_encrypted = true;
            $setting->type = self::TYPE_ENCRYPTED;
        } else {
            // Determine type based on value
            if (is_bool($value)) {
                $setting->type = self::TYPE_BOOLEAN;
            } elseif (is_int($value)) {
                $setting->type = self::TYPE_INTEGER;
            } elseif (is_array($value)) {
                $setting->type = self::TYPE_JSON;
                $value = json_encode($value);
            } else {
                $setting->type = self::TYPE_STRING;
            }
        }

        $setting->fill([
            'value' => $value,
            'category' => $category,
            'description' => $description,
        ]);

        $setting->save();
        return $setting;
    }

    /**
     * Get all settings for a category
     */
    public static function getCategory(string $category): array
    {
        return Cache::remember("settings.{$category}", 3600, function () use ($category) {
            return self::where('category', $category)
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    /**
     * Get masked value for display (for encrypted settings)
     */
    public function getMaskedValue(): string
    {
        if ($this->is_encrypted && $this->value) {
            return str_repeat('*', 8);
        }

        return (string) $this->value;
    }

    /**
     * Check if setting exists
     */
    public static function has(string $key): bool
    {
        return self::where('key', $key)->exists();
    }

    /**
     * Delete setting by key
     */
    public static function forget(string $key): bool
    {
        $deleted = self::where('key', $key)->delete() > 0;
        
        // Clear cache
        Cache::forget("setting.{$key}");
        
        return $deleted;
    }

    /**
     * Scope for specific category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for encrypted settings
     */
    public function scopeEncrypted($query)
    {
        return $query->where('is_encrypted', true);
    }

    /**
     * Scope for non-encrypted settings
     */
    public function scopeNotEncrypted($query)
    {
        return $query->where('is_encrypted', false);
    }

    /**
     * Get default settings structure
     */
    public static function getDefaults(): array
    {
        return [
            self::CATEGORY_GENERAL => [
                'site_name' => 'License Management System',
                'site_url' => config('app.url'),
                'admin_email' => config('mail.from.address'),
                'timezone' => config('app.timezone'),
            ],
            self::CATEGORY_EMAIL => [
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'smtp_username' => '',
                'smtp_password' => '',
                'from_email' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
            ],
            self::CATEGORY_STORAGE => [
                's3_access_key' => '',
                's3_secret_key' => '',
                's3_bucket' => '',
                's3_region' => 'us-east-1',
            ],
            self::CATEGORY_INTEGRATIONS => [
                'telegram_bot_token' => '',
                'telegram_chat_id' => '',
            ],
            self::CATEGORY_DEVOPS => [
                'backup_schedule' => 'daily',
                'backup_retention_days' => 30,
                'backup_encryption_enabled' => true,
            ],
        ];
    }
}