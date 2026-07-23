<?php

namespace App\Support;

enum ContractDocumentCategory: string
{
    case Contract = 'contract';
    case Guarantor = 'guarantor';
    case Id = 'id';
    case AddressProof = 'address_proof';
    case Payslip = 'payslip';
    case BankStatements = 'bank_statements';
    case CommercialReferences = 'commercial_references';

    public function label(): string
    {
        return __('contracts.document_categories.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
