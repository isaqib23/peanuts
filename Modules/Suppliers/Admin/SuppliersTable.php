<?php

namespace Modules\Suppliers\Admin;

use Modules\Admin\Ui\AdminTable;
use Modules\Suppliers\Entities\Supplier;
use Yajra\DataTables\DataTables;

class SuppliersTable extends AdminTable
{
    /**
     * Make table response for the resource.
     *
     * @return DataTables
     */
    public function make()
    {
        return $this->newTable();
    }
}
