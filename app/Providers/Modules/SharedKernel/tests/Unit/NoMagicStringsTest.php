<?php

/**
 * @return array<string, string> file => contents of every context source file, migrations and tests excluded
 */
function contextSources(): array
{
    $files = [];

    foreach (sourceRoots() as $root) {
        $files = [...$files, ...glob($root.'/*/{Application,Domain,Infrastructure}/{,*/,*/*/}*.php', GLOB_BRACE)];
    }

    return array_combine($files, array_map('file_get_contents', $files));
}

/**
 * @return list<string> the lines of every source that match $pattern, outside a constant declaration
 */
function offendingLines(string $pattern): array
{
    $offenders = [];

    foreach (contextSources() as $file => $source) {
        foreach (explode("\n", $source) as $number => $line) {
            if (preg_match($pattern, $line) === 1 && ! str_contains($line, ' const ')) {
                $offenders[] = basename($file).':'.($number + 1).' '.trim($line);
            }
        }
    }

    return $offenders;
}

describe('no magic strings', function (): void {
    it('names a table through a constant, never inline', function (): void {
        expect(offendingLines("/(DB::table|->table)\\(\\s*'/"))->toBe([]);
    });

    it('names an event type through a constant, never inline', function (): void {
        expect(offendingLines("/'(kitchen|front_of_house|billing)\\.[a-z_]+'/"))->toBe([]);
    });

    it('names a status, a kind or a course through a constant, never inline', function (): void {
        expect(offendingLines("/'(occupied|free|open|paid|pending|food|alcohol|starter|main|dessert|drink)'/"))
            ->toBe([]);
    });
});
