@props(['entry'])
@if ($entry['blog_id'])
    <button type="button" class="hide-btn btn btn-outline-secondary text-nowrap" hidden
            data-hide-id="{{ $entry['blog_id'] }}" data-hide-title="{{ $entry['title'] }}"
            data-hide-chara="{{ $entry['chara_name'] }}／{{ $entry['chara_world'] }}" data-hide-url="{{ $entry['url'] }}"
            aria-label="{{ $entry['title'] }} を除外" title="この募集を今後の検索結果から外す">
        <x-partyfinder.icon name="block" /><span>除外</span>
    </button>
@endif
