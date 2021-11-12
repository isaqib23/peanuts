<?php

namespace Modules\Votes\Admin;

use Modules\Admin\Ui\Tab;
use Modules\Admin\Ui\Tabs;

class VotesTabs extends Tabs
{
    public function make()
    {
        $this->group('brand_information', trans('brand::brands.tabs.group.brand_information'))
            ->active()
            ->add($this->general());
    }

    private function general()
    {
        return tap(new Tab('general', trans('votes::votes.tabs.general')), function (Tab $tab) {
            $tab->active();
            $tab->weight(5);
            $tab->fields(['status']);
            $tab->view('votes::admin.votes.tabs.general',[
                'product_1'     => $this->link_products(),
                'product_2'     => $this->link_products(),
                'status'        => ["Active","Disable"],
            ]);
        });
    }

    private function link_products(){
        return (new \Modules\Product\Entities\Product)->get()->pluck("name","id")->prepend(trans('admin::admin.form.please_select'), '');
    }
}
