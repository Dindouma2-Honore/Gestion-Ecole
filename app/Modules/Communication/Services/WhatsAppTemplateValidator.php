<?php

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Models\CommunicationTemplate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WhatsAppTemplateValidator
{
    public function validate(CommunicationTemplate $t): array
    {
        $data = $t->toArray();
        $data['header_handle'] = $t->header_handle;
        Validator::make($data, [
            'name' => 'required|string|max:255', 'code' => 'required|regex:/^[a-z][a-z0-9_]*$/|max:150',
            'provider_template_name' => 'required|regex:/^[a-z0-9_]+$/|max:150',
            'language' => ['required', Rule::in(array_keys(config('whatsapp.templates.languages')))],
            'provider_category' => ['required', Rule::in(array_keys(config('whatsapp.templates.categories')))],
            'audience' => ['required', Rule::in(['PARENT', 'TEACHER', 'STAFF', 'ADMINISTRATION', 'TECHNICAL', 'CANDIDATE'])],
            'module' => ['required', Rule::in(['SCOLARITE', 'PEDAGOGIE', 'ASSIDUITE', 'RH', 'FINANCES', 'VIE_SCOLAIRE', 'LOGISTIQUE', 'COMMUNICATION', 'TECHNIQUE'])],
            'header_type' => ['required', Rule::in(['NONE', 'TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT'])],
            'header_text' => 'nullable|string|max:60', 'body' => 'required|string|max:1024', 'footer' => 'nullable|string|max:60',
            'variables_schema' => 'nullable|array', 'variables_schema.*.position' => 'required|integer|min:1',
            'variables_schema.*.component' => ['required', Rule::in(['BODY', 'HEADER'])],
            'variables_schema.*.key' => 'required|regex:/^[a-z][a-z0-9_]*$/',
            'variables_schema.*.example' => 'required|string|max:200',
            'buttons' => 'nullable|array|max:3', 'buttons.*.type' => ['required', Rule::in(['QUICK_REPLY', 'URL', 'PHONE_NUMBER'])],
            'buttons.*.text' => 'required|string|max:25',
        ])->validate();
        if ($t->header_type === 'TEXT' && blank($t->header_text)) {
            $this->invalid('header_text', 'Le texte de l’en-tête est obligatoire.');
        }
        if (in_array($t->header_type, ['IMAGE', 'VIDEO', 'DOCUMENT']) && blank($t->header_handle)) {
            $this->invalid('header_handle', 'Un exemple média Meta (handle de téléversement) est requis.');
        }
        if (collect($t->variables_schema ?? [])->pluck('key')->unique()->count() !== count($t->variables_schema ?? [])) {
            $this->invalid('variables_schema', 'Les clés de variables doivent être uniques entre les composants.');
        }
        $components = [];
        if ($t->header_type !== 'NONE') {
            $header = ['type' => 'HEADER', 'format' => $t->header_type];
            if ($t->header_type === 'TEXT') {
                $header['text'] = $t->header_text;
                $examples = $this->examples($t, 'HEADER', $t->header_text);
                if (count($examples) > 1) {
                    $this->invalid('header_text', 'Un seul paramètre est supporté dans l’en-tête texte.');
                }
                if ($examples) {
                    $header['example'] = ['header_text' => $examples];
                }
            } else {
                $header['example'] = ['header_handle' => [$t->header_handle]];
            }
            $components[] = $header;
        }
        if ($t->header_type !== 'TEXT' && collect($t->variables_schema)->contains('component', 'HEADER')) {
            $this->invalid('variables_schema', 'Les variables HEADER nécessitent un en-tête texte.');
        }
        $body = ['type' => 'BODY', 'text' => $t->body];
        $examples = $this->examples($t, 'BODY', $t->body);
        if ($examples) {
            $body['example'] = ['body_text' => [$examples]];
        }
        $components[] = $body;
        if (filled($t->footer)) {
            if (str_contains($t->footer, '{{') || str_contains($t->footer, '}}')) {
                $this->invalid('footer', 'Le pied de page ne peut pas contenir de variables.');
            }
            $components[] = ['type' => 'FOOTER', 'text' => $t->footer];
        }
        $buttons = [];
        foreach ($t->buttons ?? [] as $button) {
            $b = ['type' => $button['type'], 'text' => $button['text']];
            if (str_contains($b['text'], '{{') || str_contains($b['text'], '}}')) {
                $this->invalid('buttons', 'Le libellé du bouton doit être fixe.');
            }
            if ($b['type'] === 'URL') {
                $url = $button['url'] ?? '';
                if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https' || str_contains($url, '{{') || parse_url($url, PHP_URL_USER)) {
                    $this->invalid('buttons', 'Les boutons URL nécessitent une adresse HTTPS fixe sans identifiants.');
                }
                $b['url'] = $url;
            }
            if ($b['type'] === 'PHONE_NUMBER') {
                if (! preg_match('/^\+[1-9][0-9]{7,14}$/D', $button['phone_number'] ?? '')) {
                    $this->invalid('buttons', 'Numéro international requis pour le bouton téléphone.');
                }
                $b['phone_number'] = $button['phone_number'];
            }
            $buttons[] = $b;
        }
        $types = array_count_values(array_column($buttons, 'type'));
        if (($types['URL'] ?? 0) > 2 || ($types['PHONE_NUMBER'] ?? 0) > 1 || (isset($types['QUICK_REPLY']) && count($types) > 1)) {
            $this->invalid('buttons', 'Utilisez jusqu’à trois réponses rapides, ou deux URL et un téléphone.');
        }
        if ($buttons) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons];
        }

        return ['name' => $t->provider_template_name, 'language' => $t->language, 'category' => $t->provider_category, 'components' => $components];
    }

    private function examples(CommunicationTemplate $t, string $component, string $text): array
    {
        preg_match_all('/\{\{([1-9][0-9]*)\}\}/', $text, $matches);
        $remaining = preg_replace('/\{\{([1-9][0-9]*)\}\}/', '', $text);
        if (str_contains($remaining, '{{') || str_contains($remaining, '}}')) {
            $this->invalid('variables_schema', 'Utilisez des positions numériques comme {{1}}.');
        }
        $positions = array_values(array_unique(array_map('intval', $matches[1])));
        sort($positions);
        if ($positions && $positions !== range(1, count($positions))) {
            $this->invalid('variables_schema', 'Les positions doivent être continues à partir de 1.');
        }
        if (preg_match('/^\s*\{\{|\}\}\s*$/', $text)) {
            $this->invalid('body', 'Le texte ne doit pas commencer ou finir par une variable.');
        }
        $schema = collect($t->variables_schema ?? [])->where('component', $component)->sortBy('position')->values();
        if ($schema->pluck('position')->map(fn ($p) => (int) $p)->all() !== $positions || $schema->pluck('key')->unique()->count() !== $schema->count()) {
            $this->invalid('variables_schema', 'Déclarez exactement une variable et un exemple par position et composant.');
        }

        return $schema->pluck('example')->all();
    }

    public function preview(CommunicationTemplate $t): string
    {
        $render = function (string $text, string $component) use ($t): string {
            $map = [];
            foreach ($t->variables_schema ?? [] as $v) {
                if (($v['component'] ?? 'BODY') === $component) {
                    $map['{{'.($v['position'] ?? '').'}}'] = (string) ($v['example'] ?? '');
                }
            }

            return strtr($text, $map);
        };

        return implode("\n\n", array_filter([
            $t->header_type === 'TEXT' ? $render($t->header_text ?? '', 'HEADER') : ($t->header_type !== 'NONE' ? '['.$t->header_type.' — exemple média]' : ''),
            $render($t->body ?? '', 'BODY'), $t->footer,
        ]));
    }

    public function hash(array $components): string
    {
        $semantic = array_map(fn ($c) => array_intersect_key($c, array_flip(['type', 'format', 'text', 'buttons'])), $components);
        $sort = function (&$value) use (&$sort): void {
            if (! is_array($value)) {
                return;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as &$child) {
                $sort($child);
            }
        };
        $sort($semantic);

        return hash('sha256', json_encode($semantic, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
