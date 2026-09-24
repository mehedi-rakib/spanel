<?php
$orderTotalPriceSummary = \App\Utils\OrderManager::getOrderTotalPriceSummary(order: $order);
$currency = fn($amount) => setCurrencySymbol(amount: usdToDefaultCurrency(amount: $amount), currencyCode: getCurrencyCode());
$customer = $order->customer;
$customerName = $customer ? trim(($customer['f_name'] ?? '') . ' ' . ($customer['l_name'] ?? '')) : '';
?>
{{-- A4 invoice (was a 363px thermal receipt). Styles are scoped to .a4-invoice so
     the POS page is unaffected; @page makes the browser print on A4. --}}
<style>
    .a4-invoice { width: 100%; max-width: 190mm; margin: 0 auto; color: #25272B; font-size: 13px; background: #fff; }
    .a4-invoice * { box-sizing: border-box; }
    .a4-invoice .inv-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding-bottom: 14px; border-bottom: 3px solid #25272B; }
    .a4-invoice .inv-logo { max-height: 56px; max-width: 180px; margin-bottom: 6px; }
    .a4-invoice .inv-company { font-size: 22px; font-weight: 700; margin: 0; line-height: 1.2; }
    .a4-invoice .inv-muted { color: #6B7079; font-size: 12px; line-height: 1.5; }
    .a4-invoice .inv-title { font-size: 30px; font-weight: 700; color: #E1122F; letter-spacing: 2px; text-align: right; margin: 0; line-height: 1; }
    .a4-invoice .inv-meta { text-align: right; margin-top: 8px; font-size: 12.5px; line-height: 1.6; }
    .a4-invoice .inv-parties { display: flex; gap: 16px; margin: 16px 0; }
    .a4-invoice .inv-box { flex: 1; background: #F4F5F7; border-radius: 6px; padding: 10px 14px; }
    .a4-invoice .inv-box-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #6B7079; margin-bottom: 4px; }
    .a4-invoice .inv-box-name { font-size: 16px; font-weight: 700; }
    .a4-invoice table.inv-items { width: 100%; border-collapse: collapse; }
    .a4-invoice table.inv-items th { background: #25272B; color: #fff; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; padding: 9px 8px; text-align: left; }
    .a4-invoice table.inv-items td { padding: 9px 8px; border-bottom: 1px solid #E3E6EA; vertical-align: top; }
    .a4-invoice table.inv-items tr:nth-child(even) td { background: #FAFBFC; }
    .a4-invoice .num { text-align: right; white-space: nowrap; }
    .a4-invoice .inv-variation { font-size: 11.5px; color: #6B7079; }
    .a4-invoice .inv-bottom { display: flex; justify-content: space-between; gap: 24px; margin-top: 16px; }
    .a4-invoice .inv-notes { flex: 1; font-size: 12.5px; }
    .a4-invoice table.inv-totals { width: 290px; border-collapse: collapse; }
    .a4-invoice table.inv-totals td { padding: 5px 8px; }
    .a4-invoice table.inv-totals .inv-grand td { font-size: 17px; font-weight: 700; border-top: 2px solid #25272B; border-bottom: 2px solid #25272B; }
    .a4-invoice .inv-paid { color: #07A26B; }
    .a4-invoice .inv-due { color: #E1122F; font-weight: 700; }
    .a4-invoice .inv-status { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .a4-invoice .inv-status.paid { background: #DDF5EA; color: #07A26B; }
    .a4-invoice .inv-status.partial { background: #FFEEDF; color: #E0632F; }
    .a4-invoice .inv-status.due, .a4-invoice .inv-status.unpaid { background: #FDE2E5; color: #E1122F; }
    .a4-invoice .inv-sign { display: flex; justify-content: space-between; margin-top: 60px; }
    .a4-invoice .inv-sign div { width: 200px; border-top: 1px solid #6B7079; text-align: center; padding-top: 6px; font-size: 12px; color: #6B7079; }
    .a4-invoice .inv-thanks { text-align: center; margin-top: 24px; font-size: 13px; color: #6B7079; }
    /* These print rules must beat pos-invoice.css (thermal: size auto, 15px margins,
       everything black/500) and bootstrap's print CSS (size a3, body min-width 992px).
       printThis injects this block after those stylesheets, so !important here wins. */
    @media print {
        @page { size: A4 portrait !important; margin: 12mm !important; }
        html, body { background: #fff !important; min-width: 0 !important; }
        .a4-invoice .inv-company, .a4-invoice .inv-title, .a4-invoice .inv-box-name, .a4-invoice strong,
        .a4-invoice table.inv-items th, .a4-invoice .inv-grand td, .a4-invoice .inv-due, .a4-invoice .inv-status { font-weight: 700 !important; }
        .a4-invoice .inv-title, .a4-invoice .inv-due { color: #E1122F !important; }
        .a4-invoice table.inv-items th { color: #fff !important; }
        .a4-invoice .inv-paid { color: #07A26B !important; }
        .a4-invoice .inv-muted, .a4-invoice .inv-box-label, .a4-invoice .inv-variation,
        .a4-invoice .inv-sign div, .a4-invoice .inv-thanks { color: #6B7079 !important; }
        .a4-invoice .inv-status.paid { color: #07A26B !important; }
        .a4-invoice .inv-status.partial { color: #E0632F !important; }
        .a4-invoice .inv-status.due, .a4-invoice .inv-status.unpaid { color: #E1122F !important; }
        .a4-invoice { max-width: none; font-size: 12px; }
        .a4-invoice table.inv-items th, .a4-invoice .inv-box, .a4-invoice .inv-status, .a4-invoice table.inv-items tr:nth-child(even) td {
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .a4-invoice table.inv-items tr { page-break-inside: avoid; }
    }
</style>

<div class="a4-invoice">
    <div class="inv-head">
        <div>
            @if(getWebConfig(name: 'company_web_logo'))
                <img class="inv-logo" src="{{ getStorageImages(path: getWebConfig(name: 'company_web_logo'), type: 'backend-basic') }}" alt="{{ getWebConfig(name: 'company_name') }}">
            @endif
            <p class="inv-company">{{ getWebConfig(name: 'company_name') }}</p>
            <div class="inv-muted">
                @if(getWebConfig(name: 'shop_address')){{ getWebConfig(name: 'shop_address') }}<br>@endif
                {{ translate('phone') }}: {{ getWebConfig(name: 'company_phone') }}
                @if(getWebConfig(name: 'company_email')) &nbsp;•&nbsp; {{ getWebConfig(name: 'company_email') }}@endif
            </div>
        </div>
        <div>
            <p class="inv-title">{{ translate('INVOICE') }}</p>
            <div class="inv-meta">
                <div><strong>{{ translate('invoice_No') }}:</strong> #{{ $order['id'] }}</div>
                <div><strong>{{ translate('date') }}:</strong> {{ date('d/m/Y h:i A', strtotime($order['created_at'])) }}</div>
            </div>
        </div>
    </div>

    <div class="inv-parties">
        <div class="inv-box">
            <div class="inv-box-label">{{ translate('bill_to') }}</div>
            @if($customer && $customer->id != 0)
                <div class="inv-box-name">{{ $customerName }}</div>
                @if($customer['phone'])<div>{{ $customer['phone'] }}</div>@endif
                @if($customer['email'])<div class="inv-muted">{{ $customer['email'] }}</div>@endif
                @if($customer['street_address'])<div class="inv-muted">{{ $customer['street_address'] }}</div>@endif
            @else
                <div class="inv-box-name">{{ translate('walking_customer') }}</div>
            @endif
        </div>
        <div class="inv-box">
            <div class="inv-box-label">{{ translate('payment') }}</div>
            <div><strong>{{ translate('paid_by') }}:</strong> {{ translate($order->payment_method) }}</div>
            <div style="margin-top: 4px;">
                <strong>{{ translate('status') }}:</strong>
                <span class="inv-status {{ $order->payment_status }}">{{ translate($order->payment_status) }}</span>
            </div>
        </div>
    </div>

    <table class="inv-items">
        <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th>{{ translate('item') }}</th>
            <th class="num" style="width: 9%;">{{ translate('qty') }}</th>
            <th class="num" style="width: 16%;">{{ translate('unit_price') }}</th>
            <th class="num" style="width: 14%;">{{ translate('discount') }}</th>
            <th class="num" style="width: 17%;">{{ translate('amount') }}</th>
        </tr>
        </thead>
        <tbody>
        @php($line = 0)
        @foreach($order->details as $detail)
            @if($detail->product)
                @php($line++)
                @php($amount = ($detail['price'] * $detail['qty']) - $detail['discount'])
                <tr>
                    <td>{{ $line }}</td>
                    <td>
                        {{ $detail->product['name'] }}
                        @if($detail->product->product_type == 'physical' && count(json_decode($detail['variation'], true) ?? []) > 0)
                            <div class="inv-variation">
                                @foreach(json_decode($detail['variation'], true) as $key1 => $variation)
                                    {{ translate($key1) }}: <strong>{{ $variation }}</strong>@if(!$loop->last), @endif
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="num">{{ $detail['qty'] }}</td>
                    <td class="num">{{ $currency($detail['price']) }}</td>
                    <td class="num">{{ $detail['discount'] > 0 ? $currency($detail['discount']) : '—' }}</td>
                    <td class="num">{{ $currency($amount) }}</td>
                </tr>
            @endif
        @endforeach
        </tbody>
    </table>

    <div class="inv-bottom">
        <div class="inv-notes">
            @if($order->order_note)
                <div class="inv-box-label">{{ translate('note') }}</div>
                <div>{{ $order->order_note }}</div>
            @endif
        </div>
        <table class="inv-totals">
            <tr><td>{{ translate('items_Price') }}</td><td class="num">{{ $currency($orderTotalPriceSummary['itemPrice']) }}</td></tr>
            @if($orderTotalPriceSummary['itemDiscount'] > 0)
                <tr><td>{{ translate('item_discount') }}</td><td class="num">- {{ $currency($orderTotalPriceSummary['itemDiscount']) }}</td></tr>
            @endif
            @if($orderTotalPriceSummary['extraDiscount'] > 0)
                <tr><td>{{ translate('extra_discount') }}</td><td class="num">- {{ $currency($orderTotalPriceSummary['extraDiscount']) }}</td></tr>
            @endif
            <tr><td>{{ translate('subtotal') }}</td><td class="num">{{ $currency($orderTotalPriceSummary['subTotal']) }}</td></tr>
            @if($orderTotalPriceSummary['taxTotal'] > 0)
                <tr><td>{{ translate('tax') }} / {{ translate('VAT') }}</td><td class="num">{{ $currency($orderTotalPriceSummary['taxTotal']) }}</td></tr>
            @endif
            @if($orderTotalPriceSummary['couponDiscount'] > 0)
                <tr><td>{{ translate('coupon_discount') }}</td><td class="num">- {{ $currency($orderTotalPriceSummary['couponDiscount']) }}</td></tr>
            @endif
            <tr class="inv-grand"><td>{{ translate('total') }}</td><td class="num">{{ $currency($orderTotalPriceSummary['totalAmount']) }}</td></tr>
            @if ($order->order_type == 'pos' || $order->order_type == 'POS')
                <tr><td>{{ translate('Paid_Amount') }}</td><td class="num inv-paid">{{ $currency($orderTotalPriceSummary['paidAmount']) }}</td></tr>
                @if($orderTotalPriceSummary['dueAmount'] > 0)
                    <tr><td class="inv-due">{{ translate('Due_Amount') }}</td><td class="num inv-due">{{ $currency($orderTotalPriceSummary['dueAmount']) }}</td></tr>
                @elseif($orderTotalPriceSummary['changeAmount'] > 0)
                    <tr><td>{{ translate('Change_Amount') }}</td><td class="num">{{ $currency($orderTotalPriceSummary['changeAmount']) }}</td></tr>
                @endif
            @endif
        </table>
    </div>

    <div class="inv-sign">
        <div>{{ translate('customer_signature') }}</div>
        <div>{{ translate('authorized_signature') }}</div>
    </div>

    <div class="inv-thanks">{{ translate('thank_you_for_your_business') }}</div>
</div>
