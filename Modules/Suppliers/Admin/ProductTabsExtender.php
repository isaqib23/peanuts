<?php

namespace Modules\Suppliers\Admin;

use Modules\Admin\Ui\Tab;
use Modules\Admin\Ui\Tabs;

class ProductTabsExtender
{
    public function extend(Tabs $tabs)
    {
        $tabs->group('advanced_information')
            ->add($this->suppliers());
    }

    private function suppliers()
    {
        if (! request()->routeIs('admin.suppliers.edit')) {
            return;
        }

        return tap(new Tab('suppliers', trans('suppliers::sidebar.suppliers')), function (Tab $tab) {
            $tab->weight(50);
            $tab->view('suppliers::admin.suppliers.index');
        });
    }
}
