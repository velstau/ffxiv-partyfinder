@props(['name', 'value', 'checked' => false, 'tone' => null])
@php $icons = ['tank' => 'shield', 'heal' => 'heart', 'dps' => 'sword']; $colors = ['tank' => 'blue', 'heal' => 'green', 'dps' => 'red']; @endphp
<label {{ $attributes->class(['form-selectgroup-item']) }}>
    <input class="form-selectgroup-input" type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($checked)>
    <span class="form-selectgroup-label">@if ($tone)<x-partyfinder.icon :name="$icons[$tone] ?? 'users'" class="text-{{ $colors[$tone] ?? 'secondary' }}" /> @endif{{ $slot }}</span>
</label>
