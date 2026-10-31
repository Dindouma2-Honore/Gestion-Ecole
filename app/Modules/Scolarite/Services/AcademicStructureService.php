<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Services;

use App\Modules\Scolarite\Contracts\AcademicStructureCatalogContract;
use App\Modules\Scolarite\Contracts\AcademicStructureManagementContract;
use App\Modules\Scolarite\Contracts\AcademicStructureReadContract;
use App\Modules\Scolarite\Data\AcademicStructureItemDTO;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Filiere;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Scolarite\Models\SectionScolaire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class AcademicStructureService implements AcademicStructureCatalogContract, AcademicStructureManagementContract, AcademicStructureReadContract
{
    public function cycle(int $id): ?AcademicStructureItemDTO
    {
        $record = Cycle::find($id);

        return $record ? $this->dto($record) : null;
    }

    public function cycles(?bool $active = null): array
    {
        return Cycle::query()->when($active !== null, fn ($query) => $query->where('actif', $active))->orderBy('ordre')->orderBy('id')
            ->get()->mapWithKeys(fn (Cycle $record): array => [$record->id => $this->dto($record)])->all();
    }

    public function createCycle(string $name, string $code, ?string $description = null, bool $active = true, int $order = 0): AcademicStructureItemDTO
    {
        $record = new Cycle;

        return $this->save($record, ['nom' => $name, 'code' => $code, 'description' => $description, 'actif' => $active, 'ordre' => $order]);
    }

    public function updateCycle(int $id, string $name, string $code, ?string $description = null, bool $active = true, int $order = 0): ?AcademicStructureItemDTO
    {
        $record = Cycle::find($id);
        if (! $record) {
            return null;
        }

        return $this->save($record, ['nom' => $name, 'code' => $code, 'description' => $description, 'actif' => $active, 'ordre' => $order]);
    }

    public function niveau(int $id): ?AcademicStructureItemDTO
    {
        $record = Niveau::find($id);

        return $record ? $this->dto($record) : null;
    }

    public function niveaux(?int $cycleId = null): array
    {
        return Niveau::query()->when($cycleId !== null, fn ($query) => $query->where('cycle_id', $cycleId))->orderBy('ordre')->orderBy('id')
            ->get()->mapWithKeys(fn (Niveau $record): array => [$record->id => $this->dto($record)])->all();
    }

    public function createNiveau(string $name, string $code, ?int $cycleId = null, int $order = 1): AcademicStructureItemDTO
    {
        $record = new Niveau;

        return $this->save($record, ['nom' => $name, 'code' => $code, 'cycle_id' => $cycleId, 'ordre' => $order]);
    }

    public function updateNiveau(int $id, string $name, string $code, ?int $cycleId = null, int $order = 1): ?AcademicStructureItemDTO
    {
        $record = Niveau::find($id);
        if (! $record) {
            return null;
        }

        return $this->save($record, ['nom' => $name, 'code' => $code, 'cycle_id' => $cycleId, 'ordre' => $order]);
    }

    public function filiere(int $id): ?AcademicStructureItemDTO
    {
        $record = Filiere::find($id);

        return $record ? $this->dto($record) : null;
    }

    public function filieres(?bool $active = null): array
    {
        return Filiere::query()->when($active !== null, fn ($query) => $query->where('actif', $active))->orderBy('id')->orderBy('id')
            ->get()->mapWithKeys(fn (Filiere $record): array => [$record->id => $this->dto($record)])->all();
    }

    public function createFiliere(string $name, string $code, ?string $description = null, bool $active = true): AcademicStructureItemDTO
    {
        $record = new Filiere;

        return $this->save($record, ['nom' => $name, 'code' => $code, 'description' => $description, 'actif' => $active]);
    }

    public function updateFiliere(int $id, string $name, string $code, ?string $description = null, bool $active = true): ?AcademicStructureItemDTO
    {
        $record = Filiere::find($id);
        if (! $record) {
            return null;
        }

        return $this->save($record, ['nom' => $name, 'code' => $code, 'description' => $description, 'actif' => $active]);
    }

    public function section(int $id): ?AcademicStructureItemDTO
    {
        $record = SectionScolaire::find($id);

        return $record ? $this->dto($record) : null;
    }

    public function sections(?bool $active = null): array
    {
        return SectionScolaire::query()->when($active !== null, fn ($query) => $query->where('actif', $active))->orderBy('id')->orderBy('id')
            ->get()->mapWithKeys(fn (SectionScolaire $record): array => [$record->id => $this->dto($record)])->all();
    }

    public function createSectionScolaire(string $name, string $code, ?string $description = null, bool $active = true): AcademicStructureItemDTO
    {
        $record = new SectionScolaire;

        return $this->save($record, ['nom' => $name, 'code' => $code, 'description' => $description, 'actif' => $active]);
    }

    public function updateSectionScolaire(int $id, string $name, string $code, ?string $description = null, bool $active = true): ?AcademicStructureItemDTO
    {
        $record = SectionScolaire::find($id);
        if (! $record) {
            return null;
        }

        return $this->save($record, ['nom' => $name, 'code' => $code, 'description' => $description, 'actif' => $active]);
    }

    public function niveauByLegacyId(string $legacyId): ?AcademicStructureItemDTO
    {
        $record = Niveau::where('facilg_legacy_id', $legacyId)->first();

        return $record ? $this->dto($record) : null;
    }

    public function importNiveau(string $legacyId, string $name, string $code, int $order, ?string $description = null): AcademicStructureItemDTO
    {
        $record = Niveau::firstOrNew(['facilg_legacy_id' => $legacyId]);
        $record->description = $description;

        return $this->save($record, ['nom' => $name, 'code' => $code, 'ordre' => $order, 'cycle_id' => $record->cycle_id]);
    }

    private function dto(Model $record): AcademicStructureItemDTO
    {
        return new AcademicStructureItemDTO((int) $record->id, $record->nom,
            $record->cycle_id === null ? null : (int) $record->cycle_id,
            $record->code, (bool) ($record->actif ?? true), $record->description,
            $record->ordre === null ? null : (int) $record->ordre);
    }

    private function save(Model $record, array $attributes): AcademicStructureItemDTO
    {
        $rules = [
            'nom' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:30'],
        ];
        if ($record instanceof Niveau) {
            $rules['nom'] = ['required', 'string', 'max:255'];
            $rules['code'] = ['required', 'string', 'max:50', Rule::unique('niveaux', 'code')->ignore($record->getKey())];
            $rules['cycle_id'] = ['nullable', 'integer', Rule::exists('cycles', 'id')];
            $rules['ordre'] = ['required', 'integer'];
        } else {
            $rules['code'][] = Rule::unique($record->getTable(), 'code')->ignore($record->getKey());
            $rules['description'] = ['nullable', 'string'];
            $rules['actif'] = ['required', 'boolean'];
            if ($record instanceof Cycle) {
                $rules['ordre'] = ['required', 'integer', 'min:0', 'max:65535'];
            }
        }
        $record->fill(Validator::make($attributes, $rules)->validate());
        $record->save();

        return $this->dto($record);
    }
}
