<?php

namespace Modules\Votes\Admin;

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
        if (! request()->routeIs('admin.votes.edit')) {
            return;
        }

        return tap(new Tab('votes', trans('votes::sidebar.votes')), function (Tab $tab) {
            $tab->weight(50);
            $tab->view('votes::admin.votes.index');
        });
    }
}
