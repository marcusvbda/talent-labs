<?php

namespace App\Collection\Data;

use App\Enums\SourceContactKind;

final readonly class SourceContactData
{
    public function __construct(
        public SourceContactKind $kind,
        public ?string $name = null,
        public ?string $title = null,
        public ?string $email = null,
    ) {}

    /**
     * @return array{kind: string, name: string|null, title: string|null, email: string|null}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'name' => $this->name,
            'title' => $this->title,
            'email' => $this->email,
        ];
    }
}
