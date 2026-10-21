<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class Register extends Model
{
    protected $connection = 'oracle_primary';
    protected $table = 'registers';
    
    // Mapeamos exactamente la llave primaria en mayúsculas
    protected $primaryKey = 'IDREGISTER';
    
    public $timestamps = false; // O true si usas CREATED_AT / UPDATED_AT

    // Si quieres mapear los campos para usarlos cómodamente en Laravel:
    protected $fillable = [
        'NUMEMPLEADO',
        'NOMEMPLEADO',
        'APPEMPLEADO',
        'RFC',
        'CURP',
        'UNIDAD',
        'CARGO',
        'SS',
        'NIVEL',
        'UBICACION',
        'CLAVE_UR',
        'MODALIDAD',
        'CUENTAINICIAL',
        'IDSTATUS'
    ];
}