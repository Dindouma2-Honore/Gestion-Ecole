<?php
declare(strict_types=1);
namespace App\Modules\Pedagogie\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ConseilClasse extends Model {
 protected $table='conseils_classe';
 protected $fillable=['classe_id','periode_id','effectif','moyenne_classe','taux_reussite','date_conseil','statut','valide_par','valide_le'];
 protected $casts=['date_conseil'=>'date','valide_le'=>'datetime','moyenne_classe'=>'float','taux_reussite'=>'float'];
 public function decisions(): HasMany { return $this->hasMany(DecisionConseil::class); }
}
