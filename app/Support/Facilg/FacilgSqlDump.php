<?php

declare(strict_types=1);

namespace App\Support\Facilg;

use Generator;
use RuntimeException;

class FacilgSqlDump
{
    /** @return Generator<int, array<string, mixed>> */
    public function rows(string $path, string $wantedTable): Generator
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Impossible d'ouvrir le dump : {$path}");
        }

        $statement = '';
        while (($line = fgets($handle)) !== false) {
            if ($statement === '' && ! str_starts_with($line, 'INSERT INTO `')) {
                continue;
            }
            $statement .= $line;
            if (! preg_match('/;\s*$/', $line)) {
                continue;
            }

            if (preg_match('/^INSERT INTO `([^`]+)` \((.+)\) VALUES\s*(.+);\s*$/s', $statement, $matches)) {
                $table = $matches[1];
                if ($table === $wantedTable) {
                    $columns = array_map(fn (string $column): string => trim($column, " `\t\r\n"), explode(',', $matches[2]));
                    foreach ($this->parseTuples($matches[3]) as $values) {
                        if (count($columns) === count($values)) {
                            yield array_combine($columns, $values);
                        }
                    }
                }
            }
            $statement = '';
        }
        fclose($handle);
    }

    /** @return Generator<int, list<mixed>> */
    private function parseTuples(string $sql): Generator
    {
        $tuple = [];
        $value = '';
        $quoted = false;
        $escaped = false;
        $inTuple = false;
        $length = strlen($sql);

        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            if (! $inTuple) {
                if ($character === '(') {
                    $inTuple = true;
                    $tuple = [];
                    $value = '';
                }

                continue;
            }
            if ($quoted) {
                if ($escaped) {
                    $value .= match ($character) {
                        'n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0", default => $character,
                    };
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === "'") {
                    if (($sql[$index + 1] ?? null) === "'") {
                        $value .= "'";
                        $index++;
                    } else {
                        $quoted = false;
                    }
                } else {
                    $value .= $character;
                }

                continue;
            }
            if ($character === "'") {
                $quoted = true;
            } elseif ($character === ',') {
                $tuple[] = $this->normalise($value);
                $value = '';
            } elseif ($character === ')') {
                $tuple[] = $this->normalise($value);
                yield $tuple;
                $inTuple = false;
            } else {
                $value .= $character;
            }
        }
    }

    private function normalise(string $value): mixed
    {
        $value = trim($value);
        if (strcasecmp($value, 'NULL') === 0) {
            return null;
        }
        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        return $value;
    }
}
