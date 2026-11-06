<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    protected $table = 'personas';
    protected $primaryKey = 'idPerson';

    protected $fillable = [
        'idTypePerson',
        'nomPerson',
        'appPerson',
        'apmPerson',
        'curp',
        'rfc',
        'idregister',
    ];

    public function register()
    {
        return $this->belongsTo(Register::class, 'idregister', 'idRegister');
    }

    public function typePerson()
    {
        return $this->belongsTo(TypePerson::class, 'idtypeperson', 'idtypeperson');
    }
}