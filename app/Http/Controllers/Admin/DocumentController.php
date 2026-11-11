<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files of the company: licences, contracts, scanned bills ...
 * A file can stand alone or belong to a paper (subject_type + subject_id).
 * Files live outside the public folder and are only served to signed-in users.
 */
class DocumentController extends Controller
{
    public const SUBJECTS = ['Sale', 'Purchase', 'Account', 'Asset', 'Item', 'Payroll', 'JournalEntry', 'StockDoc'];

    public function index(Request $request): View
    {
        Gate::authorize('documents.view');

        $documents = Attachment::with('uploader')
            ->when(trim((string) $request->query('q')), fn ($q, $term) => $q->where(fn ($i) => $i->where('title', 'like', "%$term%")->orWhere('category', 'like', "%$term%")->orWhere('original_name', 'like', "%$term%")))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.documents', ['documents' => $documents, 'term' => (string) $request->query('q', ''), 'types' => \App\Models\Lookup::options('document_type')]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('documents.manage');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt'],
            'subject_type' => ['nullable', 'in:'.implode(',', self::SUBJECTS)],
            'subject_id' => ['nullable', 'integer'],
        ]);

        $file = $request->file('file');
        Attachment::create([
            'title' => $data['title'],
            'category' => $data['category'] ?? null,
            'subject_type' => $data['subject_type'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'path' => $file->store('documents/'.date('Y/m'), 'local'),
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', __('File saved.'));
    }

    public function show(Request $request): StreamedResponse
    {
        Gate::authorize('documents.view');

        $document = Attachment::findOrFail($request->route('id'));
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('documents.manage');

        $document = Attachment::findOrFail($request->route('id'));
        Storage::disk('local')->delete($document->path);
        $document->delete();

        return back()->with('success', __('File deleted.'));
    }
}
