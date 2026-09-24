@php
    $formatCell = function ($value, $type) use ($money) {
        if ($value === null || $value === '') {
            return '';
        }
        if ($type === 'money') {
            return $money($value);
        }
        if ($type === 'number') {
            return number_format((float)$value);
        }
        return e($value);
    };
@endphp
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: freeserif; font-size: 10pt; color: #25272B; }
        .head td { vertical-align: top; }
        .company { font-size: 16pt; font-weight: bold; }
        .muted { color: #6B7079; font-size: 9pt; }
        .title { font-size: 14pt; font-weight: bold; color: #1A73E8; text-align: right; }
        .meta td { padding: 1px 8px 1px 0; font-size: 9.5pt; }
        .summary td { background: #E8F1FC; padding: 6px 10px; border: 2px solid #fff; }
        .summary .label { color: #4A4D55; font-size: 8.5pt; }
        .summary .value { font-size: 11pt; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data th { background: #1A73E8; color: #fff; padding: 6px 5px; font-size: 9pt; text-align: left; }
        table.data td { padding: 5px; border-bottom: 1px solid #E3E6EA; font-size: 9pt; }
        table.data tr.alt td { background: #F7F9FC; }
        table.data tr.total td { font-weight: bold; border-top: 2px solid #25272B; border-bottom: none; background: #F4F5F7; }
        .num, table.data th.num { text-align: right; }
        .empty { text-align: center; color: #8A8F9A; padding: 18px; }
    </style>
</head>
<body>
<table width="100%" class="head">
    <tr>
        <td width="60%">
            <div class="company">{{ $company['name'] }}</div>
            @if($company['address'])<div class="muted">{{ $company['address'] }}</div>@endif
            <div class="muted">{{ collect([$company['phone'], $company['email']])->filter()->implode(' • ') }}</div>
        </td>
        <td width="40%">
            <div class="title">{{ $data['title'] }}</div>
            <div class="muted" style="text-align: right;">Generated {{ now()->format('d/m/Y h:i A') }}</div>
        </td>
    </tr>
</table>

<table class="meta" style="margin-top: 8px;">
    @foreach($data['meta'] as $label => $value)
        @if($value !== '' && $value !== null)
            <tr><td class="muted">{{ $label }}:</td><td><b>{{ $value }}</b></td></tr>
        @endif
    @endforeach
</table>

@if(!empty($data['summary']))
    <table class="summary" width="100%" style="margin-top: 8px;">
        <tr>
            @foreach($data['summary'] as $label => $value)
                <td>
                    <div class="label">{{ $label }}</div>
                    <div class="value">{{ $value }}</div>
                </td>
            @endforeach
        </tr>
    </table>
@endif

<table class="data">
    <thead>
    <tr>
        <th style="width: 4%;">#</th>
        @foreach($data['columns'] as [$label, $type])
            <th class="{{ $type === 'text' ? '' : 'num' }}">{{ $label }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @forelse($data['rows'] as $i => $row)
        <tr class="{{ $i % 2 ? 'alt' : '' }}">
            <td>{{ $i + 1 }}</td>
            @foreach($data['columns'] as $c => [$label, $type])
                <td class="{{ $type === 'text' ? '' : 'num' }}">{!! $formatCell($row[$c] ?? null, $type) !!}</td>
            @endforeach
        </tr>
    @empty
        <tr><td class="empty" colspan="{{ count($data['columns']) + 1 }}">No records for this period.</td></tr>
    @endforelse
    @if(!empty($data['totals']) && count($data['rows']))
        <tr class="total">
            <td></td>
            @foreach($data['columns'] as $c => [$label, $type])
                <td class="{{ $type === 'text' ? '' : 'num' }}">{!! $formatCell($data['totals'][$c] ?? null, $type) !!}</td>
            @endforeach
        </tr>
    @endif
    </tbody>
</table>
</body>
</html>
