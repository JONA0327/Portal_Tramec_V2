<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    // La tabla real no tiene columna "name": v1 guarda first_name/last_name por separado.
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'active_flag',
        'level',
    ];

    /**
     * Qué secciones ve cada nivel (menú de v1): Inicio todos menos 10; Productos 0-9;
     * Almacenes 0, 1, 7; Existencias 0-9; Manuales 0-9. Sin nivel asignado no ve ninguna.
     */
    public function puedeVer(string $modulo): bool
    {
        if ($this->level === null) {
            return $modulo === 'inicio';
        }

        $niveles = match ($modulo) {
            'inicio' => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
            'productos' => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
            'almacenes' => [0, 1, 7],
            'existencias' => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
            'manuales' => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
            default => [],
        };

        return in_array((int) $this->level, $niveles, true);
    }

    /**
     * Columnas de precio de Existencias que ve este nivel, como en v1: los niveles de venta ven
     * solo "Su Costo" (la columna de su canal); los internos ven varios canales. Todos los
     * niveles con acceso ven el Precio de Lista, (los niveles sin acceso no ven ninguna).
     * ponytail: v1 además aplicaba, para DELTA, un factor fijo sobre list_price en la columna
     * "canal" para CIPSA (×0.6593) y canal entrante (×0.645) — sí portado, ver
     * App\Models\Product::canalPrecio() y PriceRule::FACTORES_DELTA_NIVEL. Para CIPSA (nivel 6,
     * un solo canal) ese factor especial YA ES la columna "canal"/"Su Costo". Para canal entrante
     * (nivel 9, varios canales) el factor especial es una tarifa propia distinta del canal normal,
     * así que se restaura como columna aparte "Su Costo" (ver ExistenciasController y
     * ProductsController, que ponen el precio normal de canal (price3) en 'canal' y el factor
     * especial en 'su_costo' solo para este nivel).
     */
    public function columnasPrecio(): array
    {
        $lista = ['precio_lista' => 'Precio de Lista'];

        return match ($this->level === null ? -1 : (int) $this->level) {
            0, 7 => ['canal_tmc' => 'CANAL TMC', 'canal' => 'CANAL', 'oem' => 'OEM', 'usuario_final' => 'USUARIO FINAL'] + $lista,
            1 => ['canal' => 'CANAL', 'oem' => 'OEM', 'usuario_final' => 'USUARIO FINAL'] + $lista,
            8 => ['canal' => 'CANAL', 'oem' => 'OEM'] + $lista,
            9 => ['su_costo' => 'Su Costo', 'canal' => 'CANAL', 'oem' => 'OEM', 'usuario_final' => 'USUARIO FINAL'] + $lista,
            2 => ['canal_tmc' => 'Su Costo'] + $lista,
            3, 6 => ['canal' => 'Su Costo'] + $lista,
            4 => ['oem' => 'Su Costo'] + $lista,
            5 => ['usuario_final' => 'Su Costo'] + $lista,
            default => [],
        };
    }

    /** Carrito y Cotizador: administrador (0), Equipo Tramec (1) y Equipo Tramec Especial (7). */
    public function puedeCotizar(): bool
    {
        return $this->level !== null && in_array((int) $this->level, [0, 1, 7], true);
    }

    /** Clientes finales de canal (2 a 6): ven solo su costo y el precio de lista, sin avisos internos de catálogo. */
    public function esCliente(): bool
    {
        return $this->level !== null && in_array((int) $this->level, [2, 3, 4, 5, 6], true);
    }

    /** v1: solo estos niveles tenían el botón de copiar precio ($canCopyPrice). */
    public function puedeCopiarPrecios(): bool
    {
        return $this->level !== null && in_array((int) $this->level, [0, 1, 7, 8, 9], true);
    }

    // users.level 0 = ADMINISTRADOR (mismo criterio que v1 para administración).
    public function isAdmin(): bool
    {
        return $this->level !== null && (int) $this->level === 0;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
