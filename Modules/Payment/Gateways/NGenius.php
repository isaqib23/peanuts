<?php

namespace Modules\Payment\Gateways;

use Illuminate\Http\Request;
use Modules\Order\Entities\Order;
use Modules\Payment\GatewayInterface;

class NGenius implements GatewayInterface
{
    public $label;
    public $description;
    public $apiKey;

    public function __construct()
    {
        $this->label = setting('ngenius_label');
        $this->description = setting('ngenius_description');
        $this->apiKey = env('NETWORK_API_KEY');
    }

    public function purchase(Order $order, Request $request)
    {
        $result = nGeniusAccessToken();
        $request->merge([
            "access_token"  => $result->access_token,
            "order_id"      => $order->id,
            "user_id"       => (isset($request->billing->customer_id)) ? $request->billing->customer_id : $request->input("user_id")
        ]);
        $response = nGeniusPaymentUrl($request);

        return [
            "order_reference"      => $response->reference,
            "payment_page_url"     => $response->_links->payment->href,
        ];
    }

    public function complete(Order $order)
    {
    }
}
