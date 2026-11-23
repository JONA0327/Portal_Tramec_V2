<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiToken extends Command
{
    protected $signature = 'api:token {email : correo del usuario (define el nivel y las reglas de precio que verá)} {nombre=integracion : etiqueta del token}';

    protected $description = 'Crea un token de API para un tercero (se muestra una sola vez)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No existe ese usuario.');

            return self::FAILURE;
        }

        $token = Str::random(60);
        DB::table('api_tokens')->insert(['user_id' => $user->id, 'nombre' => $this->argument('nombre'), 'token' => hash('sha256', $token), 'created_at' => now(), 'updated_at' => now()]);
        $this->info("Token (guárdalo, no se vuelve a mostrar): {$token}");

        return self::SUCCESS;
    }
}
