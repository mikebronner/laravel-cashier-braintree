<?php

namespace Laravel\Cashier\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable;

class User extends Model
{
    use Billable;
}
