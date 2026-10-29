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
        return $this->belongsTo(Register::class, 'NUMEMPLEADO', 'NUMEMPLEADO');
    }

    protected $fillable = [
        'NUMEMPLEADO',
        'nomEmpleado',
        'nombrePensionado',
        'curp',
        'rfc',
        'cuenta',
        'porcentaje'
    ];
}