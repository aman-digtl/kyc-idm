<?php

namespace DigtlCo\KycIdm\Models;

use Illuminate\Database\Eloquent\Model;

class KycUser extends Model {

    protected $table = 'kyc_user';
    protected $fillable =['user_id'];
    
}