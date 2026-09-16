@php
    use Illuminate\Support\Facades\Session;
    $currencyCode = getCurrencyCode(type: 'default');
    $direction = Session::get('direction');
    $totalQty = $items->sum('quantity_change');
    $totalCost = $items->sum(fn($item) => $item->quantity_change * ($item->unit_cost ?? 0));
    $purchaseDate = $items->first()->created_at;
    $admin = $items->first()->admin;
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{$direction}}"
      style="text-align: {{$direction === "rtl" ? 'right' : 'left'}};"
      xmlns="http://www.w3.org/1999/html">
<head>
    <meta charset="UTF-8">
    <title>{{ translate('purchase_invoice')}}</title>
    <meta http-equiv="Content-Type" content="text/html;"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * {
            margin: 0;
            padding: 0;
            line-height: 1.6;
            font-family: sans-serif;
            color: #6A707C;
        }

        body {
            font-size: .75rem;
        }

        img {
            max-width: 100%;
        }

        table {
            width: 100%;
        }

        table.customers {
            border-collapse: collapse;
        }

        table.customers thead th {
            background-color: #F5FBFF;
            color: #222222;
            border-top: 1px solid #D6EBFF;
            border-bottom: 1px solid #D6EBFF;
            padding: 8px;
            font-size: 11px;
        }

        table.customers tbody td {
            padding: 8px;
            border-bottom: 1px solid #D7DAE0;
            font-size: 11px;
        }

        .text-left {
            text-align: {{$direction === "rtl" ? 'right' : 'left'}}  !important;
        }

        .text-right {
            text-align: {{$direction === "rtl" ? 'left' : 'right'}}  !important;
        }

        .fz-10 { font-size: 10px; }
        .fz-11 { font-size: 11px; }
        .fz-12 { font-size: 12px; }
        .fz-17 { font-size: 17px; }

        .font-bold, strong {
            font-weight: 700;
            color: #222222;
        }

        .text-primary { color: #0177CD; }

        .mb-1 { margin-bottom: 4px !important; }
        .mb-2 { margin-bottom: 8px !important; }
        .mt-6px { margin-top: 6px; }

        .border { border: 1px solid #D7DAE0; }
        .border-bottom { border-bottom: 1px solid #D7DAE0; }
        .border-dashed-top { border-top: 1px dashed #ddd; }

        .bs-0 { border-spacing: 0; }
        .vertical-align-top { vertical-align: top; }
        .text-uppercase { text-transform: uppercase; }
        .text-capitalize { text-transform: capitalize; }
        .calc-table td { padding-inline: 0 !important; }
    </style>
</head>

<body>

<div class="first content-position" style="width:595px;margin: 0 auto; padding: 30px 20px 10px;">
    <table class="fz-10">
        <tr>
            <td style="padding:0;text-align:{{$direction === "rtl" ? 'right' : 'left'}}">
                <div style="text-transform:uppercase; font-size:22px;margin-bottom:5px; color:#222222;">
                    {{ translate('purchase_invoice')}}
                </div>
                <div>
                    <span class="font-bold">{{ translate('date')}}</span> : {{ \Carbon\Carbon::parse($purchaseDate)->format('M d, Y') }}
                </div>
            </td>
            <td style="padding:0;text-align:{{$direction === "rtl" ? 'left' : 'right'}}">
                <img height="40" src="{{getStorageImages(path: $companyWebLogo, type: 'backend-logo')}}" alt=""
                     style="margin-bottom:5px">
                <div>{{ $companyName }}</div>
                <div>{{ $companyAddress }}</div>
                <div>{{ $companyPhone }} @if($companyPhone && $companyEmail) &middot; @endif {{ $companyEmail }}</div>
            </td>
        </tr>
    </table>
    <br>
    <table class="border bs-0" style="border-radius:12px;">
        <tr>
            <td class="text-left" style="padding:16px">
                <div class="mb-1 fz-11">
                    <span class="font-bold">{{ translate('reference_no')}}</span> : {{ $reference_no }}
                </div>
                <div class="fz-11">
                    <span class="font-bold">{{ translate('created_by')}}</span> : {{ $admin?->name ?? '-' }}
                </div>
            </td>
            <td class="text-right" style="padding:16px">
                <div class="fz-11">
                    <span class="font-bold">{{translate('total')}}</span> {{' ( '.$currencyCode.' )'}}
                </div>
                <div class="fz-17 text-primary text-right">{{ webCurrencyConverter(amount: $totalCost) }}</div>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="border-bottom"></td>
        </tr>
        <tr>
            <td colspan="2" style="padding: 10px">
                <table class="customers bs-0">
                    <thead>
                    <tr>
                        <th class="text-left text-uppercase">{{ translate('product')}}</th>
                        <th class="text-left text-uppercase">{{ translate('product_SKU')}}</th>
                        <th class="text-right text-uppercase">{{ translate('qty')}}</th>
                        <th class="text-right text-uppercase">{{ translate('unit_cost')}}</th>
                        <th class="text-right text-uppercase">{{ translate('total')}}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td>{{ $item->product?->name ?? translate('deleted_product') }}</td>
                            <td>{{ $item->product?->code ?? '-' }}</td>
                            <td class="text-right">{{ $item->quantity_change }}</td>
                            <td class="text-right">{{ webCurrencyConverter(amount: $item->unit_cost ?? 0) }}</td>
                            <td class="text-right">{{ webCurrencyConverter(amount: $item->quantity_change * ($item->unit_cost ?? 0)) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding: 10px 16px 20px">
                <table class="fz-11">
                    <tr>
                        <th class="text-left" style="width:60%"></th>
                        <th class="calc-table">
                            <table>
                                <tr>
                                    <td class="text-left font-bold">{{ translate('total_quantity')}}</td>
                                    <td class="text-right">{{ $totalQty }}</td>
                                </tr>
                                <tr>
                                    <td class="border-dashed-top font-bold text-left fz-12">{{ translate('grand_total')}}</td>
                                    <td class="border-dashed-top text-right fz-12 font-bold">{{ webCurrencyConverter(amount: $totalCost) }}</td>
                                </tr>
                            </table>
                        </th>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
