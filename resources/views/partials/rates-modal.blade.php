{{-- Pop-up of the top bar: today's exchange rate of every currency, saved in one go. --}}
@php
    $rateRows = \App\Http\Controllers\Setup\ExchangeRateController::latest();
    $rateBase = \App\Services\Rates::base()->code;
@endphp
<div class="modal fade erp-quick" id="ratesModal" tabindex="-1" aria-label="{{ __('Today\'s exchange rates') }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('rates.daily') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><x-icon name="coins" class="me-1" /> {{ __('Today\'s exchange rates') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.actions.cancel') }}"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">{{ __('Write how many of each currency equal 1 :base today. Leave a box empty to keep its last rate.', ['base' => $rateBase]) }}</p>
                <div class="mb-3">
                    <label class="form-label" for="rates-date">{{ __('Date') }}</label>
                    <x-date name="date" :value="today_iso()" id="rates-date" required />
                </div>
                @forelse ($rateRows as $row)
                    <div class="mb-3">
                        <label class="form-label" for="rate-{{ $row['currency']->id }}">{{ $row['currency']->code }} · {{ __($row['currency']->name) }}</label>
                        <div class="input-group" dir="ltr">
                            <span class="input-group-text">{{ __('1 :base =', ['base' => $rateBase]) }}</span>
                            <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" id="rate-{{ $row['currency']->id }}" name="rates[{{ $row['currency']->id }}]"
                                   placeholder="{{ $row['rate'] !== null ? \App\Support\Num::format($row['rate'], 6, true) : '' }}">
                            <span class="input-group-text">{{ $row['currency']->code }}</span>
                        </div>
                        <div class="form-text">
                            @if ($row['rate'] !== null)
                                {{ __('last: :rate on :date', ['rate' => \App\Support\Num::format($row['rate'], 6, true), 'date' => fmt_date($row['date'])]) }}
                            @else
                                {{ __('no rate yet') }}
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ __('Nothing here yet') }}</p>
                @endforelse
                <div class="d-flex gap-2 justify-content-between mt-4">
                    <a class="btn btn-light-secondary" href="{{ route('rates.index') }}">{{ __('All rates') }}</a>
                    <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save rates') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>
