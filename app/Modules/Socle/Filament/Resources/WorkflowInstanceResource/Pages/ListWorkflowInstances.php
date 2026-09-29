<?php

namespace App\Modules\Socle\Filament\Resources\WorkflowInstanceResource\Pages;

use App\Modules\Socle\Filament\Resources\WorkflowInstanceResource;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowInstances extends ListRecords
{
    protected static string $resource = WorkflowInstanceResource::class;
}
