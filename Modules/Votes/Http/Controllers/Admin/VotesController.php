<?php

namespace Modules\Votes\Http\Controllers\Admin;

use Modules\Admin\Traits\HasCrudActions;
use Modules\Votes\Entities\Votes;
use Modules\Votes\Http\Requests\CreateVoteRequest;

class VotesController
{
    use HasCrudActions;

    /**
     * Model for the resource.
     *
     * @var string
     */
    protected $model = Votes::class;

    /**
     * Label of the resource.
     *
     * @var string
     */
    protected $label = 'votes::votes.votes';

    /**
     * View path of the resource.
     *
     * @var string
     */
    protected $viewPath = 'votes::admin.votes';

    /**
     * Form requests for the resource.
     *
     * @var array
     */
    protected $validation = CreateVoteRequest::class;
}
