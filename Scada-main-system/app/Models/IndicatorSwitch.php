<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndicatorSwitch extends Model
{
    protected $fillable = [
        'element_no',
        'name',
        'value'
    ];
}
