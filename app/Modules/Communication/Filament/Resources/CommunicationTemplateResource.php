<?php

namespace App\Modules\Communication\Filament\Resources;

use App\Filament\Support\RecordModal;
use App\Modules\Communication\Filament\Resources\CommunicationTemplateResource\Pages;
use App\Modules\Communication\Models\CommunicationTemplate;
use App\Modules\Communication\Services\WhatsAppDispatcher;
use App\Modules\Communication\Services\WhatsAppTemplateService;
use App\Modules\Communication\Services\WhatsAppTemplateValidator;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CommunicationTemplateResource extends Resource
{
    protected static ?string $model = CommunicationTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Communication';

    protected static ?string $navigationLabel = 'Templates';

    protected static ?string $modelLabel = 'template WhatsApp';

    protected static ?string $pluralModelLabel = 'Templates WhatsApp';

    protected static ?string $slug = 'communication/templates';

    public const LOCAL = ['DRAFT' => 'Brouillon', 'READY' => 'Prêt', 'ACTIVE' => 'Actif', 'DISABLED' => 'Désactivé'];

    public const META = ['NOT_SUBMITTED' => 'Non soumis', 'PENDING' => 'En attente Meta', 'APPROVED' => 'Approuvé par Meta', 'REJECTED' => 'Rejeté par Meta', 'PAUSED' => 'Suspendu', 'DISABLED' => 'Désactivé par Meta', 'UNKNOWN' => 'Inconnu'];

    public static function allowed(string $permission): bool
    {
        return auth()->user()?->statut === 'actif' && (auth()->user()?->can($permission) ?? false);
    }

    public static function canViewAny(): bool
    {
        return static::allowed('view_whatsapp_templates');
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny() && $record->waba_id === (string) config('whatsapp.waba_id');
    }

    public static function canCreate(): bool
    {
        return static::canViewAny() && static::allowed('create_whatsapp_template');
    }

    public static function canEdit(Model $record): bool
    {
        return static::canView($record) && static::allowed('update_whatsapp_template') && $record->editable();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('waba_id', (string) config('whatsapp.waba_id'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informations')->schema([
                TextInput::make('name')->label('Nom interne')->required()->maxLength(255),
                TextInput::make('code')->label('Code interne')->required()->regex('/^[a-z][a-z0-9_]*$/')->maxLength(150),
                TextInput::make('provider_template_name')->label('Nom Meta')->helperText('Lettres minuscules, chiffres et underscores.')->required()->regex('/^[a-z0-9_]+$/')->maxLength(150),
                Select::make('audience')->label('Audience')->options(['PARENT' => 'Parents', 'TEACHER' => 'Enseignants', 'STAFF' => 'Personnel', 'ADMINISTRATION' => 'Administration', 'TECHNICAL' => 'Technique', 'CANDIDATE' => 'Candidats'])->required(),
                Select::make('module')->options(array_combine(['SCOLARITE', 'PEDAGOGIE', 'ASSIDUITE', 'RH', 'FINANCES', 'VIE_SCOLAIRE', 'LOGISTIQUE', 'COMMUNICATION', 'TECHNIQUE'], ['Scolarité', 'Pédagogie', 'Assiduité', 'RH', 'Finances', 'Vie scolaire', 'Logistique', 'Communication', 'Technique']))->required(),
                TextInput::make('trigger_event')->label('Événement déclencheur')->maxLength(255)->helperText('Documenté ici ; les événements métier restent en simulation.'),
                Select::make('language')->label('Langue Meta')->options(config('whatsapp.templates.languages'))->default('fr')->required(),
                Select::make('provider_category')->label('Catégorie demandée')->options(config('whatsapp.templates.categories'))->default('UTILITY')->required(),
            ])->columns(2),
            Section::make('Contenu')->schema([
                Select::make('header_type')->label('En-tête')->options(['NONE' => 'Aucun', 'TEXT' => 'Texte', 'IMAGE' => 'Image', 'VIDEO' => 'Vidéo', 'DOCUMENT' => 'Document'])->default('NONE')->required()->live(),
                TextInput::make('header_text')->label('Texte de l’en-tête')->maxLength(60)->visible(fn ($get) => $get('header_type') === 'TEXT'),
                TextInput::make('header_handle')->label('Exemple média Meta')->password()->revealable(false)->autocomplete(false)
                    ->helperText('Handle obtenu par l’API de téléversement reprenable Meta, pas un media ID. Conservé chiffré.')
                    ->visible(fn ($get) => in_array($get('header_type'), ['IMAGE', 'VIDEO', 'DOCUMENT'])),
                Textarea::make('body')->label('Corps du message')->required()->rows(8)->maxLength(1024)->helperText('Variables positionnelles : {{1}}, {{2}}, etc.'),
                TextInput::make('footer')->label('Pied de page')->maxLength(60),
                Repeater::make('variables_schema')->label('Variables et exemples fictifs')->schema([
                    Select::make('component')->label('Composant')->options(['BODY' => 'Corps', 'HEADER' => 'En-tête'])->default('BODY')->required(),
                    TextInput::make('position')->label('Position')->numeric()->minValue(1)->required(),
                    TextInput::make('key')->label('Clé')->required(), TextInput::make('example')->label('Exemple fictif')->required()->maxLength(200),
                ])->columns(2)->defaultItems(0),
                Repeater::make('buttons')->label('Boutons')->schema([
                    Select::make('type')->options(['QUICK_REPLY' => 'Réponse rapide', 'URL' => 'Lien HTTPS fixe', 'PHONE_NUMBER' => 'Téléphone'])->required()->live(),
                    TextInput::make('text')->label('Libellé')->required()->maxLength(25),
                    TextInput::make('url')->label('Lien HTTPS')->visible(fn ($get) => $get('type') === 'URL'),
                    TextInput::make('phone_number')->label('Numéro international')->visible(fn ($get) => $get('type') === 'PHONE_NUMBER'),
                ])->defaultItems(0)->maxItems(3),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return RecordModal::configure($table->columns([
            TextColumn::make('name')->label('Nom')->searchable(), TextColumn::make('provider_template_name')->label('Nom Meta')->searchable(),
            TextColumn::make('audience')->label('Audience')->toggleable(), TextColumn::make('module')->toggleable(),
            TextColumn::make('language')->label('Langue'), TextColumn::make('provider_category_effective')->label('Catégorie Meta')->placeholder('Non synchronisée'),
            TextColumn::make('local_status')->label('Statut local')->badge()->formatStateUsing(fn ($state) => self::LOCAL[$state] ?? $state),
            TextColumn::make('meta_status')->label('Statut Meta')->badge()->formatStateUsing(fn ($state) => self::META[$state] ?? $state)->color(fn ($state) => match ($state) {
                'APPROVED' => 'success','REJECTED','DISABLED' => 'danger','PAUSED','PENDING' => 'warning',default => 'gray'
            }),
            TextColumn::make('quality_rating')->label('Qualité')->placeholder('Inconnue')->formatStateUsing(fn ($state) => ['GREEN' => 'Bonne', 'YELLOW' => 'Moyenne', 'RED' => 'Faible'][$state] ?? 'Inconnue'),
            TextColumn::make('last_synced_at')->label('Dernière synchro')->dateTime()->placeholder('Jamais'),
        ])->filters([
            SelectFilter::make('local_status')->label('Statut local')->options(self::LOCAL),
            SelectFilter::make('meta_status')->label('Statut Meta')->options(self::META),
        ])->recordActions([ViewAction::make(), EditAction::make()->mutateRecordDataUsing(fn (array $data): array => Pages\EditCommunicationTemplate::fillModalData($data))->using(fn (Model $record, array $data) => Pages\EditCommunicationTemplate::updateFromModal($record, $data)), ...static::workflowActions()])->defaultSort('id', 'desc'), static::class);
    }

    public static function workflowActions(): array
    {
        return [
            Action::make('preview')->label('Prévisualiser')->icon('heroicon-o-eye')->visible(fn () => self::allowed('preview_whatsapp_template'))
                ->modalContent(function (CommunicationTemplate $record) {
                    Gate::authorize('preview_whatsapp_template');

                    return view('communication::templates.preview', ['text' => app(WhatsAppTemplateValidator::class)->preview($record)]);
                })
                ->modalSubmitAction(false)->modalCancelActionLabel('Fermer'),
            self::workflow('documentExample', 'Préparer un exemple PDF', 'submit_whatsapp_template', 'prepareDocumentExample')->modalDescription('Un bulletin entièrement fictif sera téléversé chez Meta comme exemple du template.')->visible(fn (CommunicationTemplate $record) => self::allowed('submit_whatsapp_template') && self::allowed('update_whatsapp_template') && $record->editable() && $record->header_type === 'DOCUMENT'),
            self::workflow('ready', 'Valider localement', 'update_whatsapp_template', 'markReady')->visible(fn (CommunicationTemplate $record) => self::allowed('update_whatsapp_template') && $record->editable()),
            self::workflow('submit', 'Soumettre à Meta', 'submit_whatsapp_template', 'submitToMeta')
                ->modalDescription('Le contenu et ses exemples seront envoyés au WABA configuré. Meta décide de l’approbation.')
                ->visible(fn (CommunicationTemplate $record) => self::allowed('submit_whatsapp_template') && $record->editable() && $record->local_status === 'READY'),
            self::workflow('sync', 'Actualiser le statut', 'sync_whatsapp_template', 'syncTemplateStatus'),
            self::workflow('activate', 'Activer', 'activate_whatsapp_template', 'activate')->visible(fn (CommunicationTemplate $record) => self::allowed('activate_whatsapp_template') && $record->meta_status === 'APPROVED' && $record->local_status !== 'ACTIVE'),
            self::workflow('disable', 'Désactiver localement', 'disable_whatsapp_template', 'disable'),
            Action::make('duplicate')->label('Dupliquer')->visible(fn () => self::allowed('create_whatsapp_template'))->requiresConfirmation()
                ->action(function (CommunicationTemplate $record) {
                    $t = app(WhatsAppTemplateService::class)->duplicate($record);

                    return redirect(static::getUrl('edit', ['record' => $t]));
                }),
            Action::make('test')->label('Tester')->visible(fn (CommunicationTemplate $record) => self::allowed('test_whatsapp_template') && $record->local_status === 'ACTIVE' && $record->meta_status === 'APPROVED')
                ->schema([TextInput::make('recipient')->label('Numéro autorisé Meta')->default(fn () => config('whatsapp.allowlist')[0] ?? '')->required(), TextInput::make('media_id')->label('Identifiant du média de test Meta')->helperText('Requis pour une image ou vidéo. Pour un document, laisser vide pour un PDF fictif.')->maxLength(200)])
                ->requiresConfirmation()->modalDescription('Envoi des exemples fictifs au numéro autorisé. Les documents utilisent un PDF de démonstration.')
                ->action(function (CommunicationTemplate $record, array $data) {
                    static::run(function () use ($record, $data) {
                        $m = app(WhatsAppDispatcher::class)->dispatchTemplate($record, $data['recipient'], $data['media_id'] ?? null);

                        return 'Message #'.$m->id.' en file d’attente. Consultez son statut dans la fiche.';
                    });
                }),
        ];
    }

    private static function workflow(string $name, string $label, string $permission, string $method): Action
    {
        return Action::make($name)->label($label)->visible(fn () => self::allowed($permission))->requiresConfirmation()
            ->action(function (CommunicationTemplate $record) use ($method, $permission) {
                Gate::authorize($permission);
                static::run(function () use ($method, $record) {
                    app(WhatsAppTemplateService::class)->{$method}($record);

                    return $method === 'submitToMeta' ? 'Template soumis à Meta. Approbation en attente.' : 'Opération terminée.';
                });
            });
    }

    public static function run(callable $operation): void
    {
        try {
            $text = $operation();
            Notification::make()->title($text)->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Vérification nécessaire')->body(implode(' ', $e->validator->errors()->all()))->danger()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Opération indisponible')->body('Vérifiez la configuration, les permissions et le statut du template. Aucun succès Meta confirmé.')->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCommunicationTemplates::route('/'), 'create' => Pages\CreateCommunicationTemplate::route('/create'),
            'view' => Pages\ViewCommunicationTemplate::route('/{record}'), 'edit' => Pages\EditCommunicationTemplate::route('/{record}/edit')];
    }
}
