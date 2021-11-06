<?php

use Modules\Address\Entities\Address;
use Modules\Cart\Facades\Cart;
use Modules\Product\Entities\Product;
use Modules\User\Entities\User;

function updateLotteryProduct($product, $data, $method){
    $currentPrice = $data["price"];
    if($data["product_type"] == 1) {
        $currentPrice = $data["current_price"];
        $data["product_id"] = $product->id;

        $lottery = (new \Modules\Product\Entities\ProductLottery())->where("product_id",$product->id)->first();

        if ($lottery) {
            (new \Modules\Product\Entities\ProductLottery())->where("product_id",$product->id)->update([
                'min_ticket'        => $data["min_ticket"],
                'max_ticket'        => $data["max_ticket"],
                'max_ticket_user'   => $data["max_ticket_user"],
                'winner'            => $data["winner"],
                'initial_price'     => $data["initial_price"],
                'bottom_price'      => $data["bottom_price"],
                'reduce_price'      => $data["reduce_price"],
                'current_price'     => $data["current_price"],
                'link_product'      => $data["link_product"],
                'from_date'         => $data["from_date"],
                'to_date'           => $data["to_date"],
                'product_id'        => $data["product_id"]
            ]);
        } else {
            (new \Modules\Product\Entities\ProductLottery())->create($data);
        }
    }else{
        (new \Modules\Product\Entities\ProductLottery())->where("product_id",$product->id)->delete();
    }

    (new \Modules\Product\Entities\Product)->where("id",$product->id)->update([
        "current_price"     => $currentPrice
    ]);
}

function getLotteryProduct($id, $data) {
    $lottery = (new \Modules\Product\Entities\ProductLottery)->where("product_id",$id)->first();

    $data["product"]->product_id = $lottery->product_id;
    $data["product"]->min_ticket = $lottery->min_ticket;
    $data["product"]->max_ticket = $lottery->max_ticket;
    $data["product"]->max_ticket_user = $lottery->max_ticket_user;
    $data["product"]->winner = $lottery->winner;
    $data["product"]->initial_price = $lottery->initial_price;
    $data["product"]->bottom_price = $lottery->bottom_price;
    $data["product"]->reduce_price = $lottery->reduce_price;
    $data["product"]->current_price = $lottery->current_price;
    $data["product"]->link_product = $lottery->link_product;
    $data["product"]->from_date = $lottery->from_date;
    $data["product"]->to_date = $lottery->to_date;

    return $data;
}

function updateProductLottery($orderId){
    $getProduct = \Modules\Order\Entities\OrderProduct::where("order_id",$orderId)->get();
    foreach ($getProduct as $key => $value){
        $getLottery = \Modules\Product\Entities\ProductLottery::where("product_id",$value->product_id)->first();

        if($getLottery) {
            $soldTickets = (int)getSoldLottery($getLottery->product_id);

            if ($soldTickets >= (int)$getLottery->min_ticket) {
                // unlock product
                (new \Modules\Product\Entities\Product)->where("id", $getLottery->product_id)->update(['is_unlocked' => "true"]);
                $currentPrice = $getLottery->initial_price - ($soldTickets - (int)$getLottery->min_ticket) * $getLottery->reduce_price;
                if ($currentPrice < $getLottery->bottom_price) {
                    $currentPrice = $getLottery->bottom_price;
                }

                (new \Modules\Product\Entities\ProductLottery)->where("id", $getLottery->id)->update([
                    "current_price" => $currentPrice,
                ]);

                (new \Modules\Product\Entities\Product)->where("id",$getLottery->product_id)->update([
                    "current_price"     => $currentPrice
                ]);

                (new \Modules\Product\Entities\Product)->where("id",$getLottery->link_product)->update([
                    "current_price"     => $currentPrice
                ]);
            }
        }
    }
}

function getSoldLottery($product_id) {
    return \Modules\Order\Entities\OrderProduct::where('product_id',$product_id)->sum('qty');
}

function isAddedToWishlist($userId, $productId){
    $wishlist = \DB::table('wish_lists')->where([
        "user_id"       => $userId,
        "product_id"    => $productId,
    ])->first();

    return (is_null($wishlist)) ? false : true;
}

