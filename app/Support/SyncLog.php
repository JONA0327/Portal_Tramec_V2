<?php

namespace App\Support;

/**
 * Registro durable (archivo, no caché) de cuándo fue la última sincronización
 * exitosa y el último intento fallido de cada servicio. Sobrevive a `cache:clear`.
 */
class SyncLog
{
    private static function path(): string
    {
        return storage_path('app/sync-log.json');
    }

    private static function read(): array
    {
        $raw = @file_get_contents(self::path());
        $data = $raw ? json_decode($raw, true) : null;

        return is_array($data) ? $data : [];
    }

    private static function mutate(string $name, callable $fn): void
    {
        $fh = @fopen(self::path(), 'c+');
        if (! $fh) {
            return;
        }
        flock($fh, LOCK_EX);
        $data = json_decode(stream_get_contents($fh) ?: '', true);
        $data = is_array($data) ? $data : [];
        $data[$name] = $fn($data[$name] ?? []);
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($data, JSON_PRETTY_PRINT));
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    public static function success(string $name): void
    {
        self::mutate($name, fn ($e) => ['last_success' => now()->toDateTimeString(), 'last_error' => null] + $e);
    }

    public static function failure(string $name, string $error): void
    {
        self::mutate($name, fn ($e) => array_merge($e, [
            'last_attempt_failed' => now()->toDateTimeString(),
            'last_error' => mb_substr($error, 0, 300),
        ]));
    }

    /** Fecha guardada (hora del servidor, UTC) -> Carbon en hora de México. */
    public static function mx(?string $value): ?\Illuminate\Support\Carbon
    {
        return $value
            ? \Illuminate\Support\Carbon::parse($value, config('app.timezone'))->setTimezone('America/Mexico_City')
            : null;
    }

    public static function lastSuccess(string $name): ?string
    {
        return self::read()[$name]['last_success'] ?? null;
    }

    public static function lastFailure(string $name): ?string
    {
        return self::read()[$name]['last_attempt_failed'] ?? null;
    }
}
