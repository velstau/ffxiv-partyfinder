@props(['name', 'label', 'hint' => null])
@php $fieldIcons = ['q' => 'search', 'keywords' => 'filter', 'ng_keywords' => 'block', 'worldname' => 'map-pin', 'within_days' => 'calendar', 'blog_lang' => 'language', 'order' => 'arrows-sort', 'sort' => 'arrows-sort', 'view_mode' => 'layout-cards', 'saved-name' => 'bookmark']; @endphp
<div {{ $attributes->class(['field']) }}>
    <label class="form-label" for="{{ $name }}">@if (isset($fieldIcons[$name]))<x-partyfinder.icon :name="$fieldIcons[$name]" class="text-secondary" /> @endif{{ $label }}</label>
    {{ $slot }}
    @if ($hint)<p class="text-secondary" id="{{ $name }}-hint">{{ $hint }}</p>@endif
</div>
