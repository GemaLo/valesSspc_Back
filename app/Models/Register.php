<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Register extends Model
{
    protected $connection = 'oracle_primary';
    protected $table = 'registers';
    
    protected $primaryKey = 'IDREGISTER';
    
    public $timestamps = true; 

    public function pensionados(): HasMany
    {
        // Corregido: Llave foránea y local en mayúsculas para Oracle
        return $this->hasMany(Pension::class, 'NUMEMPLEADO', 'NUMEMPLEADO');
    }

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
        'PENSION',
        'IMPORTE',
        'OBSERVACIONINICIAL'
    ];
}