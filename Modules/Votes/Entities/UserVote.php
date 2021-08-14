<?php

namespace Modules\Votes\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserVote extends Model
{
    use HasFactory;

    protected $fillable = [];

    protected $table = "user_vote";

    protected static function newFactory()
    {
        return \Modules\Votes\Database\factories\UserVoteFactory::new();
    }
}
