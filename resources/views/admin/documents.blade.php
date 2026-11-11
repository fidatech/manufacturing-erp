@extends('layouts.app', ['title' => __('Documents')])

@section('content')
<x-page-header :title="__('Documents')" icon="folder-open" />

@can('documents.manage')
    <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="card mb-4">
        @csrf
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="f-title">{{ __('Title') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('title') is-invalid @enderror" id="f-title" name="title" value="{{ old('title') }}" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="f-category">{{ __('Document type') }}</label>
                    <select class="form-select" id="f-category" name="category">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(old('category') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="f-file">{{ __('File') }} <span class="text-danger">*</span></label>
                    <input type="file" class="form-control @error('file') is-invalid @enderror" id="f-file" name="file" required>
                    @error('file')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><x-icon name="upload" class="me-1" /> {{ __('Save file') }}</button>
                </div>
            </div>
            <div class="form-text">{{ __('PDF, picture, Word or Excel, up to 10 MB.') }}</div>
        </div>
    </form>
@endcan

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col"><input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search…') }}"></div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit"><x-icon name="magnifying-glass" /></button></div>
        </form>
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Belongs to') }}</th><th>{{ __('Added') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($documents as $document)
                    <tr>
                        <td>{{ $document->title }}<div class="small text-muted">{{ $document->original_name }} · {{ number_format($document->size / 1024) }} KB</div></td>
                        <td>{{ $document->category }}</td>
                        <td>{{ $document->subject_type ? __($document->subject_type).' #'.$document->subject_id : '' }}</td>
                        <td>{{ fmt_date($document->created_at) }}<div class="small text-muted">{{ $document->uploader?->name }}</div></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-light-secondary" href="{{ route('documents.show', ['id' => $document->id]) }}">{{ __('Open') }}</a>
                            @can('documents.manage')
                                <form method="POST" action="{{ route('documents.destroy', ['id' => $document->id]) }}" class="d-inline" data-confirm="{{ __('Delete this?') }}" data-confirm-ok="{{ __('Delete') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light-danger">{{ __('Delete') }}</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $documents->links('partials.pagination') }}
    </div>
</div>
@endsection
