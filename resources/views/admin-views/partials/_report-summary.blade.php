<div class="card-header gap-10">
    <h4 class="d-flex align-items-center text-capitalize gap-10 mb-0">
        <img src="{{dynamicAsset(path: 'public/assets/back-end/img/order-statistics.png')}}" alt="">
        {{translate('report_summary')}}
    </h4>
</div>
<div class="card-body">
    <ul class="list-group list-group-flush">
        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <span class="text-capitalize">{{translate('total_revenue')}}</span>
            <span class="fw-bold">{{ setCurrencySymbol(amount: $data['revenue'] ?? 0, currencyCode: getCurrencyCode()) }}</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <span class="text-capitalize">{{translate('pending')}}</span>
            <span class="fw-bold">{{ $data['pending'] }}</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <span class="text-capitalize">{{translate('confirmed')}}</span>
            <span class="fw-bold">{{ $data['confirmed'] }}</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <span class="text-capitalize">{{translate('packaging')}}</span>
            <span class="fw-bold">{{ $data['processing'] }}</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <span class="text-capitalize">{{translate('out_for_delivery')}}</span>
            <span class="fw-bold">{{ $data['out_for_delivery'] }}</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <span class="text-capitalize">{{translate('delivered')}}</span>
            <span class="fw-bold">{{ $data['delivered'] }}</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <span class="text-capitalize">{{translate('canceled')}}</span>
            <span class="fw-bold">{{ $data['canceled'] }}</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <span class="text-capitalize">{{translate('returned')}}</span>
            <span class="fw-bold">{{ $data['returned'] }}</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <span class="text-capitalize">{{translate('failed_to_delivery')}}</span>
            <span class="fw-bold">{{ $data['failed'] }}</span>
        </li>
    </ul>
</div>
