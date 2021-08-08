<?php

function updateLotteryProduct($product,$data,$method){
    if($data["product_type"] == 1) {
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
                (new \Modules\Product\Entities\Product)->where("id", $getLottery->product_id)->update(['is_unlocked' => true]);
                $currentPrice = $getLottery->initial_price - ($soldTickets - (int)$getLottery->min_ticket) * $getLottery->reduce_price;
                if ($currentPrice < $getLottery->bottom_price) {
                    $currentPrice = $getLottery->bottom_price;
                }

                (new \Modules\Product\Entities\ProductLottery)->where("id", $getLottery->id)->update([
                    "current_price" => $currentPrice,
                ]);
            }
        }
    }
}

function getSoldLottery($product_id) {
    return \Modules\Order\Entities\OrderProduct::where('product_id',$product_id)->sum('qty');
}

/**
 * if the sold ticket >= minimum ticket
{
UNlock the direct purchase
cureent price = Initial Price - (sold ticket - minimum ticket) * Reduce amount
if (cureent price < Bottom Price)
{
current price = Bottom Price
}
}
 */
