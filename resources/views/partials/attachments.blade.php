{{-- Files of one paper. Needs $subjectType (e.g. "Sale"), $subjectId and $attachments. --}}
<div class="card mt-4 no-print">
    <div class="card-header"><h5 class="card-title">{{ __('Files') }}</h5></div>
    <div class="card-body">
        @forelse ($attachments as $file)
            <div class="d-flex align-items-center justify-content-between gap-2 py-1">
                <a href="{{ route('documents.show', ['id' => $file->id]) }}"><x-icon name="paperclip" /> {{ $file->title }}</a>
                <span class="small text-muted">{{ number_format($file->size / 1024) }} KB</span>
            </div>
        @empty
            <p class="text-muted mb-2">{{ __('No files yet. You can keep a photo or scan of the paper here.') }}</p>
        @endforelse
        @can('documents.manage')
            <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="row g-2 align-items-end mt-1">
                @csrf
                <input type="hidden" name="subject_type" value="{{ $subjectType }}">
                <input type="hidden" name="subject_id" value="{{ $subjectId }}">
                <div class="col-12 col-md-4"><input type="text" class="form-control" name="title" placeholder="{{ __('Title') }}" required></div>
                <div class="col-12 col-md-5"><input type="file" class="form-control" name="file" required></div>
                <div class="col-12 col-md-3"><button type="submit" class="btn btn-light-secondary w-100"><x-icon name="upload" class="me-1" /> {{ __('Save file') }}</button></div>
            </form>
        @endcan
    </div>
</div>
