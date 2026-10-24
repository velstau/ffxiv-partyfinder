@if (($matrixSummary['count'] ?? 0) > 0)
    @php $sum = $matrixSummary; @endphp
    <details class="matrix card mb-3" @if ($matrixOpen) open @endif>
        <summary class="p-3"><x-partyfinder.icon name="users" />フェーズ×募集枠の件数 <span class="text-secondary">／ {{ $matrixLabel }}</span></summary><div class="card-body pt-0">
        <p class="text-secondary mb-2">表は横にスクロールできます</p>
        <div class="table-responsive pf-table-scroll" tabindex="0" role="region" aria-label="{{ $matrixLabel }}の表">
            <table class="matrix-table table table-vcenter mb-0">
                <caption class="visually-hidden">{{ $matrixLabel }}のフェーズ別募集枠</caption>
                <thead>
                    <tr>
                        <th class="ph" rowspan="2" scope="col">フェーズ</th>
                        @foreach ($sum['groups'] as $g)
                            <th colspan="{{ count($g['slots']) + 1 }}" class="grp bg-secondary-lt" scope="colgroup">{{ $g['label'] }}</th>
                        @endforeach
                        <th rowspan="2">{{ $sum['unknown']['short'] }}</th>
                        <th rowspan="2" class="fw-bold">件数</th>
                    </tr>
                    <tr>
                        @foreach ($sum['groups'] as $g)
                            <th class="grp bg-secondary-lt">計</th>
                            @foreach ($g['slots'] as $slot)
                                <th scope="col">{{ $slot['short'] }}</th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sum['phases'] as $row)
                        <tr>
                            <th class="ph" scope="row">{{ $row['label'] }}</th>
                            @foreach ($sum['groups'] as $g)
                                <td class="grp {{ $row['counts'][$g['key']] === 0 ? 'zero' : '' }}">{{ $row['counts'][$g['key']] }}</td>
                                @foreach ($g['slots'] as $slot)
                                    <td class="{{ $row['counts'][$slot['key']] === 0 ? 'zero' : '' }}">{{ $row['counts'][$slot['key']] }}</td>
                                @endforeach
                            @endforeach
                            <td class="{{ $row['counts'][$sum['unknown']['key']] === 0 ? 'zero' : '' }}">{{ $row['counts'][$sum['unknown']['key']] }}</td>
                            <td class="fw-bold">{{ $row['total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th class="ph">合計</th>
                        @foreach ($sum['groups'] as $g)
                            <td class="grp bg-secondary-lt">{{ $sum['totals'][$g['key']] }}</td>
                            @foreach ($g['slots'] as $slot)
                                <td>{{ $sum['totals'][$slot['key']] }}</td>
                            @endforeach
                        @endforeach
                        <td>{{ $sum['totals'][$sum['unknown']['key']] }}</td>
                        <td class="fw-bold">{{ $sum['count'] }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="text-secondary mt-3">
            「タンク」「DPS」の列は MT＋ST の足し算ではなく、どちらかを募集していれば1件。
            「DPS募集」「なんでも」のような総称は該当する枠すべてに数えるので、枠ごとの数字の合計は件数と一致しない。
        </p>
        @if (!empty($sum['jobs']))
            <p class="text-secondary mt-2">
                ジョブ指定：
                @foreach ($sum['jobs'] as $job => $count)
                    <span class="chip badge bg-secondary-lt">{{ $job }} {{ $count }}</span>
                @endforeach
            </p>
        @endif
    </div></details>
@endif
