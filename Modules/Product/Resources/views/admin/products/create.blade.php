@extends('admin::layout')

@component('admin::components.page.header')
    @slot('title', trans('admin::resource.create', ['resource' => trans('product::products.product')]))

    <li><a href="{{ route('admin.products.index') }}">{{ trans('product::products.products') }}</a></li>
    <li class="active">{{ trans('admin::resource.create', ['resource' => trans('product::products.product')]) }}</li>
@endcomponent

@section('content')
    <form method="POST" action="{{ route('admin.products.store') }}" class="form-horizontal" id="product-create-form" enctype="multipart/form-data" novalidate>
        {{ csrf_field() }}

        {!! $tabs->render(compact('product')) !!}
    </form>
@endsection

@include('product::admin.products.partials.shortcuts')
@push('scripts')
    <script type="text/javascript">
        $( "#basic_information ul li:nth-child(3)" ).hide();
        $(document).find("#product_type").change(function () {
            if($(this).val() == 1){
                $( "#basic_information ul li:nth-child(3)" ).show();
            }else{
                $( "#basic_information ul li:nth-child(3)" ).hide();
            }
        })
    </script>
@endpush
