<?php

namespace Itemvirtual\Laravel2fa\Console\Commands;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;

/**
 * USAGE
 *
 * php artisan laravel-2fa:doctrine-mappings
 * php artisan laravel-2fa:doctrine-mappings -D
 *
 */
class DoctrineMappings extends Command
{
    private const NS = 'http://doctrine-project.org/schemas/orm/doctrine-mapping';

    private const SNIPPET = __DIR__ . '/../../../database/doctrine/two-factor-fields.xml';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel-2fa:doctrine-mappings
                            {--D|dry-run : Show the changes without writing the files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add the two-factor fields to the Doctrine xml-mappings of every guard table';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $path = config('laravel-2fa.xml_mappings_path');
        $mappings = $this->validateMappings($path);

        if ($mappings === null) {
            return 1;
        }

        $fields = $this->snippetFields();
        $dryRun = (bool)$this->option('dry-run');

        foreach ($mappings as $table => $mapping) {
            $missing = array_filter($fields, function ($field) use ($mapping) {
                return !in_array($field['column'], $mapping['columns'], true) && !in_array($field['name'], $mapping['names'], true);
            });

            if (!$missing) {
                $this->line($table . ': up to date (' . $mapping['file'] . ')');
                continue;
            }

            $columns = implode(', ', array_column($missing, 'column'));

            if ($dryRun) {
                $this->info($table . ': would add ' . $columns . ' (' . $mapping['file'] . ')');
                continue;
            }

            File::put($mapping['file'], $this->insertFields($mapping, $missing));
            $this->info($table . ': added ' . $columns . ' (' . $mapping['file'] . ')');
            $this->log($mapping['file'] . ': added ' . $columns);
        }

        return 0;
    }

    private function validateMappings($path): ?array
    {
        $errors = [];

        if (!File::exists(self::SNIPPET)) {
            $errors[] = 'Fields snippet not found: ' . self::SNIPPET;
        }

        if (!is_string($path) || $path === '' || !File::isDirectory($path)) {
            $errors[] = 'The xml-mappings path does not exist: ' . var_export($path, true) . '. Check laravel-2fa.xml_mappings_path';
            return $this->reportErrors($errors);
        }

        $entities = $this->readEntities(File::glob(rtrim($path, '/') . '/*.dcm.xml'));
        $mappings = [];

        foreach (Laravel2fa::tables() as $table) {
            if (!isset($entities[$table])) {
                $errors[] = 'No xml-mapping found for table [' . $table . '] in ' . $path;
                continue;
            }

            $mappings[$table] = $entities[$table];
        }

        return $errors ? $this->reportErrors($errors) : $mappings;
    }

    private function reportErrors(array $errors): ?array
    {
        foreach ($errors as $error) {
            $this->error($error);
        }

        $this->line('No files have been modified.');

        return null;
    }

    private function snippetFields(): array
    {
        $snippet = File::get(self::SNIPPET);
        preg_match('/^([ \t]*)<field\b/m', $snippet, $indent);
        preg_match_all('/<field\b[^>]*?(?:\/>|>.*?<\/field>)/s', $snippet, $matches);

        $fields = [];
        foreach ($matches[0] as $xml) {
            preg_match('/\bname="([^"]+)"/', $xml, $name);
            preg_match('/\bcolumn="([^"]+)"/', $xml, $column);

            $lines = explode("\n", $xml);
            foreach ($lines as $i => $line) {
                if ($i > 0 && strpos($line, $indent[1]) === 0) {
                    $lines[$i] = substr($line, strlen($indent[1]));
                }
            }

            $fields[] = ['name' => $name[1], 'column' => $column[1], 'lines' => $lines];
        }

        return $fields;
    }

    private function readEntities(array $files): array
    {
        $entities = [];

        foreach ($files as $file) {
            $doc = new DOMDocument();

            if (!@$doc->load($file)) {
                continue;
            }

            $xpath = new DOMXPath($doc);
            $xpath->registerNamespace('orm', self::NS);

            foreach ($xpath->query('//orm:entity') as $index => $entity) {
                /** @var DOMElement $entity */
                $table = $entity->getAttribute('table');

                if ($table === '' || isset($entities[$table])) {
                    continue;
                }

                $columns = [];
                $names = [];
                foreach ($xpath->query('orm:id|orm:field', $entity) as $node) {
                    $names[] = $node->getAttribute('name');
                    $columns[] = $node->getAttribute('column') ?: $node->getAttribute('name');
                }
                foreach ($xpath->query('.//orm:join-column', $entity) as $node) {
                    $columns[] = $node->getAttribute('name');
                }

                $entities[$table] = ['file' => $file, 'entity' => $index, 'columns' => $columns, 'names' => $names];
            }
        }

        return $entities;
    }

    private function insertFields(array $mapping, array $fields): string
    {
        $content = File::get($mapping['file']);
        $eol = strpos($content, "\r\n") !== false ? "\r\n" : "\n";

        preg_match_all('/<\/entity>/', $content, $closings, PREG_OFFSET_CAPTURE);
        $offset = $closings[0][$mapping['entity']][1];

        $lineStart = strrpos(substr($content, 0, $offset), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $before = substr($content, $lineStart, $offset - $lineStart);

        $ownLine = trim($before) === '';
        $insertAt = $ownLine ? $lineStart : $offset;
        $indent = ($ownLine ? $before : '') . '    ';

        $xml = $ownLine ? '' : $eol;
        foreach ($fields as $field) {
            foreach ($field['lines'] as $line) {
                $xml .= $indent . rtrim($line, "\r") . $eol;
            }
        }

        return substr($content, 0, $insertAt) . $xml . substr($content, $insertAt);
    }

    private function log(string $message): void
    {
        if (config('laravel-2fa.save_logs') && config('logging.channels.laravel-2fa')) {
            Log::channel('laravel-2fa')->info('doctrine-mappings: ' . $message);
        }
    }
}
