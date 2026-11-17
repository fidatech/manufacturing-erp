@extends('layouts.app', ['title' => __('Employee Documents')])

@section('content')
<x-page-header :title="__('Employee Documents')" icon="folder-open" :subtitle="__('Tazkira, contract, certificates and other papers of each employee.')" />

@can('hr.manage')
    @if ($employees->isEmpty())
        <div class="alert alert-info">{{ __('Add employees first.') }}</div>
    @else
        <form method="POST" action="{{ route('hr.documents.store') }}" enctype="multipart/form-data" class="card mb-4 no-print">
            @csrf
            <div class="card-header"><h5 class="card-title">{{ __('Add a file') }}</h5></div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="f-employee">{{ __('Employee') }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="f-employee" name="employee_id" required data-search>
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) old('employee_id', $filters['employee'] ?? '') === (string) $employee->id)>{{ $employee->name }}{{ $employee->metaValue('job_title') ? ' · '.$employee->metaValue('job_title') : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="f-title">{{ __('Title') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="f-title" name="title" value="{{ old('title') }}" maxlength="255" required placeholder="{{ __('e.g. Tazkira copy') }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label" for="f-category">{{ __('Document Type') }}</label>
                        <select class="form-select" id="f-category" name="category" {{ quick_add('documents', __('New document type'), 'lookups.create', ['type' => 'document_type']) }}>
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected(old('category') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="f-file">{{ __('File') }} <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="f-file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
                    </div>
                </div>
                <div class="form-text">{{ __('PDF, picture, Word or Excel, up to 10 MB.') }}</div>
            </div>
            <div class="card-footer d-flex justify-content-end"><button type="submit" class="btn btn-primary"><x-icon name="upload" class="me-1" /> {{ __('Save file') }}</button></div>
        </form>
    @endif
@endcan

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3 no-print">
            <div class="col-12 col-md">
                <input type="search" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search…') }}" aria-label="{{ __('Search') }}">
            </div>
            <div class="col col-md-auto">
                <select class="form-select" name="employee" aria-label="{{ __('Employee') }}">
                    <option value="">{{ __('All employees') }}</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) ($filters['employee'] ?? '') === (string) $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit"><x-icon name="magnifying-glass" /></button></div>
        </form>

        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Title') }}</th><th>{{ __('Document Type') }}</th><th>{{ __('File') }}</th><th>{{ __('Date') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($documents as $document)
                    <tr>
                        <td>{{ $names[$document->subject_id] ?? '' }}</td>
                        <td>{{ $document->title }}</td>
                        <td>{{ $document->category }}</td>
                        <td class="small text-muted">{{ $document->original_name }} · {{ number_format($document->size / 1024) }} KB</td>
                        <td class="text-nowrap">{{ fmt_date($document->created_at->toDateString()) }}</td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-light-secondary" href="{{ route('hr.documents.show', ['id' => $document->id]) }}"><x-icon name="download" /> {{ __('Download') }}</a>
                            @can('hr.manage')
                                <form method="POST" action="{{ route('hr.documents.destroy', ['id' => $document->id]) }}" class="d-inline" data-confirm="{{ __('Delete this file?') }}" data-confirm-ok="{{ __('Delete') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light-danger">{{ __('Delete') }}</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $documents->links('partials.pagination') }}
    </div>
</div>
@endsection
