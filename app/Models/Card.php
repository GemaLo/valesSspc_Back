<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    protected $connection = 'oracle_primary';
    protected $table = 'cards';

    protected $primaryKey = 'idCard';
    protected $fillable = [
        'IDREGISTER',
        'folioCard',
        'cuenta',
        'idStatus',
        'observaciones',
    ];
}
