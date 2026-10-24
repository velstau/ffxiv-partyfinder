<p class="text-secondary mb-2">表は横にスクロールできます</p>
<div class="table-responsive pf-table-scroll card" tabindex="0" role="region" aria-label="{{ $group['label'] }}の表">
    <table class="recruits table table-vcenter card-table mb-0">
        <caption class="visually-hidden">{{ $group['label'] }}の募集一覧</caption>
        <thead>
            <tr>
                <th scope="col"><x-partyfinder.icon name="flag" />フェーズ</th>
                <th scope="col"><x-partyfinder.icon name="users" />募集ロール</th>
                <th scope="col"><x-partyfinder.icon name="users" />枠</th>
                <th scope="col"><x-partyfinder.icon name="list-details" />タイトル</th>
                <th scope="col"><x-partyfinder.icon name="user-circle" />投稿者 / ワールド</th>
                <th scope="col"><x-partyfinder.icon name="clock" />投稿日時</th>
                <th class="act" scope="col"><x-partyfinder.icon name="sliders" />操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($group['entries'] as $entry)
                @php $a = $entry['analysis']; @endphp
                <tr>
                    <td class="phase"><x-partyfinder.phase :analysis="$a" /></td>
                    <td class="roles"><x-partyfinder.roles :analysis="$a" /></td>

                    <td class="slots">
                        @if ($a['open_slots'])
                            {{ '@' . $a['open_slots'] }}
                        @else
                            <span class="text-secondary">—</span>
                        @endif
                    </td>

                    <td class="title">
                        @if ($a['is_fixed'])
                            <span class="flag fixed badge bg-green-lt">固定</span>
                        @endif
                        @if ($a['style'] !== '攻略')
                            <span class="flag badge bg-secondary-lt">{{ $a['style'] }}</span>
                        @endif
                        <a href="{{ $entry['url'] }}" target="_blank" rel="noopener">{{ $entry['title'] }}<x-partyfinder.icon name="external" /><span class="visually-hidden">（募集日記を新しいタブで開く）</span></a>
                    </td>

                    <td class="who">
                        @if ($entry['chara_url'])
                            <a href="{{ $entry['chara_url'] }}" target="_blank" rel="noopener">{{ $entry['chara_name'] }}</a>
                        @else
                            {{ $entry['chara_name'] }}
                        @endif
                        <span class="text-secondary">／ {{ $entry['chara_world'] }}</span>
                    </td>

                    <td class="when">
                        @if ($entry['posted_at'])
                            {{ \Carbon\Carbon::createFromTimestamp($entry['posted_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                        @else
                            <span class="text-secondary">—</span>
                        @endif
                    </td>

                    <td class="act">
                        <x-partyfinder.hide-button :entry="$entry" />
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
