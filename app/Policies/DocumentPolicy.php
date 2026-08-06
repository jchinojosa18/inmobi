<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('documents.view');
    }

    public function view(User $user, Document $document): bool
    {
        return $this->sameOrganization($user, $document)
            && $user->can('documents.view');
    }

    public function upload(User $user): bool
    {
        return $user->can('documents.upload');
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->sameOrganization($user, $document)
            && $user->can('documents.delete');
    }

    private function sameOrganization(User $user, Document $document): bool
    {
        return (int) $user->organization_id === (int) $document->organization_id;
    }
}
