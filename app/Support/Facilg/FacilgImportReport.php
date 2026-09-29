<?php

declare(strict_types=1);

namespace App\Support\Facilg;

use Illuminate\Support\Facades\Log;

class FacilgImportReport
{
    /** @var array<string, array{importees:int,ignorees:int,erreurs:int}> */
    private array $stats = [];

    /** @var list<string> */
    private array $messages = [];

    public function count(string $table, string $type): void
    {
        $this->stats[$table] ??= ['importees' => 0, 'ignorees' => 0, 'erreurs' => 0];
        $this->stats[$table][$type]++;
    }

    public function warning(string $table, string $message): void
    {
        $this->count($table, 'erreurs');
        $this->messages[] = "[{$table}] {$message}";
        Log::channel('facilg')->warning($message, ['source_table' => $table]);
    }

    /** @return array<string, array{importees:int,ignorees:int,erreurs:int}> */
    public function stats(): array
    {
        return $this->stats;
    }

    /** @return list<string> */
    public function messages(): array
    {
        return $this->messages;
    }
}
