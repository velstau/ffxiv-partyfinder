@props(['analysis'])
@php $roleClass = ['タンク' => 'bg-blue-lt', 'ヒーラー' => 'bg-green-lt', 'DPS' => 'bg-red-lt', 'ジョブ' => 'bg-secondary-lt']; @endphp
@forelse ($analysis['roles'] as $role)
    <span class="chip badge {{ $roleClass[$role['group']] ?? 'bg-secondary-lt' }}">{{ $role['label'] }}</span>
@empty
    @if (empty($analysis['excluded_roles']))<span class="text-secondary">—<span class="visually-hidden">募集ロール不明</span></span>@endif
@endforelse
@foreach ($analysis['excluded_roles'] as $role)
    <span class="chip badge bg-secondary-lt text-decoration-line-through"><span class="visually-hidden">募集外：</span>{{ $role['label'] }}</span>
@endforeach