function initializeFoloosi(){
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://foloosi.com/api/v1/api/initialize-setup",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => [
            "transaction_amount"    => 1,
            "currency"              => "AED",
            "customer_address"      => "Address",
            "customer_city"         => "Dubai",
            "billing_country"       => "AE",
            "billing_state"         => "Dubai",
            "billing_postal_code"   => "000000",
            "customer_name"         => "Test",
            "customer_email"        => "isaqib23@gmail.com",
            "customer_mobile"       => "0569038033"
        ],
        CURLOPT_HTTPHEADER => array(
            "content-type: multipart/form-data",
            "merchant_key: ".setting('foloosi_merchant_key')
        ),
    ));
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) {
        $responseData =  $err;
    } else {
        $responseData = json_decode($response,true);
    }

    return isset($responseData['data']) ? $responseData['data'] : $responseData;
}

function updateWinner($product,$data){

    if($data["product_type"] == 1 && !is_null($data["winner_id"])) {
        $winner = \DB::table('winners')->where("product_id",$product->id)->first();

        if($winner){
            \DB::table('winners')->where("product_id",$product->id)->update([
                "winner_id"     => $data["winner_id"],
                "updated_at"    => date("Y-m-d H:i:s")
            ]);

            return true;
        }

        \DB::table('winners')->insert([
            "product_id"    => $product->id,
            "winner_id"     => $data["winner_id"],
            "created_at"    => date("Y-m-d H:i:s"),
            "updated_at"    => date("Y-m-d H:i:s")
        ]);

        return true;
    }
}

function generateAirWayBill($user_id,$address){
    $user = User::where("id",$user_id)->first();
    $userAddress = Address::where("id",$address)->first();

    $postData = new \StdClass();
    $postData->UserName = "3000";
    $postData->Password = "fftes";
    $postData->AccountNo = "15527";
    $postData->Country = "AE";
    $postData->AirwayBillData = new \StdClass();
    $postData->AirwayBillData->AirWayBillCreatedBy = $userAddress->first_name." ".$userAddress->last_name;
    $postData->AirwayBillData->CODAmount = "0";
    $postData->AirwayBillData->CODCurrency = "";
    $postData->AirwayBillData->Destination = "BOM";
    $postData->AirwayBillData->DutyConsigneePay = "0";
    $postData->AirwayBillData->GoodsDescription = "DOCUMENTS";
    $postData->AirwayBillData->NumberofPeices = 1;
    $postData->AirwayBillData->Origin = "DXB";
    $postData->AirwayBillData->ProductType = "XPS";
    $postData->AirwayBillData->ReceiversAddress1 = $userAddress->address_1;
    $postData->AirwayBillData->ReceiversAddress2 = $userAddress->address_2;
    $postData->AirwayBillData->ReceiversCity = $userAddress->city;
    $postData->AirwayBillData->ReceiversSubCity = "";
    $postData->AirwayBillData->ReceiversCountry = $userAddress->country;
    $postData->AirwayBillData->ReceiversCompany = $userAddress->first_name." ".$userAddress->last_name;
    $postData->AirwayBillData->ReceiversContactPerson = $userAddress->first_name." ".$userAddress->last_name;
    $postData->AirwayBillData->ReceiversEmail = "";
    $postData->AirwayBillData->ReceiversGeoLocation = "";
    $postData->AirwayBillData->ReceiversMobile = $user->phone;
    $postData->AirwayBillData->ReceiversPhone = $user->phone;
    $postData->AirwayBillData->ReceiversPinCode = $userAddress->zip;
    $postData->AirwayBillData->ReceiversProvince = "";
    $postData->AirwayBillData->SendersAddress1 = "21 A AL KHABAISI STREET";
    $postData->AirwayBillData->SendersAddress2 = "DEIRA DUBAI";
    $postData->AirwayBillData->SendersCity = "DUBAI";
    $postData->AirwayBillData->SendersSubCity  = "";
    $postData->AirwayBillData->SendersCountry = "AE";
    $postData->AirwayBillData->SendersCompany = "FIRST FLIGHT COURIERS ME LLC";
    $postData->AirwayBillData->SendersContactPerson = "SANTHOSH BHASKAR";
    $postData->AirwayBillData->SendersEmail = "santhosh@firstflightme.com";
    $postData->AirwayBillData->SendersGeoLocation = "";
    $postData->AirwayBillData->SendersMobile = "+971558453274";
    $postData->AirwayBillData->SendersPhone = "+97142530300";
    $postData->AirwayBillData->SendersPinCode = "";
    $postData->AirwayBillData->ServiceType = "NOR";
    $postData->AirwayBillData->ShipmentDimension = "15X20X25";
    $postData->AirwayBillData->ShipmentInvoiceCurrency = "USD";
    $postData->AirwayBillData->ShipmentInvoiceValue = 10;
    $postData->AirwayBillData->ShipperReference = "ABCDEF74";
    $postData->AirwayBillData->ShipperVatAccount = "";
    $postData->AirwayBillData->SpecialInstruction = "";
    $postData->AirwayBillData->Weight = 1;


    $json = json_encode($postData);
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://ontrack.firstflightme.com/FFCService.svc/CreateAirwayBill");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

    $output = json_decode(curl_exec($ch));
    $AirwayBillNumber = $output->AirwayBillNumber;

    curl_close ($ch);

    return $AirwayBillNumber;
}

