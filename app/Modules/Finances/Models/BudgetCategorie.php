<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetCategorie extends Model
{
    protected $table = 'budget_categories';

    public $timestamps = false;

    protected $guarded = [];

    public function lignes(): HasMany
    {
        return $this->hasMany(BudgetLigne::class, 'categorie_id');
    }
}
