@extends('admin::layout')
<style>
    .address-information-wrapper,.order-information-wrapper{position:relative;margin-bottom:40px}.order-information-wrapper .order-information-buttons{position:absolute;top:0;right:0}.order-information-wrapper .order-information-buttons>a{float:right;padding:6px 15px;color:#626060}.order-information-wrapper .order-information-buttons>a+.tooltip .tooltip-inner{white-space:nowrap}.order-information-wrapper .order-information-buttons>form{float:right;margin-right:5px}.order-information-wrapper .order-information-buttons>form button{padding:6px 12px;color:#626060}.order-information-wrapper .order-information-buttons>form button+.tooltip .tooltip-inner{white-space:nowrap}.order-information-wrapper .table-responsive{margin-bottom:0}.order-wrapper{background:#fff;padding:15px;border-radius:3px}.order-wrapper .order td .row{margin-right:0}.order-wrapper .table{margin:0}.order-wrapper .table>tbody>tr>td{border:none}.order-wrapper h4{font-weight:500;margin-bottom:10px}.order-wrapper .handling-information span{font-size:15px}.order-wrapper .items-ordered .table-responsive{margin-bottom:0}.order-wrapper .items-ordered .table{border-bottom:1px solid #e9e9e9}.order-wrapper .items-ordered tr:last-child{border-bottom:none}.order-wrapper .items-ordered tr>td{font-size:16px;padding-top:16px;padding-bottom:16px;border-top:1px solid #f1f1f1!important;vertical-align:middle}.order-wrapper .items-ordered tr>td:first-child{min-width:250px}.order-wrapper .items-ordered tr>td:last-child{font-weight:500}.order-wrapper .items-ordered tr a{font-size:16px;font-weight:400;color:#444;letter-spacing:.2px;-webkit-transition:.2s ease-in-out;transition:.2s ease-in-out}.order-wrapper .items-ordered tr a:hover{color:#0068e1}.order-wrapper .items-ordered tr span{font-size:14px;display:block}.order-wrapper .items-ordered tr span span{display:inline-block;color:#9a9a9a}.order-wrapper .form-group{overflow:hidden}.order-wrapper .form-group>label{display:block}.order-wrapper .section-title{border-bottom:1px solid #d2d6de;padding-bottom:8px;margin-bottom:15px}.order-wrapper .account-information .table-responsive,.order-wrapper .order .table-responsive{margin-left:-8px}.order-wrapper .account-information .table-responsive tr>td:first-child,.order-wrapper .order .table-responsive tr>td:first-child{font-family:Open Sans,sans-serif;font-weight:600;white-space:nowrap}.order-wrapper .billing-address span,.order-wrapper .shipping-address span{line-height:26px;display:block;clear:both}.order-wrapper .order-total textarea{width:90%}.order-wrapper .order-total button{margin-top:10px}.order-wrapper .order-totals{width:300px;margin:15px 15px 0 0}.order-wrapper .order-totals tbody>tr>td{font-family:Roboto,sans-serif!important;font-weight:400!important;font-size:17px;padding:5px 8px}.order-wrapper .order-totals tbody>tr:last-child>td{font-family:Roboto,sans-serif;font-weight:500!important;border-top:1px solid #e9e9e9}.order-wrapper .order-totals .coupon-code{font-family:Open Sans,sans-serif;font-weight:600}@media screen and (max-width:991px){.order-wrapper .account-information,.order-wrapper .handling-information,.order-wrapper .shipping-address{margin-top:30px}}@media screen and (max-width:767px){.order-wrapper .table>tbody>tr>td{white-space:inherit}}@media screen and (max-width:520px){.order-information-wrapper .order-information-buttons{position:relative;top:auto;right:auto;float:right}}@media screen and (max-width:400px){.order-wrapper .order-totals{width:250px}}
</style>
@component('admin::components.page.header')
    @slot('title', trans('admin::resource.show', ['resource' => trans('order::orders.order')]))

    <li><a href="{{ route('admin.orders.index') }}">{{ trans('order::orders.orders') }}</a></li>
    <li class="active">{{ trans('admin::resource.show', ['resource' => trans('order::orders.order')]) }}</li>
@endcomponent

@section('content')
    <div class="order-wrapper">
        <div class="order-information-wrapper">
            <div class="order-information-buttons">
                <a href="{{ route('admin.orders.print.show', $order) }}" class="btn btn-default" target="_blank" data-toggle="tooltip" title="{{ trans('order::orders.print') }}">
                    <i class="fa fa-print" aria-hidden="true"></i>
                </a>

                <form method="POST" action="{{ route('admin.orders.email.store', $order) }}">
                    {{ csrf_field() }}

                    <button type="submit" class="btn btn-default" data-toggle="tooltip" title="{{ trans('order::orders.send_email') }}" data-loading>
                        <i class="fa fa-envelope-o" aria-hidden="true"></i>
                    </button>
                </form>
            </div>

            <h3 class="section-title">{{ trans('order::orders.order_and_account_information') }}</h3>

            <div class="row">
                <div class="col-md-6">
                    <div class="order clearfix">
                        <h4>{{ trans('order::orders.order_information') }}</h4>

                        <div class="table-responsive">
                            <table class="table">
                                <tbody>
                                <tr>
                                    <td>{{ trans('order::orders.order_date') }}</td>
                                    <td>{{ $order->created_at->toFormattedDateString() }}</td>
                                </tr>

                                <tr>
                                    <td>{{ trans('order::orders.order_status') }}</td>
                                    <td>
                                        {{ucfirst($order->status)}}
                                    </td>
                                </tr>

                                @if ($order->shipping_method)
                                    <tr>
                                        <td>{{ trans('order::orders.shipping_method') }}</td>
                                        <td>{{ $order->shipping_method }}</td>
                                    </tr>
                                @endif

                                <tr>
                                    <td>{{ trans('order::orders.payment_method') }}</td>
                                    <td>{{ $order->payment_method }}</td>
                                </tr>

                                @if (is_multilingual())
                                    <tr>
                                        <td>{{ trans('order::orders.currency') }}</td>
                                        <td>{{ $order->currency }}</td>
                                    </tr>

                                    <tr>
                                        <td>{{ trans('order::orders.currency_rate') }}</td>
                                        <td>{{ $order->currency_rate }}</td>
                                    </tr>

                                    <tr>
                                        <td>{{ trans('order::orders.order_type') }}</td>
                                        <td>{{ $order->order_type }}</td>
                                    </tr>
                                @endif

                                @if ($order->note)
                                    <tr>
                                        <td>{{ trans('order::orders.order_note') }}</td>
                                        <td>{{ $order->note }}</td>
                                    </tr>
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="account-information">
                        <h4>{{ trans('order::orders.account_information') }}</h4>

                        <div class="table-responsive">
                            <table class="table">
                                <tbody>
                                <tr>
                                    <td>{{ trans('order::orders.customer_name') }}</td>
                                    <td>{{ $order->customer_full_name }}</td>
                                </tr>

                                <tr>
                                    <td>{{ trans('order::orders.customer_email') }}</td>
                                    <td>{{ $order->customer_email }}</td>
                                </tr>

                                <tr>
                                    <td>{{ trans('order::orders.customer_phone') }}</td>
                                    <td>{{ $order->customer_phone }}</td>
                                </tr>

                                <tr>
                                    <td>{{ trans('order::orders.customer_group') }}</td>

                                    <td>
                                        {{ is_null($order->customer_id) ? trans('order::orders.guest') : trans('order::orders.registered') }}
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('order::admin.orders.partials.address_information')
        @include('order::admin.orders.partials.items_ordered')
        @include('order::admin.orders.partials.order_totals')
    </div>
@endsection
