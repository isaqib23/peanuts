<?php

namespace Modules\Product\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductLottery extends Model
{
    use HasFactory;

    protected $table = "product_lottery";

    protected $fillable = [
        'min_ticket',
        'max_ticket',
        'max_ticket_user',
        'winner',
        'initial_price',
        'bottom_price',
        'reduce_price',
        'current_price',
        'link_product',
        'from_date',
        'to_date',
        'product_id'
    ];

    protected static function newFactory()
    {
        return \Modules\Product\Database\factories\ProductLotteryFactory::new();
    }
}
