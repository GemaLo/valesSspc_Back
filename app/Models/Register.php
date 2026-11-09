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
    return $this->hasMany(Pension::class, 'NUMEMPLEADO', 'NUMEMPLEADO');
}
public function documentos()
    {
        return $this->hasMany(Document::class, 'idRegister', 'IDREGISTER');
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
        'OBSERVACIONINICIAL',
        'FECHAENTREGA',
    ];
}