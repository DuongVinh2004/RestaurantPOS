<?php

declare(strict_types=1);

namespace App\Platform\QualityAssurance\Harness\Application\Builders;

use Illuminate\Support\Facades\File;
use SimpleXMLElement;
use Throwable;

trait BuildsPhpUnitTestingEnvironment
{
    /**
     * @return array<string, string>
     */
    protected function buildPhpUnitTestingEnvironment(): array
    {
        $environment = [
            'APP_ENV' => 'testing',
        ];

        $phpUnitPath = base_path('phpunit.xml');
        if (! File::exists($phpUnitPath)) {
            return $environment;
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string((string) File::get($phpUnitPath), SimpleXMLElement::class, LIBXML_NONET);
        } catch (Throwable) {
            $xml = false;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }

        if (! $xml instanceof SimpleXMLElement || ! isset($xml->php)) {
            return $environment;
        }

        foreach ($xml->php->env as $envNode) {
            $name = trim((string) ($envNode['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $environment[$name] = (string) ($envNode['value'] ?? '');
        }

        return $environment;
    }
}
