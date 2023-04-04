<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class LocalizationTablesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'localization:tables';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate localization tables';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $languages = LaravelLocalization::getSupportedLocales();

        $translations = [];
        foreach ($languages as $language => $details) {
            $translations[$language] = require config('app.lang_path') . "/$language/validation.php";




        }

        $headers = array_keys(reset($translations));
        $rows = [];

        foreach ($translations as $language => $translation) {
            $row = [$language];
            foreach ($headers as $header) {
                $row[] = $translation[$header] ?? '';
            }
            $rows[] = $row;
        }

        $this->table($headers, $rows);
    }
}
