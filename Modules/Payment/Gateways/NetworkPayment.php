<?php

namespace Modules\Payment\Gateways;

use Stripe\PaymentIntent;
use Illuminate\Http\Request;
use Modules\Order\Entities\Order;
use Modules\Payment\GatewayInterface;
use Modules\Payment\Responses\StripeResponse;

class NetworkPayment implements GatewayInterface
{
    public $label;
    public $description;
    public $merchantKey;
    public $secretKey;

    public function __construct()
    {
        $this->label = setting('foloosi_label');
        $this->description = setting('foloosi_description');
        $this->merchantKey = setting('foloosi_merchant_key');
        $this->secretKey = setting('foloosi_secret_key');
    }

    public function purchase(Order $order, Request $request)
    {
        /*$intent = PaymentIntent::create([
            'amount' => $order->total->subunit(),
            'currency' => setting('default_currency'),
        ]);

        return new StripeResponse($order, $intent);*/
    }

    public function complete(Order $order)
    {
        //return new StripeResponse($order, new PaymentIntent(request('paymentIntent')));
    }
}
