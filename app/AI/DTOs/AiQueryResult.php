<?php

namespace App\AI\DTOs;

class AiQueryResult
{
    public function __construct(
        public readonly string $intent,
        public readonly string $summary,
        public readonly array $data = [],
        public readonly array $meta = [],
        public readonly bool $isSuccess = true
    ) {
    }

    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'summary' => $this->summary,
            'data' => $this->data,
            'meta' => $this->meta,
            'success' => $this->isSuccess,
        ];
    }
}
