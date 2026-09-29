<?php
namespace App\Modules\Pedagogie\Filament\Resources\EvaluationResource\Pages;
use App\Modules\Pedagogie\Filament\Resources\EvaluationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListEvaluations extends ListRecords { protected static string $resource = EvaluationResource::class; protected function getHeaderActions(): array { return [CreateAction::make()]; } }
