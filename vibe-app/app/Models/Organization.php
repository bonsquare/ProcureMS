<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'status'];

    public function users() { return $this->hasMany(User::class); }
    public function schools() { return $this->hasMany(School::class); }
}
