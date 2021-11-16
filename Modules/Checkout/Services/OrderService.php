<?php

namespace Modules\Checkout\Services;

use Modules\Cart\CartTax;
use Modules\Cart\CartItem;
use Modules\Cart\Facades\Cart;
use Modules\Order\Entities\Order;
use Modules\Address\Entities\Address;
use Modules\FlashSale\Entities\FlashSale;
use Modules\Currency\Entities\CurrencyRate;
use Modules\Address\Entities\DefaultAddress;
use Modules\Shipping\Facades\ShippingMethod;

class OrderService
{
    public function create($request)
    {
        $this->mergeShippingAddress($request);
        $this->saveAddress($request);
        $this->addShippingMethodToCart($request);

        return tap($this->store($request), function ($order) use($request) {
            $this->storeOrderProducts($order, $request);
            $this->storeOrderDownloads($order,$request);
            $this->storeFlashSaleProductOrders($order, $request);
            $this->incrementCouponUsage($order, $request);
            $this->attachTaxes($order, $request);
            $this->reduceStock($request);
        });
    }

    private function mergeShippingAddress($request)
    {
        $request->merge([
            'shipping' => $request->ship_to_a_different_address ? $request->shipping : $request->billing,
        ]);
    }

    private function saveAddress($request)
    {
        if (auth()->guest()) {
            return;
        }

        if ($request->newBillingAddress) {
            $address = auth()->user()->addresses()->create(
                $this->extractAddress($request->billing)
            );

            $this->makeDefaultAddress($address);
        }

        if ($request->ship_to_a_different_address && $request->newShippingAddress) {
            auth()->user()->addresses()->create(
                $this->extractAddress($request->shipping)
            );
        }
    }

    private function extractAddress($data)
    {
        return [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'address_1' => $data['address_1'],
            'address_2' => $data['address_2'] ?? null,
            'city' => $data['city'],
            'state' => $data['state'],
            'zip' => $data['zip'],
            'country' => $data['country'],
        ];
    }

    private function makeDefaultAddress(Address $address)
    {
        if (auth()->user()->addresses()->count() > 1) {
            return;
        }

        DefaultAddress::create([
            'address_id' => $address->id,
            'customer_id' => auth()->id(),
        ]);
    }

    private function addShippingMethodToCart($request)
    {
        if (! Cart::allItemsAreVirtual() && ! Cart::hasShippingMethod() && !is_null($request->shipping_method)) {
            Cart::addShippingMethod(ShippingMethod::get($request->shipping_method));
        }
    }

    private function store($request)
    {
        $customer_id = is_null(auth()->id()) ? $request->user_id : auth()->id();
        return Order::create([
            'customer_id' => $customer_id,
            'customer_email' => $request->customer_email,
            'customer_phone' => $request->customer_phone,
            'customer_first_name' => (isset($request->billing)) ? $request->billing['first_name'] : "",
            'customer_last_name' => (isset($request->billing)) ? $request->billing['last_name'] : "",
            'billing_first_name' => (isset($request->billing)) ? $request->billing['first_name'] : "",
            'billing_last_name' => (isset($request->billing)) ? $request->billing['last_name'] : "",
            'billing_address_1' => (isset($request->billing)) ? $request->billing['address_1'] : "",
            'billing_address_2' => (isset($request->billing)) ? $request->billing['address_2']  : "",
            'billing_city' => (isset($request->billing)) ? $request->billing['city'] : "",
            'billing_state' => (isset($request->billing)) ? $request->billing['state'] : "",
            'billing_zip' => (isset($request->billing)) ? $request->billing['zip'] : "",
            'billing_country' => (isset($request->billing)) ? $request->billing['country'] : "",
            'shipping_first_name' => (isset($request->shipping)) ? $request->shipping['first_name'] : "",
            'shipping_last_name' => (isset($request->shipping)) ? $request->shipping['last_name'] : "",
            'shipping_address_1' => (isset($request->shipping)) ? $request->shipping['address_1'] : "",
            'shipping_address_2' => (isset($request->shipping)) ? $request->shipping['address_2'] : "",
            'shipping_city' => (isset($request->shipping)) ? $request->shipping['city'] : "",
            'shipping_state' => (isset($request->shipping)) ? $request->shipping['state'] : "",
            'shipping_zip' => (isset($request->shipping)) ? $request->shipping['zip'] : "",
            'shipping_country' => (isset($request->shipping)) ? $request->shipping['country'] : "",
            'sub_total' => Cart::subTotal()->amount(),
            'shipping_method' => Cart::shippingMethod()->name(),
            'shipping_cost' => Cart::shippingCost()->amount(),
            'coupon_id' => Cart::coupon()->id(),
            'discount' => Cart::discount()->amount(),
            'total' => Cart::total()->amount(),
            'payment_method' => $request->payment_method,
            'currency' => currency(),
            'currency_rate' => CurrencyRate::for(currency()),
            'locale' => locale(),
            'status' => Order::PENDING_PAYMENT,
            'note' => $request->order_note,
        ]);
    }

    private function storeOrderProducts(Order $order, $request)
    {
        Cart::items()->each(function (CartItem $cartItem) use ($order) {
            $order->storeProducts($cartItem);
        });
    }

    private function storeOrderDownloads(Order $order, $request)
    {
        Cart::items()->each(function (CartItem $cartItem) use ($order) {
            $order->storeDownloads($cartItem);
        });
    }

    private function storeFlashSaleProductOrders(Order $order, $request)
    {
        Cart::items()->each(function (CartItem $cartItem) use ($order) {
            if (! FlashSale::contains($cartItem->product)) {
                return;
            }

            FlashSale::pivot($cartItem->product)
                ->orders()
                ->attach([
                    $cartItem->product->id => [
                        'order_id' => $order->id,
                        'qty' => $cartItem->qty,
                    ],
                ]);
        });
    }

    private function incrementCouponUsage(Order $order, $request)
    {
        Cart::coupon()->usedOnce();
    }

    private function attachTaxes(Order $order, $request)
    {
        Cart::taxes()->each(function (CartTax $cartTax) use ($order) {
            $order->attachTax($cartTax);
        });
    }

    public function reduceStock($request)
    {
        Cart::reduceStock();
    }

    public function delete(Order $order, $request)
    {
        $order->delete();

        Cart::restoreStock();
    }
}
