<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Register extends Model
{
    protected $connection = 'oracle_primary';
    protected $table = 'registers';
    protected $primaryKey = 'idRegister';
    protected $fillable = ['numEmpleado', 'nomEmpleado', 'appEmpleado', 'rfc', 'curp', 'unidad', 'cargo', 'ss', 'nivel', 'ubicacion', 'clave_UR', 'modalidad', 'idStatus'];
}
