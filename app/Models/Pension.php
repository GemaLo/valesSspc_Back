<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pension extends Model
{
    protected $primaryKey = 'idPension';
    protected $connection = 'oracle_primary';

    protected $table = 'pensiones';
    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Register::class, 'numempleado', 'NUMEMPLEADO');
    }
    protected $fillable = ['numEmpleado', 'nomEmpleado', 'nombrePensionado', 'curp', 'rfc', 'cuenta', 'porcentaje'];
}






