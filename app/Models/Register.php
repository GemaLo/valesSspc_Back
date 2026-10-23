<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class Register extends Model
{
    protected $connection = 'oracle_primary';
    protected $table = 'registers';
    
    protected $primaryKey = 'IDREGISTER';
    
    public $timestamps = true; 

    protected $fillable = [
        'NOMBRECOMPLETO',
        'NUMEMPLEADO',
        'NOMEMPLEADO',
        'APPEMPLEADO',
        'APMEMPLEADO',
        'UR',
        'RFC',
        'CURP',
        'NSS',
        'NIVEL',
        'PUESTO',
        'UBICACION',
        'IDENLACE',
        'CLAVE',
        'CUENTAINICIAL',
        'MODALIDAD',
        'IDSTATUS',
        'ORIGEN',
        'PENSION'
    ];
}