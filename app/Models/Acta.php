<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Acta extends Model
{
    protected $table = 'actas';
    protected $connection = 'oracle_primary';
    protected $primaryKey = 'id_acta';
    protected $fillable = [
        'acta',
        'id_typeActa',
        'id_user',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function typeActa()
    {
        return $this->belongsTo(TypeActa::class, 'id_typeActa', 'id_typeActa');
    }
}