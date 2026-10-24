<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function show(Document $document): View
    {
        Gate::authorize('view', $document);

        $document->loadCount(['sections', 'segments']);

        return view('documents.show', [
            'document' => $document,
        ]);
    }
}
