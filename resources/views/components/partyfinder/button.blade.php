@props(['variant' => 'secondary', 'type' => 'button', 'icon' => null])
@php $variants = ['primary' => 'btn-primary', 'secondary' => '', 'quiet' => 'btn-ghost-secondary']; @endphp
<button type="{{ $type }}" {{ $attributes->class(['btn', $variants[$variant] ?? '']) }}>
    @if ($icon)<x-partyfinder.icon :name="$icon" />@endif
    <span>{{ $slot }}</span>
</button>
