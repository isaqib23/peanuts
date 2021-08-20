<?php

namespace Modules\Suppliers\Admin;

use Modules\Admin\Ui\Tab;
use Modules\Admin\Ui\Tabs;

class SuppliersTabs extends Tabs
{
    public function make()
    {
        $this->group('supplier_information', trans('suppliers::suppliers.tabs.group.supplier_information'))
            ->active()
            ->add($this->general());
    }

    private function general()
    {
        return tap(new Tab('general', trans('suppliers::suppliers.tabs.general')), function (Tab $tab) {
            $tab->active();
            $tab->weight(5);
            $tab->fields(['name']);
            $tab->view('suppliers::admin.suppliers.tabs.general');
        });
    }
}
