@extends('layouts.back-end.app')

@section('title', translate('product_Bulk_Edit'))

@section('content')
    <div class="content container-fluid">

        <div class="mb-4">
            <h2 class="h1 mb-1 text-capitalize d-flex gap-2">
                <img src="{{dynamicAsset(path: 'public/assets/back-end/img/bulk-import.png')}}" alt="">
                {{translate('bulk_Edit')}}
            </h2>
        </div>

        <div class="row text-start">
            <div class="col-12">
                <div class="card card-body">
                    <h1 class="display-5">{{translate('instructions')}} : </h1>
                    <p>{{ translate('1') }}. {{translate('select_the_fields_you_want_to_bulk_edit.')}} {{translate('description,_meta_title_and_meta_description_have_a_separate_column_for_each_language.')}}</p>
                    <p>{{ translate('2') }}. {{translate('optionally_narrow_down_which_products_to_include_using_the_filters_below,_then_download_the_sheet.')}}</p>
                    <p>{{ translate('3') }}. {{translate('the_sheet_will_contain_every_matching_product_with_its_id,_SKU_and_name_for_reference,_plus_the_fields_you_selected.')}}</p>
                    <p>{{ translate('4') }}. {{translate('edit_the_values_in_the_downloaded_sheet.')}} {{translate('do_not_change_the_id_column_or_add/remove_columns.')}}</p>
                    <p>{{ translate('5') }}. {{translate('leave_a_cell_empty_to_keep_that_products_current_value_unchanged.')}}</p>
                    <p>{{ translate('6') }}. {{translate('upload_the_edited_sheet_below_to_apply_the_changes.')}}</p>
                </div>
            </div>

            <div class="col-md-12 mt-2">
                <form class="product-form" action="{{route('admin.products.bulk-edit-export')}}" method="POST">
                    @csrf
                    <div class="card rest-part">
                        <div class="px-3 py-4">
                            <h4 class="mb-0">{{translate('step')}} 1 : {{translate('choose_fields_to_edit')}}</h4>
                        </div>
                        <div class="card-body">
                            <div class="d-flex gap-4 flex-wrap mb-3">
                                <div class="form-group d-flex gap-2 mb-0">
                                    <input type="checkbox" id="bulk-edit-select-all" class="cursor-pointer">
                                    <label class="title-color mb-0 cursor-pointer text-capitalize" for="bulk-edit-select-all">{{translate('select_all')}}</label>
                                </div>
                            </div>
                            <div class="row">
                                @foreach($fields as $key => $label)
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="form-group d-flex gap-2">
                                            <input type="checkbox" name="fields[]" value="{{ $key }}"
                                                   class="bulk-edit-field-checkbox" id="bulk-edit-field-{{ $key }}">
                                            <label class="title-color mb-0 text-capitalize" for="bulk-edit-field-{{ $key }}">
                                                {{translate($label)}}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <hr>
                            <h5 class="mb-3">{{translate('translatable_fields')}}</h5>
                            <div class="row">
                                @foreach($translatableFields as $field => $meta)
                                    @foreach($languages as $lang)
                                        <div class="col-sm-6 col-lg-3">
                                            <div class="form-group d-flex gap-2">
                                                <input type="checkbox" name="fields[]" value="{{ $field }}_{{ $lang }}"
                                                       class="bulk-edit-field-checkbox" id="bulk-edit-field-{{ $field }}_{{ $lang }}">
                                                <label class="title-color mb-0 text-capitalize" for="bulk-edit-field-{{ $field }}_{{ $lang }}">
                                                    {{translate($meta['label'])}} ({{ strtoupper($lang) }})
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                @endforeach
                            </div>

                            <hr>
                            <h5 class="mb-3">{{translate('filter_products_to_include')}}</h5>
                            <div class="row">
                                <div class="col-sm-6 col-lg-3">
                                    <div class="form-group">
                                        <label class="title-color">{{translate('category')}}</label>
                                        <select class="js-select2-custom form-control" name="category_id">
                                            <option value="">{{translate('all_categories')}}</option>
                                            @foreach($categories as $category)
                                                <option value="{{ $category['id'] }}">{{ $category['defaultName'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <div class="form-group">
                                        <label class="title-color">{{translate('brand')}}</label>
                                        <select class="js-select2-custom form-control" name="brand_id">
                                            <option value="">{{translate('all_brands')}}</option>
                                            @foreach($brands as $brand)
                                                <option value="{{ $brand['id'] }}">{{ $brand['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <div class="form-group">
                                        <label class="title-color">{{translate('status')}}</label>
                                        <select class="js-select2-custom form-control" name="status">
                                            <option value="">{{translate('all')}}</option>
                                            <option value="1">{{translate('active')}}</option>
                                            <option value="0">{{translate('inactive')}}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <div class="form-group">
                                        <label class="title-color">{{translate('search_by_name_or_SKU')}}</label>
                                        <input type="text" class="form-control" name="searchValue"
                                               placeholder="{{translate('search_by_name_or_SKU')}}">
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-10 align-items-center justify-content-end">
                                <button type="submit" class="btn btn--primary px-4">{{translate('download_sheet')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-md-12 mt-4">
                <form class="product-form" action="{{route('admin.products.bulk-edit')}}" method="POST"
                      enctype="multipart/form-data">
                    @csrf
                    <div class="card rest-part">
                        <div class="px-3 py-4">
                            <h4 class="mb-0">{{translate('step')}} 2 : {{translate('upload_the_edited_sheet')}}</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <div class="row justify-content-center">
                                    <div class="col-auto">
                                        <div class="uploadDnD">
                                            <div class="form-group inputDnD input_image input_image_edit" data-title="{{translate('drag_&_drop_file_or_browse_file')}}">
                                                <input type="file" name="products_file" accept=".xlsx, .xls" class="form-control-file text--primary font-weight-bold action-upload-section-dot-area" id="inputFile">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-10 align-items-center justify-content-end">
                                <button type="reset" class="btn btn-secondary px-4 action-onclick-reload-page">{{translate('reset')}}</button>
                                <button type="submit" class="btn btn--primary px-4">{{translate('submit')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        "use strict";
        $('#bulk-edit-select-all').on('change', function () {
            $('.bulk-edit-field-checkbox').prop('checked', $(this).is(':checked'));
        });
        $('.bulk-edit-field-checkbox').on('change', function () {
            let total = $('.bulk-edit-field-checkbox').length;
            let checked = $('.bulk-edit-field-checkbox:checked').length;
            $('#bulk-edit-select-all').prop('checked', total === checked);
        });
    </script>
@endpush
