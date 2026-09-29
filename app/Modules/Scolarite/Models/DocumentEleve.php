<?php

namespace App\Modules\Scolarite\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentEleve extends Model
{
    protected $table = 'documents_eleve';

    protected $guarded = [];

    protected $casts = ['date_ajout' => 'date'];

    public function typeDocument()
    {
        return $this->belongsTo(TypeDocumentEleve::class, 'type_document_eleve_id');
    }

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }
}
