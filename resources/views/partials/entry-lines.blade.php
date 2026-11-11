{{-- The accounts of one journal entry, in plain words: who got and who gave. --}}
@foreach ($entry->lines as $line)
    <div class="small {{ $entry->isVoid() ? 'text-decoration-line-through text-muted' : '' }}">
        <span class="text-muted">{{ \App\Support\Num::isPositive($line->debit) || \App\Support\Num::isPositive($line->base_debit) ? __('To') : __('From') }}</span>
        {{ $line->account->name }}:
        <strong class="text-nowrap">{{ money(\App\Support\Num::isPositive($line->debit) ? $line->debit : $line->credit, $line->account->currency->code) }}</strong>
    </div>
@endforeach
