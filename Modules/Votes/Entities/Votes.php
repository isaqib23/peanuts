<?php

namespace Modules\Votes\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Support\Eloquent\Translatable;
use Modules\Votes\Admin\VotesTable;

class Votes extends Model
{

    protected $fillable = [
        'product_1',
        'product_2',
        'count_1',
        'status',
        'count_2'
    ];

    public function table()
    {
        return new VotesTable($this->newQuery());
    }
}
