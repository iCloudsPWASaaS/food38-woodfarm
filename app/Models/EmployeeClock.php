<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeClock extends Model
{
    protected $fillable = [
        'employee_id',
        'date',
        'clock_in',
        'clock_out',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}