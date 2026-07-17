<?php

namespace App\Livewire\Concerns;

use App\Support\TextCase;

trait NormalizesPropertyUppercaseFields
{
    protected function normalizePropertyUppercaseFields(): void
    {
        $this->name = TextCase::upperRequired($this->name);
        $this->code = TextCase::upper($this->code);
        $this->address = TextCase::upper($this->address);
    }
}
