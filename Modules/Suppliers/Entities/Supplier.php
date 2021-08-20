<?php

namespace Modules\Suppliers\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Suppliers\Admin\SuppliersTable;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'logo',
        'fax',
        'website',
        'address'
    ];

    public function table(){
        return new SuppliersTable($this->newQuery());
    }
}
