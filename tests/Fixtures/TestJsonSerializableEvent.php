<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

use JsonSerializable;

class TestJsonSerializableEvent implements JsonSerializable
{
    public function __construct(
        public string $name = 'test',
        public array  $data = [],
    )
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'data' => $this->data,
        ];
    }
}
