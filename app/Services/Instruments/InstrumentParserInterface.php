<?php

namespace App\Services\Instruments;

interface InstrumentParserInterface
{
    public function parse(string $rawData): array;
    public function supportedFormats(): array;
}
