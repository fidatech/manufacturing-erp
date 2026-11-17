<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Attachment;
use App\Models\Lookup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employees' papers (tazkira, contract, certificates, photo ...) inside Human Resources.
 * They are ordinary files of the File Manager that belong to an employee's account,
 * so HR staff can keep them without access to every other file of the company.
 */
class EmployeeDocumentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('hr.view');

        $filters = $request->validate(['employee' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:100']]);
        $employees = Account::ofType('employee')->orderBy('name')->get(['id', 'name', 'meta']);

        $documents = $this->employeeFiles()->with('uploader')
            ->when($filters['employee'] ?? null, fn ($q, $id) => $q->where('subject_id', $id))
            ->when(trim((string) ($filters['q'] ?? '')), fn ($q, $term) => $q->where(fn ($i) => $i->where('title', 'like', "%$term%")->orWhere('category', 'like', "%$term%")->orWhere('original_name', 'like', "%$term%")))
            ->orderByDesc('id')
            ->paginate(per_page(25))->withQueryString();

        return view('hr.documents', [
            'documents' => $documents,
            'filters' => $filters,
            'employees' => $employees,
            'names' => $employees->pluck('name', 'id'),
            'types' => Lookup::options('document_type'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('hr.manage');

        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ], [], ['employee_id' => __('employee')]);
        $employee = Account::ofType('employee')->findOrFail($data['employee_id']);

        $file = $request->file('file');
        Attachment::create([
            'title' => $data['title'],
            'category' => $data['category'] ?? null,
            'subject_type' => 'Account',
            'subject_id' => $employee->id,
            'path' => $file->store('documents/employees', 'local'),
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);
        audit('created', null, 'Employee document: '.$employee->name.' · '.$data['title']);

        return redirect()->route('hr.documents.index', ['employee' => $employee->id])->with('success', __('File saved.'));
    }

    public function show(Request $request): StreamedResponse
    {
        Gate::authorize('hr.view');

        $document = $this->employeeFiles()->findOrFail($request->route('id'));
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('hr.manage');

        $document = $this->employeeFiles()->findOrFail($request->route('id'));
        Storage::disk('local')->delete($document->path);
        $document->delete();

        return back()->with('success', __('File deleted.'));
    }

    /** Only files that belong to an employee: other files of the company stay out of reach. */
    private function employeeFiles()
    {
        return Attachment::where('subject_type', 'Account')
            ->whereIn('subject_id', Account::ofType('employee')->select('accounts.id'));
    }
}
