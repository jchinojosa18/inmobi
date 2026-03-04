<?php

namespace App\Domain\Shared;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

abstract class OrganizationScopedModel extends Model
{
    use BelongsToOrganization;
}
