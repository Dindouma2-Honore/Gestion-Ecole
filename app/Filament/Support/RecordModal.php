<?php

namespace App\Filament\Support;

use Filament\Actions\ViewAction;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Presentation only: reuse the table's existing actions and their restrictions. */
final class RecordModal
{
    public static function configure(Table $table, string $resource): Table
    {
        foreach (['edit', 'delete'] as $name) {
            if ($action = $table->getAction($name)) {
                ($name === 'edit') ? AmbassadorsDesign::goldenFormModal($action) : AmbassadorsDesign::goldenDialogModal($action);
                $action->url(null);
            }
        }
        $view = $table->getAction('view');
        if (! $view) {
            $view = ViewAction::make()->authorize(fn (Model $record): bool => $resource::canView($record));
            $table->pushRecordActions([$view]);
        }
        AmbassadorsDesign::goldenFormModal($view)->url(null)
            ->extraModalFooterActions(function (Model $record) use ($table, $resource): array {
                $actions = [];
                foreach (['edit', 'delete'] as $name) {
                    if (! ($source = $table->getAction($name))) {
                        continue;
                    }
                    $action = $source->group(null)->record($record)->cancelParentActions();
                    if ($name === 'edit') {
                        $action->schema(fn (Schema $schema): Schema => $resource::form($schema->model($record)->operation('edit')));
                    }
                    $actions[] = $action;
                }

                return $actions;
            });

        return $table->recordUrl(null)->recordAction(fn (Model $record): ?string => $resource::canView($record) ? 'view' : null);
    }
}
