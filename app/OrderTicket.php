<?php

namespace FleetCart;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderTicket extends Model
{
    use HasFactory;

    protected $table = "order_tickets";

    protected $fillable = ["product_id","ticket_number","is_valid","status","order_id"];
}
