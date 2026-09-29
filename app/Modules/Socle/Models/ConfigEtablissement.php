<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ConfigEtablissement extends Model
{
    protected $table = 'config_etablissement';

    protected $fillable = ['nom', 'logo_path', 'adresse', 'telephone', 'email', 'site_web', 'rccm', 'niu', 'devise'];

    /**
     * Retourne toujours l'unique ligne de configuration en cache.
     */
    public static function get(): self
    {
        $cached = Cache::get('config_etablissement');

        if (! $cached instanceof self) {
            Cache::forget('config_etablissement');
            $cached = self::firstOrCreate([], [
                'nom' => 'Établissement Ambassadors',
            ]);
            Cache::forever('config_etablissement', $cached);
        }

        return $cached;
    }

    protected static function booted(): void
    {
        static::saved(fn() => Cache::forget('config_etablissement'));
        static::deleted(fn() => Cache::forget('config_etablissement'));
    }
}
