@extends('layouts.back-end.app')

@section('title', translate('purchase_list'))

@section('content')
    <div class="content container-fluid">
        <div class="mb-4 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h2 class="h1 mb-1 text-capitalize d-flex gap-2">
                <img src="{{dynamicAsset(path: 'public/assets/back-end/img/inhouse-product-list.png')}}" alt="">
                {{translate('purchase_list')}}
            </h2>
            <a href="{{route('admin.products.purchase')}}" class="btn btn--primary">
                <i class="tio-add"></i> {{translate('new_purchase')}}
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="GET" action="{{route('admin.products.purchase-list')}}">
                    <div class="row align-items-end mb-3">
                        <div class="col-sm-6 col-lg-4">
                            <div class="form-group mb-0">
                                <label class="title-color">{{translate('search_by_reference_no')}}</label>
                                <input type="text" class="form-control" name="searchValue"
                                       value="{{ request('searchValue') }}"
                                       placeholder="{{translate('search_by_reference_no')}}">
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="form-group mb-0">
                                <label class="title-color">{{translate('from_date')}}</label>
                                <input type="date" class="form-control" name="from_date" value="{{ request('from_date') }}">
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="form-group mb-0">
                                <label class="title-color">{{translate('to_date')}}</label>
                                <input type="date" class="form-control" name="to_date" value="{{ request('to_date') }}">
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <button type="submit" class="btn btn--primary w-100">{{translate('filter')}}</button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>{{translate('reference_no')}}</th>
                            <th>{{translate('date')}}</th>
                            <th>{{translate('supplier')}}</th>
                            <th>{{translate('items')}}</th>
                            <th>{{translate('total_quantity')}}</th>
                            <th>{{translate('total_cost')}}</th>
                            <th>{{translate('created_by')}}</th>
                            <th class="text-center">{{translate('action')}}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($purchases as $purchase)
                            <tr>
                                <td>{{ $purchase->reference_no }}</td>
                                <td>{{ \Carbon\Carbon::parse($purchase->purchase_date)->format('d M Y, h:i A') }}</td>
                                <td>{{ $supplierNames[$purchase->supplier_id] ?? '-' }}</td>
                                <td>{{ $purchase->item_count }}</td>
                                <td>{{ $purchase->total_qty }}</td>
                                <td>{{ setCurrencySymbol(usdToDefaultCurrency(amount: $purchase->total_cost)) }}</td>
                                <td>{{ $adminNames[$purchase->admin_id] ?? '-' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('admin.products.purchase-invoice', $purchase->reference_no) }}"
                                       target="_blank" class="btn btn-outline--primary btn-sm" title="{{translate('invoice')}}">
                                        <i class="tio-receipt-outlined"></i> {{translate('invoice')}}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">{{translate('no_data_found')}}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $purchases->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
