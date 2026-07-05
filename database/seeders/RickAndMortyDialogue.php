<?php

namespace Database\Seeders;

use Illuminate\Support\Collection;

class RickAndMortyDialogue
{
    /**
     * Clean, title-length dialogue lines drawn from the show's scripts.
     *
     * @var Collection<int, string>
     */
    private static Collection $pool;

    /**
     * Every script line (unfiltered), cached for censored body generation.
     *
     * @var Collection<int, string>
     */
    private static Collection $all_lines;

    /**
     * A random line of dialogue, suitable for use as a record title.
     */
    public static function next(): string
    {
        if (! isset(self::$pool)) {
            self::load();
        }

        return self::$pool->random();
    }

    /**
     * Load the `line` column from the script CSV, keeping only title-length
     * lines (10–120 characters) that contain no bad words.
     */
    private static function load(): void
    {
        $handle = fopen(database_path('rickandmorty/rickandmorty-scripts.csv'), 'r');

        $header = array_map(
            fn (string $column): string => trim($column, "\u{FEFF} \t"),
            fgetcsv($handle, 0, ',', '"', '')
        );
        $line_index = array_search('line', $header);

        $lines = collect();

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line = trim($row[$line_index] ?? '');
            $length = mb_strlen($line);

            if ($length < 10 || $length > 120) {
                continue;
            }

            if (FilterData::hasBadWords($line)) {
                continue;
            }

            $lines->push($line);
        }

        fclose($handle);

        self::$pool = $lines->values();
    }

    /**
     * A paragraph-style block of between $min_lines and $max_lines random script
     * lines, each with bad words censored to asterisks.
     */
    public static function censoredBody(int $min_lines, int $max_lines): string
    {
        if (! isset(self::$all_lines)) {
            self::loadAll();
        }

        $count = random_int($min_lines, min($max_lines, self::$all_lines->count()));

        return self::$all_lines->random($count)
            ->map(fn (string $line): string => trim(str_replace('"', '', FilterData::censor($line))))
            ->filter()
            ->implode("\n\n");
    }

    /**
     * Load every non-empty script line into the {@see self::$all_lines} pool.
     */
    private static function loadAll(): void
    {
        $handle = fopen(database_path('rickandmorty/rickandmorty-scripts.csv'), 'r');

        fgetcsv($handle, 0, ',', '"', ''); // Skip the header row.

        $lines = collect();

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line = trim($row[0] ?? '');

            if ($line !== '') {
                $lines->push($line);
            }
        }

        fclose($handle);

        self::$all_lines = $lines->values();
    }
}
