<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use App\Modules\Socle\Enums\AuthorizationAction;
use App\Modules\Socle\Enums\AuthorizationSubjectType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizationGrant extends Model
{
    protected $table = 'authorization_grants';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'feature_id',
        'can_read',
        'can_write',
        'can_execute',
        'can_print',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'subject_type' => AuthorizationSubjectType::class,
            'can_read' => 'boolean',
            'can_write' => 'boolean',
            'can_execute' => 'boolean',
            'can_print' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Fonctionnalite::class, 'feature_id');
    }

    /** @return list<AuthorizationAction> */
    public function grantedActions(): array
    {
        return array_values(array_filter(
            AuthorizationAction::cases(),
            fn (AuthorizationAction $action): bool => (bool) $this->{self::columnFor($action)},
        ));
    }

    public static function columnFor(AuthorizationAction $action): string
    {
        return match ($action) {
            AuthorizationAction::READ => 'can_read',
            AuthorizationAction::WRITE => 'can_write',
            AuthorizationAction::EXECUTE => 'can_execute',
            AuthorizationAction::PRINT => 'can_print',
        };
    }
}
