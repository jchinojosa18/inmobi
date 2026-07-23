<?php

namespace Tests\Unit\Support;

use App\Support\ContractDocumentCategory;
use Tests\TestCase;

class ContractDocumentCategoryTest extends TestCase
{
    public function test_options_returns_all_seven_categories(): void
    {
        $options = ContractDocumentCategory::options();

        $this->assertCount(7, $options);
        $this->assertArrayHasKey('contract', $options);
        $this->assertArrayHasKey('commercial_references', $options);
        $this->assertSame(__('contracts.document_categories.contract'), $options['contract']);
    }
}
