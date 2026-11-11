<tr data-row>
    <td>
        <input type="text" class="form-control" name="lines[{{ $i }}][parameter]" value="{{ $line['parameter'] ?? '' }}" list="qc-parameters" aria-label="{{ __('What was checked') }}" placeholder="{{ __('Brix, pH, colour, taste…') }}">
    </td>
    <td><input type="text" class="form-control" name="lines[{{ $i }}][standard]" value="{{ $line['standard'] ?? '' }}" aria-label="{{ __('Should be') }}"></td>
    <td><input type="text" class="form-control" name="lines[{{ $i }}][result]" value="{{ $line['result'] ?? '' }}" aria-label="{{ __('Found') }}"></td>
    <td class="text-center"><input type="hidden" name="lines[{{ $i }}][ok]" value="0"><input class="form-check-input" type="checkbox" name="lines[{{ $i }}][ok]" value="1" aria-label="{{ __('Good') }}" @checked($line['ok'] ?? true)></td>
    <td class="text-end"><button type="button" class="btn btn-sm btn-light-danger" data-remove-row aria-label="{{ __('Remove') }}"><x-icon name="xmark" /></button></td>
</tr>