function getDHLDeliveryRate($user, $address){
    $date = new \DateTime("now");
    $plannedShippingDateAndTime = $date->format('Y-m-d\Th:i \G\M\TO');
    $postData = [
        "customerDetails"               => [
            "shipperDetails"    => [
                "postalCode"    => $address->zip,
                "cityName"      => $address->city,
                "countryCode"   => $address->country,
                "provinceCode"  => $address->country,
                "addressLine1"  => $address->address_1,
                "addressLine2"  => " ",
                "addressLine3"  => " ",
                "countyName"    => \Symfony\Component\Intl\Countries::getName($address->country)
            ],
            "receiverDetails"   => [
                "postalCode" => "14800",
                "cityName" => "Dubai",
                "countryCode" => "AE",
                "provinceCode" => "CZ",
                "addressLine1" => "addres1",
                "addressLine2" => "addres2",
                "addressLine3" => "addres3",
                "countyName" => "Central Bohemia"
            ]
        ],
        "accounts"                      => [
            [
                "typeCode"  => "shipper",
                "number"    => "962394856"
            ]
        ],
        "productCode"                   => "P",
        "plannedShippingDateAndTime"    => $plannedShippingDateAndTime,
        "unitOfMeasurement"             => "metric",
        "isCustomsDeclarable"           => true,
        "packages"                      => [
            [
                "typeCode"      => "3BX",
                "weight"        => 10.5,
                "dimensions"    => [
                    "length" => 25,
                    "width" => 35,
                    "height" => 15
                ]
            ]
        ],
    ];


    $json = json_encode($postData);
    $ch = curl_init();

    $headers = array(
        "Content-Type: application/json",
        "Accept: application/json"
    );

    curl_setopt($ch, CURLOPT_URL, "https://express.api.dhl.com/mydhlapi/test/rates");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_USERPWD, env('DHL_API_KEY') . ":" . env('DHL_SECRET_KEY'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

    $output = json_decode(curl_exec($ch));

    curl_close ($ch);

    return [
        "carrier_name"      => $output->products[0]->productName,
        "delivery_rate"     => $output->products[0]->totalPrice[0]->price,
        "delivery_in_days"  => $output->products[0]->deliveryCapabilities->totalTransitDays
    ];
}

function getUserCart($request){
    Cart::clear();
    $userCart = DB::table("user_cart")->where("user_id", $request->input('user_id'))->get();
    if(!is_null($userCart)){
        foreach ($userCart as $cart) {
            $getProduct = Product::getProductById($cart->product_id);
            if($getProduct && $getProduct->product_type == 1) {
                Cart::store($cart->product_id, $cart->qty, json_decode($cart->options) ?? []);
            }
        }

        $shippingMethod = getUserShipping($request);
        if($shippingMethod && !is_null($shippingMethod->address_id)) {
            Cart::addShippingMethod($shippingMethod);
        }else{
            Cart::removeShippingMethod();
        }

        $cartArray = Cart::toArray();
        foreach ($cartArray as $key => $value){
            if($key == "items") {
                $cartArray["items"] = array_values($value->toArray());
                foreach ($cartArray["items"] as $key1 => $value1){
                    $product = $value1->product;
                    $cartArray["items"][$key1]->product->sold_items = (string) getSoldLottery($product->id);
                    $cartArray["items"][$key1]->product->is_added_to_wishlist = isAddedToWishlist($request->input('user_id'), $product->id);
                    $cartArray["items"][$key1]->product->thumbnail_image = (!is_null($product->base_image->path)) ? $product->base_image : NULL;
                    $cartArray["items"][$key1]->product->suppliers = (!is_null($product->supplier->id)) ? $product->supplier : NULL;
                }
            }
        }

        $cartArray["shipping_amount"] = Cart::shippingCost()->amount();
        $cartArray["shipping_content"] = (Cart::hasShippingMethod()) ? Cart::shippingMethod()->content() : "";
        $cartArray["shipping_address"] = (Cart::hasShippingMethod()) ? Cart::shippingMethod()->address() : "";
        return $cartArray;
    }

    return [];
}

function saveUserShipping($request,$userShipping){
    $shipping = \DB::table("user_shippings")->where("user_id",$request->user_id)->first();

    if(!$shipping){
        return \DB::table("user_shippings")->insert([
            "address_id"    => $request->address_id,
            "delivery_type"    => $request->delivery_type,
            "label"         => (isset($userShipping->label)) ? $userShipping->label : null,
            "amount"        => (isset($userShipping->amount)) ? $userShipping->amount : null,
            "user_id"        => $request->user_id,
            "content"        => $userShipping->content,
            "created_at"    => date("Y-m-d H:i:s"),
            "updated_at"    => date("Y-m-d H:i:s"),
        ]);
    }else{
        return \DB::table("user_shippings")->where("user_id",$request->user_id)
            ->update([
                "address_id"        => $request->address_id,
                "delivery_type"    => $request->delivery_type,
                "content"    => $userShipping->content,
                "label"         => (isset($userShipping->label)) ? $userShipping->label : null,
                "amount"        => (isset($userShipping->amount)) ? $userShipping->amount : null,
                "updated_at"    => date("Y-m-d H:i:s")
            ]);
    }
}

function getUserShipping($request){
    return \DB::table("user_shippings")->where("user_id",$request->user_id)->first();
}

function getdirectCart($request){
    Cart::clear();
    $userCart = DB::table("user_cart")->where("user_id", $request->input('user_id'))->get();
    if(!is_null($userCart)){
        foreach ($userCart as $cart) {
            $getProduct = Product::getProductById($cart->product_id);
            if($getProduct && $getProduct->product_type == 0) {
                Cart::store($cart->product_id, $cart->qty, json_decode($cart->options) ?? []);
            }
        }

        $shippingMethod = getUserShipping($request);
        if($shippingMethod && !is_null($shippingMethod->address_id)) {
            Cart::addShippingMethod($shippingMethod);
        }else{
            Cart::removeShippingMethod();
        }

        $cartArray = Cart::toArray();
        foreach ($cartArray as $key => $value){
            if($key == "items") {
                $cartArray["items"] = array_values($value->toArray());
                foreach ($cartArray["items"] as $key1 => $value1){
                    $product = $value1->product;
                    $cartArray["items"][$key1]->product->sold_items = (string) getSoldLottery($product->id);
                    $cartArray["items"][$key1]->product->is_added_to_wishlist = isAddedToWishlist($request->input('user_id'), $product->id);
                    $cartArray["items"][$key1]->product->thumbnail_image = (!is_null($product->base_image->path)) ? $product->base_image : NULL;
                    $cartArray["items"][$key1]->product->suppliers = (!is_null($product->supplier->id)) ? $product->supplier : NULL;
                }
            }
        }

        $cartArray["shipping_amount"] = Cart::shippingCost()->amount();
        $cartArray["shipping_content"] = (Cart::hasShippingMethod()) ? Cart::shippingMethod()->content() : "";
        $cartArray["shipping_address"] = (Cart::hasShippingMethod()) ? Cart::shippingMethod()->address() : "";
        return $cartArray;
    }

    return [];
}
