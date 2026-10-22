<?php

namespace App\Support;

final readonly class PrerequisitePlanNode
{
    public function __construct(
        public string $kind,
        public int $id,
        public string $name,
        public bool $isTarget,
        public bool $satisfied,
        public int $depth,
        public ?int $order,
    ) {}

    public function key(): string
    {
        return $this->kind.':'.$this->id;
    }
}
