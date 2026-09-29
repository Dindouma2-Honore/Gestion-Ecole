<?php

namespace App\Modules\Socle\Filament\Resources\WorkflowDefinitionResource\Pages;

use App\Modules\Socle\Filament\Resources\WorkflowDefinitionResource;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowDefinitions extends ListRecords
{
    protected static string $resource = WorkflowDefinitionResource::class;
}
