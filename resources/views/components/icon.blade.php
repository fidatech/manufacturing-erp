@props(['name', 'set' => 'solid'])
<i {{ $attributes->class('fi') }}><svg aria-hidden="true"><use href="{{ asset_v('icons.svg') }}#{{ $set === 'regular' ? 'r' : 's' }}-{{ $name }}"/></svg></i>
