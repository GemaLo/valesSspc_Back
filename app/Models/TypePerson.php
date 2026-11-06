<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypePerson extends Model
{
    protected $table = 'typePerson';
    protected $primaryKey = 'idTypePerson';

    protected $fillable = [
        'TypePerson',
    ];
}