@extends('layouts.back-end.app')

@section('title', translate('stock_History'))

@section('content')
    <div class="content container-fluid">
        <div class="mb-4">
            <h2 class="h1 mb-1 text-capitalize d-flex gap-2">
                <img src="{{dynamicAsset(path: 'public/assets/back-end/img/inhouse-product-list.png')}}" alt="">
                {{translate('stock_History')}}
            </h2>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="GET" action="{{route('admin.stock-history.list')}}">
                    <div class="row align-items-end mb-3">
                        <div class="col-sm-6 col-lg-3">
                            <div class="form-group mb-0">
                                <label class="title-color">{{translate('search_by_product_name_or_SKU')}}</label>
                                <input type="text" class="form-control" name="searchValue"
                                       value="{{ request('searchValue') }}"
                                       placeholder="{{translate('search_by_product_name_or_SKU')}}">
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="form-group mb-0">
                                <label class="title-color">{{translate('type')}}</label>
                                <select class="js-select2-custom form-control" name="type">
                                    <option value="">{{translate('all')}}</option>
                                    @foreach(['purchase', 'adjustment', 'bulk_edit', 'bulk_import', 'initial_stock', 'order', 'order_cancel', 'return'] as $type)
                                        <option value="{{ $type }}" {{ request('type') == $type ? 'selected' : '' }}>{{ translate($type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <div class="form-group mb-0">
                                <label class="title-color">{{translate('from_date')}}</label>
                                <input type="date" class="form-control" name="from_date" value="{{ request('from_date') }}">
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-2">
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
                            <th>{{translate('date')}}</th>
                            <th>{{translate('product')}}</th>
                            <th>{{translate('type')}}</th>
                            <th>{{translate('quantity_change')}}</th>
                            <th>{{translate('previous_stock')}}</th>
                            <th>{{translate('new_stock')}}</th>
                            <th>{{translate('unit_cost')}}</th>
                            <th>{{translate('reference_no')}}</th>
                            <th>{{translate('note')}}</th>
                            <th>{{translate('by')}}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($stockHistories as $history)
                            <tr>
                                <td>{{ $history->created_at->format('d M Y, h:i A') }}</td>
                                <td>
                                    @if($history->product)
                                        {{ $history->product->name }}
                                        <br><span class="text-muted">{{ $history->product->code }}</span>
                                    @else
                                        {{ translate('deleted_product') }} (#{{ $history->product_id }})
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-soft-info text-capitalize">{{ translate($history->type) }}</span>
                                </td>
                                <td class="{{ $history->quantity_change >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $history->quantity_change >= 0 ? '+' : '' }}{{ $history->quantity_change }}
                                </td>
                                <td>{{ $history->previous_stock }}</td>
                                <td>{{ $history->new_stock }}</td>
                                <td>{{ $history->unit_cost ?? '-' }}</td>
                                <td>{{ $history->reference_no ?? '-' }}</td>
                                <td>{{ $history->note ?? '-' }}</td>
                                <td>{{ $history->admin?->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center">{{translate('no_data_found')}}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $stockHistories->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
