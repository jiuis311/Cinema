<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    public function tickets() {
        return $this->hasMany('\App\Ticket');
    }

    public function movieRoom() {
        return $this->belongsTo('\App\MovieRoom');
    }
}
