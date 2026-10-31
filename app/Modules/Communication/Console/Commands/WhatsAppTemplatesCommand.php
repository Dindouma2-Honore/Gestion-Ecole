<?php

namespace App\Modules\Communication\Console\Commands;

use App\Models\User;
use App\Modules\Communication\Models\CommunicationTemplate;
use App\Modules\Communication\Services\WhatsAppDispatcher;
use App\Modules\Communication\Services\WhatsAppTemplateService;
use App\Modules\Communication\Services\WhatsAppTemplateValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WhatsAppTemplatesCommand extends Command
{
    protected $signature = 'whatsapp:templates {operation : preview|ready|submit|sync|activate|disable|test|sample} {--template= : Code interne} {--language=fr} {--actor= : Identifiant du profil habilité exécutant la commande} {--recipient=} {--confirm : Confirmer la soumission ou l’envoi demandé}';

    protected $description = 'Gérer un template WhatsApp avec les mêmes contrôles et le même audit que Filament';

    public function handle(WhatsAppTemplateService $service): int
    {
        try {
            $actor = User::find($this->option('actor'));
            if (! $actor || $actor->statut !== 'actif') {
                $this->error('Un profil actif --actor est requis.');

                return self::FAILURE;
            }
            Auth::setUser($actor);
            $operation = $this->argument('operation');
            if ($operation === 'sync' && ! $this->option('template')) {
                $this->info($service->syncTemplates().' templates synchronisés.');

                return self::SUCCESS;
            }
            $t = CommunicationTemplate::where('waba_id', (string) config('whatsapp.waba_id'))->where('code', $this->option('template'))->where('language', $this->option('language'))->firstOrFail();
            Gate::authorize('view_whatsapp_templates');
            if (in_array($operation, ['submit', 'test', 'sample']) && ! $this->option('confirm')) {
                $this->error('Prévisualisez le contenu puis utilisez --confirm pour cette opération externe.');

                return self::FAILURE;
            }
            switch ($operation) {
                case 'preview': Gate::authorize('preview_whatsapp_template');
                    $this->line(app(WhatsAppTemplateValidator::class)->preview($t));
                    break;
                case 'ready': $service->markReady($t);
                    break;
                case 'submit': $service->submitToMeta($t);
                    break;
                case 'sync': $service->syncTemplateStatus($t);
                    break;
                case 'activate': $service->activate($t);
                    break;
                case 'disable': $service->disable($t);
                    break;
                case 'sample': $service->prepareDocumentExample($t);
                    break;
                case 'test':
                    $message = app(WhatsAppDispatcher::class)->dispatchTemplate($t, (string) $this->option('recipient'));
                    $this->info('Message #'.$message->id.' : '.$message->status);
                    break;
                default: $this->error('Opération inconnue.');

                    return self::FAILURE;
            }
            $t->refresh();
            $this->info($t->code.' : '.$t->local_status.' / '.$t->meta_status);

            return self::SUCCESS;
        } catch (ValidationException $e) {
            $this->error(implode(' ', $e->validator->errors()->all()));

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Opération non confirmée. Vérifiez les habilitations, la configuration et la connectivité. Code : '.$e->getCode());

            return self::FAILURE;
        } finally {
            Auth::forgetGuards();
        }
    }
}
