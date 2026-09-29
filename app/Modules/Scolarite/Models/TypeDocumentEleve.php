<?php

namespace App\Modules\Scolarite\Models;

use Illuminate\Database\Eloquent\Model;

class TypeDocumentEleve extends Model
{
    protected $table = 'types_documents_eleve';

    protected $guarded = [];

    protected $casts = ['obligatoire' => 'boolean', 'actif' => 'boolean'];

    public function documents()
    {
        return $this->hasMany(DocumentEleve::class);
    }
}
