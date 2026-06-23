<?php

namespace App\Livewire\Concerns;

use App\Support\TextCase;

trait NormalizesPropertyUppercaseFields
{
    public function updatedName(?string $value): void
    {
        $this->name = TextCase::upperLive($value) ?? '';
    }

    public function updatedCode(?string $value): void
    {
        $this->code = TextCase::upperLive($value);
    }

    public function updatedAddress(?string $value): void
    {
        $this->address = TextCase::upperLive($value);
    }

    protected function normalizePropertyUppercaseFields(): void
    {
        $this->name = TextCase::upperRequired($this->name);
        $this->code = TextCase::upper($this->code);
        $this->address = TextCase::upper($this->address);
    }
}
