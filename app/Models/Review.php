<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = ['appointment_id', 'client_id', 'stylist_id', 'rating', 'comment', 'status'];

    public function appointment() { return $this->belongsTo(Appointment::class); }
    public function client() { return $this->belongsTo(Client::class); }
    public function stylist() { return $this->belongsTo(Stylist::class); }
}
