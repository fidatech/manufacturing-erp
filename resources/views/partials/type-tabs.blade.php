{{-- Tabs that switch between kinds of a paper. Needs $names [type => label], $type and $routeName. --}}
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach ($names as $key => $name)
        <a class="btn btn-sm {{ $key === $type ? 'btn-primary' : 'btn-light-secondary' }}" href="{{ route($routeName, ['type' => $key]) }}">{{ $name }}</a>
    @endforeach
</div>
