<?php

function updateLotteryProduct($product,$data,$method){
    if($data["product_type"] == 1) {
        $data["product_id"] = $product->id;

        $lottery = (new \Modules\Product\Entities\ProductLottery())->where("product_id",$product->id)->first();

        if ($lottery) {
            (new \Modules\Product\Entities\ProductLottery())->where("product_id",$product->id)->update($data);
        } else {
            (new \Modules\Product\Entities\ProductLottery())->create($data);
        }
    }else{
        (new \Modules\Product\Entities\ProductLottery())->where("product_id",$product->id)->delete();
    }
}
