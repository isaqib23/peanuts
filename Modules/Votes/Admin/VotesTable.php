<?php

namespace Modules\Votes\Admin;

use Modules\Admin\Ui\AdminTable;
use Modules\Votes\Entities\Votes;

class VotesTable extends AdminTable
{
    /**
     * Make table response for the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function make()
    {
        return $this->newTable()
            ->addColumn('logo', function (Votes $votes) {
                return view('admin::partials.table.image', [
                    'file' => $votes->logo,
                ]);
            });
    }
}
