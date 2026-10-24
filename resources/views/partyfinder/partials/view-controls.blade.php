@php use App\Http\Controllers\PartyFinderController; @endphp
<div class="d-flex flex-wrap gap-3 align-items-end">
    <x-partyfinder.field name="sort" label="結果の並び替え">
        <select class="form-select" id="sort" name="sort" form="search-form">
            @foreach (PartyFinderController::SORT_OPTIONS as $value => $label)
                <option value="{{ $value }}" @selected($conditions['sort'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-partyfinder.field>
    <x-partyfinder.field name="view_mode" label="結果の見せ方">
        <select class="form-select" id="view_mode" name="view_mode" form="search-form">
            @foreach (PartyFinderController::VIEW_OPTIONS as $value => $label)
                <option value="{{ $value }}" @selected($conditions['view_mode'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-partyfinder.field>
    @if ($entries !== null)
        <x-partyfinder.button type="submit" form="search-form">表示を適用</x-partyfinder.button>
    @endif
</div>
