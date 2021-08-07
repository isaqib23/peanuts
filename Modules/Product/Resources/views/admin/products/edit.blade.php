@extends('admin::layout')

@component('admin::components.page.header')
    @slot('title', trans('admin::resource.edit', ['resource' => trans('product::products.product')]))
    @slot('subtitle', $product->name)

    <li><a href="{{ route('admin.products.index') }}">{{ trans('product::products.products') }}</a></li>
    <li class="active">{{ trans('admin::resource.edit', ['resource' => trans('product::products.product')]) }}</li>
@endcomponent

@section('content')
    <form method="POST" action="{{ route('admin.products.update', $product) }}" class="form-horizontal" id="product-edit-form" enctype="multipart/form-data" novalidate>
        {{ csrf_field() }}
        {{ method_field('put') }}

        {!! $tabs->render(compact('product')) !!}
    </form>
@endsection

@include('product::admin.products.partials.shortcuts')

@push('scripts')
<script type="text/javascript">
    <?php if($product->product_type == 0){ ?>
    $( "#basic_information ul li:nth-child(3)" ).hide();
    <?php } ?>
    $(document).find("#product_type").change(function () {
        if($(this).val() == 1){
            $( "#basic_information ul li:nth-child(3)" ).show();
        }else{
            $( "#basic_information ul li:nth-child(3)" ).hide();
        }
    })
</script>
@endpush
