@php(session(['last_order'=> false]))
<div class="modal fade py-5" id="print-invoice" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('print_Invoice') }}</h5>
                <button id="invoice_close" type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body row">

                @if($order)
                    @php($orderTotalPriceSummary = \App\Utils\OrderManager::getOrderTotalPriceSummary(order: $order))
                    @if($orderTotalPriceSummary['dueAmount'] > 0)
                        <div class="col-md-12 non-printable">
                            <div class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span>{{ translate($order->payment_status) }} {{ translate('order') }} &mdash; {{ translate('customer_owes') }}</span>
                                <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['dueAmount']), currencyCode: getCurrencyCode()) }}</strong>
                            </div>
                        </div>
                    @endif
                @endif

                <div class="col-md-12">
                    <div class="text-center">
                        <input id="print_invoice" type="button" class="btn btn--primary non-printable action-print-pos-invoice"
                               data-print="#printableArea"
                               value="{{ translate('print_invoice') }} (A4)"/>
                        <a href="{{url()->previous()}}" class="btn btn-danger non-printable">
                            {{ translate('back') }}
                        </a>
                    </div>
                    <hr class="non-printable">
                </div>

                <div class="w-100 px-3" id="printableArea">
                    @include('admin-views.pos.order.invoice')
                </div>

            </div>
        </div>
    </div>
</div>
