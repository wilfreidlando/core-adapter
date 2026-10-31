<?php

declare(strict_types=1);

namespace SaasCommerce\Core\Support;

/**
 * La lecture défensive des charges utiles du Core.
 *
 * Une réponse du Core est du JSON ; on ne suppose jamais qu'un champ est là,
 * ni de quel type il est. Chaque accesseur renvoie `null` (ou la valeur par
 * défaut) quand le champ manque ou n'a pas le type attendu : une réponse
 * inattendue ne doit jamais faire planter l'application cliente.
 */
final class Payload
{
    /**
     * @param array<string, mixed> $data
     */
    public static function string(array $data, string $key, ?string $default = null): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function int(array $data, string $key, ?int $default = null): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function bool(array $data, string $key, bool $default = false): bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<mixed>
     */
    public static function list(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function map(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $name => $item) {
            if (is_string($name)) {
                $map[$name] = $item;
            }
        }

        return $map;
    }
}
