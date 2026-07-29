<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Configuración editable desde el panel de admin.
 * Los valores sensibles (API key de TMDB) se guardan encriptados.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean'];
    }

    /** Claves cuyo valor se persiste encriptado */
    public const ENCRYPTED_KEYS = ['tmdb.api_key'];

    /** Feature flags con su valor por defecto */
    public const FEATURE_FLAGS = [
        'features.registration' => true,
        'features.comments' => true,
        'features.lists' => true,
        'features.reviews' => true,
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever("setting:{$key}", function () use ($key) {
            $setting = static::find($key);

            if ($setting === null) {
                return null;
            }

            if (! $setting->is_encrypted) {
                return $setting->value;
            }

            try {
                return Crypt::decryptString((string) $setting->value);
            } catch (Throwable) {
                return null;
            }
        });

        return $value ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $encrypt = in_array($key, self::ENCRYPTED_KEYS, true);

        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value === null ? null : ($encrypt ? Crypt::encryptString((string) $value) : (string) $value),
                'is_encrypted' => $encrypt,
            ]
        );

        Cache::forget("setting:{$key}");
    }

    public static function enabled(string $flag): bool
    {
        $stored = self::get($flag);

        if ($stored === null) {
            return self::FEATURE_FLAGS[$flag] ?? true;
        }

        return filter_var($stored, FILTER_VALIDATE_BOOLEAN);
    }
}
