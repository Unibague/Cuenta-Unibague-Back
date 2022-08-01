<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordChangeRequest extends Model
{
    protected $guarded = [];
    protected $table = 'password_change_requests';
}
