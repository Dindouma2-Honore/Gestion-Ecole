<?php

declare(strict_types=1);

namespace App\Modules\Socle\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegisterUserRequest extends FormRequest
{
    private const ROLES = [
        'Fondateur',
        'Directeur',
        'Comptable',
        'SurveillantGeneral',
        'ChargeLogistique',
        'Enseignant',
        'Parent',
        'Eleve',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('gerer_utilisateurs') ?? false;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in(self::ROLES)],
            'niveau_id' => ['nullable', 'integer', 'exists:niveaux,id'],
            'statut' => ['sometimes', Rule::in(['actif', 'suspendu', 'desactive'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $role = $this->string('role')->toString();
                $niveauId = $this->input('niveau_id');

                if (in_array($role, ['Directeur', 'Enseignant'], true) && blank($niveauId)) {
                    $validator->errors()->add(
                        'niveau_id',
                        "Le rôle {$role} nécessite un niveau d'enseignement."
                    );
                }

                if (! in_array($role, ['Directeur', 'Enseignant'], true) && filled($niveauId)) {
                    $validator->errors()->add(
                        'niveau_id',
                        "Le rôle {$role} ne doit pas être rattaché à un niveau."
                    );
                }
            },
        ];
    }
}
