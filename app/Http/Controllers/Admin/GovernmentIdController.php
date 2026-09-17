<?php

namespace App\Http\Controllers\Admin;

use App\Models\GovernmentId;

class GovernmentIdController extends CatalogController
{
    protected string $modelClass = GovernmentId::class;

    protected string $resource = 'government-ids';

    protected string $viewFolder = 'admin.government_ids';

    protected string $label = 'Government ID';

    protected string $plural = 'Government IDs';
}
