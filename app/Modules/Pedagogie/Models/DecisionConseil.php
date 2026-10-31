<?php
declare(strict_types=1);
namespace App\Modules\Pedagogie\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DecisionConseil extends Model {
 protected $table='decisions_conseil'; protected $fillable=['conseil_classe_id','eleve_id','decision'];
 public function conseil(): BelongsTo { return $this->belongsTo(ConseilClasse::class,'conseil_classe_id'); }
}
