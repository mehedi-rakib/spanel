<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: freeserif; font-size: 10pt; color: #25272B; }
        .company { font-size: 18pt; font-weight: bold; }
        .muted { color: #6B7079; font-size: 9pt; }
        .title { font-size: 20pt; font-weight: bold; color: #E1122F; text-align: right; }
        .box { background: #F4F5F7; padding: 8px 10px; }
        .box td { font-size: 9.5pt; padding: 1px 0; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.items th { background: #25272B; color: #fff; padding: 7px 6px; font-size: 9pt; text-align: left; }
        table.items td { padding: 7px 6px; border-bottom: 1px solid #E3E6EA; }
        .num, table.items th.num { text-align: right; }
        table.totals td { padding: 4px 6px; }
        table.totals .grand td { font-size: 12pt; font-weight: bold; border-top: 2px solid #25272B; }
        .status { font-weight: bold; padding: 3px 8px; }
        .paid { color: #07A26B; }
        .due { color: #E1122F; }
        .partial { color: #E0632F; }
    </style>
</head>
<body>
<table width="100%">
    <tr>
        <td width="60%" style="vertical-align: top;">
            <div class="company">{{ $company['name'] }}</div>
            @if($company['address'])<div class="muted">{{ $company['address'] }}</div>@endif
            <div class="muted">{{ collect([$company['phone'], $company['email']])->filter()->implode(' • ') }}</div>
        </td>
        <td width="40%" style="vertical-align: top;">
            <div class="title">INVOICE</div>
            <div class="muted" style="text-align: right;">{{ $data['meta']['Invoice No.'] }} • {{ $data['meta']['Date'] }}</div>
        </td>
    </tr>
</table>

<table width="100%" style="margin-top: 12px;">
    <tr>
        <td width="55%" class="box" style="vertical-align: top;">
            <div class="muted">Bill To</div>
            <div style="font-size: 12pt; font-weight: bold;">{{ $data['meta']['Customer'] }}</div>
            @if($data['meta']['Phone'])<div>{{ $data['meta']['Phone'] }}</div>@endif
        </td>
        <td width="5%"></td>
        <td width="40%" class="box" style="vertical-align: top;">
            <table width="100%">
                <tr><td class="muted">Payment</td><td class="num">{{ $data['meta']['Payment'] }}</td></tr>
                <tr><td class="muted">Status</td><td class="num"><span class="status {{ $data['status'] }}">{{ strtoupper($data['status']) }}</span></td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
    <tr>
        <th style="width: 5%;">#</th>
        <th>Item</th>
        <th class="num" style="width: 10%;">Qty</th>
        <th class="num" style="width: 20%;">Price</th>
        <th class="num" style="width: 22%;">Amount</th>
    </tr>
    </thead>
    <tbody>
    @foreach($data['rows'] as $i => [$name, $qty, $price, $amount])
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $name }}</td>
            <td class="num">{{ $qty }}</td>
            <td class="num">{{ $money($price) }}</td>
            <td class="num">{{ $money($amount) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table width="100%" style="margin-top: 10px;">
    <tr>
        <td width="55%" style="vertical-align: top;">
            @if($data['note'])
                <div class="muted">Note</div>
                <div>{{ $data['note'] }}</div>
            @endif
        </td>
        <td width="45%">
            <table class="totals" width="100%">
                <tr class="grand"><td>Total</td><td class="num">{{ $money($data['payment']['Total']) }}</td></tr>
                <tr><td class="muted">Received</td><td class="num paid">{{ $money($data['payment']['Paid']) }}</td></tr>
                @if($data['payment']['Due'] > 0)
                    <tr><td class="muted">Balance Due</td><td class="num due"><b>{{ $money($data['payment']['Due']) }}</b></td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<p class="muted" style="text-align: center; margin-top: 30px;">Thank you for your business!</p>
</body>
</html>
