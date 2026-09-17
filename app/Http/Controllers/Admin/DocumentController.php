<?php

namespace App\Http\Controllers\Admin;

use App\Models\Document;

class DocumentController extends CatalogController
{
    protected string $modelClass = Document::class;

    protected string $resource = 'documents';

    protected string $viewFolder = 'admin.documents';

    protected string $label = 'Document';

    protected string $plural = 'Documents';
}
