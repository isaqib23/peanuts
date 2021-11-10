<style>
    #ticketsModal .modal-header {
        border-bottom: none;
        position: relative;
    }
    #ticketsModal .modal-header .btn {
        position: absolute;
        top: 0;
        right: 0;
        margin-top: 0;
        border-top-left-radius: 0;
        border-bottom-right-radius: 0;
    }
    #ticketsModal .modal-footer {
        border-top: none;
        padding: 0;
    }
    #ticketsModal .modal-footer .btn-group > .btn:first-child {
        border-bottom-left-radius: 0;
    }
    #ticketsModal .modal-footer .btn-group > .btn:last-child {
        border-top-right-radius: 0;
    }
</style>
<div class="order-totals-wrapper">
    <div class="row">
        <a style="margin-top: 10px; margin-left: 10px" data-toggle="modal" href="#ticketsModal" class="btn btn-primary">View Tickets</a>
        <div class="order-totals pull-right">
            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <td>{{ trans('order::orders.subtotal') }}</td>
                            <td class="text-right">{{ $order->sub_total->format() }}</td>
                        </tr>

                        @if ($order->hasShippingMethod())
                            <tr>
                                <td>{{ $order->shipping_method }}</td>
                                <td class="text-right">{{ $order->shipping_cost->format() }}</td>
                            </tr>
                        @endif

                        @foreach ($order->taxes as $tax)
                            <tr>
                                <td>{{ $tax->name }}</td>
                                <td class="text-right">{{ $tax->order_tax->amount->format() }}</td>
                            </tr>
                        @endforeach

                        @if ($order->hasCoupon())
                            <tr>
                                <td>{{ trans('order::orders.coupon') }} (<span class="coupon-code">{{ $order->coupon->code }}</span>)</td>
                                <td class="text-right">&#8211;{{ $order->discount->format() }}</td>
                            </tr>
                        @endif

                        <tr>
                            <td>{{ trans('order::orders.total') }}</td>
                            <td class="text-right">{{ $order->total->format() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="ticketsModal" class="modal fade in">
            <div class="modal-dialog">
                <div class="modal-content">

                    <div class="modal-header">
                        <a class="btn btn-default" data-dismiss="modal"><span class="glyphicon glyphicon-remove"></span></a>
                        <h4 class="modal-title">Purchased Tickets</h4>
                    </div>
                    <div class="modal-body">
                        <table class="table table-bordered table-responsive">
                            @foreach($order->tickets->chunk(3) as $ticket) @php $ticket = array_values($ticket->toArray()) @endphp
                                <tr>
                                    <td>{{(isset($ticket[0])) ? $ticket[0] : ""}}</td>
                                    <td>{{(isset($ticket[1])) ? $ticket[1] : ""}}</td>
                                    <td>{{(isset($ticket[2])) ? $ticket[2] : ""}}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div><!-- /.modal-content -->
            </div><!-- /.modal-dalog -->
        </div><!-- /.modal -->
    </div>
</div>
