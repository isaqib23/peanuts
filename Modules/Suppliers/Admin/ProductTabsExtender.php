<?php

namespace Modules\Suppliers\Admin;

use Modules\Admin\Ui\Tab;
use Modules\Admin\Ui\Tabs;

class ProductTabsExtender
{
    public function extend(Tabs $tabs)
    {
        $tabs->group('advanced_information')
            ->add($this->votes());
    }

    private function votes()
    {
        if (! request()->routeIs('admin.suppliers.edit')) {
            return;
        }

        return tap(new Tab('votes', trans('suppliers::sidebar.suppliers')), function (Tab $tab) {
            $tab->weight(50);
            $tab->view('votes::admin.suppliers.index');
        });
    }
}
