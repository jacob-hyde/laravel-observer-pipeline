<?php

namespace JacobHyde\ObserverPipeline\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class TestUser extends Model
{
    protected $table = 'test_users';
    protected $guarded = [];
}