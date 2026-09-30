<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable; 

    protected $connection = 'oracle_primary';
    protected $table = 'users'; 
    protected $primaryKey = 'id_user';

    protected $fillable = [
        'firstName', 'lastName', 'middleName', 'nivel', 
        'unidad', 'active', 'idType', 'email', 'password'
    ];

    protected $hidden = [
        'password',
    ];

    public function typeUser()
    {
        return $this->belongsTo(TypeUser::class, 'idType', 'idType');
    }

    public function getAttributes()
{
    // Cambia las claves de los atributos a minúsculas de forma global para este modelo
    $attributes = parent::getAttributes();
    $lowercaseAttributes = [];
    
    foreach ($attributes as $key => $value) {
        $lowercaseAttributes[strtolower($key)] = $value;
    }
    
    return $lowercaseAttributes;
}
}