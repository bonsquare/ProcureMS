<?php

namespace App\Console\Commands;

use App\Services\CseCatalogLoader;
use App\Services\CseCatalogReader;
use Illuminate\Console\Command;
use JsonException;
use RuntimeException;

/** Loads a year of the Procurement Service catalog (the APP-CSE Part I) from the PS workbook or from the JSON made from it. */
class ImportCseCatalog extends Command
{
    protected $signature = 'cse:import-catalog
        {file : A Procurement Service APP-CSE .xlsx workbook, or a catalog .json file}
        {--year= : The catalog year to load (for example 2026)}
        {--to-json= : Write the items of the workbook to this JSON file and stop; nothing is loaded}';

    protected $description = 'Load a year of the Procurement Service catalog used by the APP-CSE';

    public function handle(CseCatalogReader $reader, CseCatalogLoader $loader): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error("The file {$file} does not exist.");

            return self::FAILURE;
        }

        try {
            $items = strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'json'
                ? json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)
                : $reader->read($file);

            if ($this->option('to-json')) {
                file_put_contents((string) $this->option('to-json'), json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
                $this->info(count($items).' catalog items written to '.$this->option('to-json').'.');

                return self::SUCCESS;
            }

            $year = (string) $this->option('year');
            if (! ctype_digit($year) || (int) $year < 2000) {
                $this->error('Give the catalog year with --year=2026.');

                return self::FAILURE;
            }

            $this->info($loader->load($items, (int) $year)." catalog items loaded for {$year}.");

            return self::SUCCESS;
        } catch (RuntimeException|JsonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
