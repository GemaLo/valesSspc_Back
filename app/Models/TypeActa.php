<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeActa extends Model
{
    protected $table = 'type_actas';
    protected $connection = 'oracle_primary';
    protected $primaryKey = 'id_typeActa';
    protected $fillable = [
        'nameType',
    ];
}
