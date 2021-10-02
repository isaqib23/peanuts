<?php

function updateLotteryProduct($product,$data,$method){
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
