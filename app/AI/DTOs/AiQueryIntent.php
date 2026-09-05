<?php

namespace App\AI\DTOs;

class AiQueryIntent
{
    public function __construct(
        public readonly string $intent,
        public readonly array $filters = [],
        public readonly ?float $confidence = null,
        public readonly ?string $rawResponse = null
    ) {
    }
}
