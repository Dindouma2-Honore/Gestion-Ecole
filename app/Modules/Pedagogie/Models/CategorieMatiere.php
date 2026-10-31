<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CategorieMatiere extends Model
{
    protected $table = 'categories_matieres';

    protected $fillable = ['nom', 'description', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(function (CategorieMatiere $categorie): void {
            if (filled($categorie->code)) {
                return;
            }

            $baseCode = Str::slug((string) $categorie->nom, '_');
            $baseCode = substr($baseCode !== '' ? $baseCode : 'categorie', 0, 30);
            $code = $baseCode;
            $suffix = 2;

            while (static::query()->where('code', $code)->exists()) {
                $suffixText = '_'.$suffix++;
                $code = substr($baseCode, 0, 30 - strlen($suffixText)).$suffixText;
            }

            $categorie->code = $code;
        });
    }

    public function matieres(): HasMany
    {
        return $this->hasMany(Matiere::class);
    }
}
