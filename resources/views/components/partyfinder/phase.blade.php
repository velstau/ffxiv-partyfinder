@props(['analysis'])
@if ($analysis['phase'])
    @if (!empty($analysis['phase']['raw']) && $analysis['phase']['raw'] !== $analysis['phase']['label'])
        <details class="pf-phase-details">
            <summary>{{ $analysis['phase']['label'] }}<span class="visually-hidden">（原文を確認）</span></summary>
            <div class="pf-phase-original">{{ $analysis['phase_source'] === 'body' ? '本文' : 'タイトル' }}の表記：{{ $analysis['phase']['raw'] }}</div>
        </details>
    @else
        <span>{{ $analysis['phase']['label'] }}</span>
    @endif
    @if ($analysis['phase_source'] === 'body')
        <span class="flag body badge bg-yellow-lt" title="タイトルには書かれておらず、本文から判定したもの">本文<span class="visually-hidden">から判定</span></span>
    @endif
@else
    <span class="text-secondary">—<span class="visually-hidden">フェーズ不明</span></span>
@endif
