<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pension extends Model
{
    protected $connection = 'oracle_primary';
    protected $table = 'pensiones';
    protected $primaryKey = 'IDPENSION';
    public $incrementing = true;

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Register::class, 'NUMEMPLEADO', 'NUMEMPLEADO');
    }

    protected $fillable = [
        'NUMEMPLEADO',
        'NOMEMPLEADO',
        'NOMBREPENSIONADO',
        'CURP',
        'RFC',
        'CUENTA',
        'PORCENTAJE'
    ];
}