<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CatalogImages;

class CatalogImageController extends Controller
{
    public function show(string $file)
    {
        $path = CatalogImages::CROPS.'/'.$file;
        abort_unless(CatalogImages::disk()->exists($path), 404);

        // Every upload gets a new random name, so a saved file never changes.
        return CatalogImages::disk()->response($path, $file, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
