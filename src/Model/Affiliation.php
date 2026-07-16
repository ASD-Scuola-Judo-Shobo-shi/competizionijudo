<?php

declare(strict_types=1);

namespace App\Model;

final class Affiliation
{
    /** @var array<string, string> */
    private const OPTIONS = [
        'ACSI' => 'ACSI – Associazione Centri Sportivi Italiani',
        'AICS' => 'AICS – Associazione Italiana Cultura Sport',
        'ASC' => 'ASC – Attività Sportive Confederate',
        'ASI' => 'ASI – Associazioni Sportive Sociali Italiane',
        'CNS LIBERTAS' => 'CNS LIBERTAS – Centro Nazionale Sportivo Libertas',
        'CSAIN' => 'CSAIN – Centri Sportivi Aziendali Industriali',
        'CSEN' => 'CSEN – Centro Sportivo Educativo Nazionale',
        'CSI' => 'CSI – Centro Sportivo Italiano',
        'ENDAS' => 'ENDAS – Ente Nazionale Democratico di Azione Sociale e Sportiva',
        'FIJLKAM' => 'FIJLKAM – Federazione Italiana Judo Lotta Karate Arti Marziali',
        'MSP Italia' => 'MSP Italia – Movimento Sportivo Popolare Italia',
        'OPES' => "OPES – Organizzazione per l'Educazione allo Sport",
        'PGS' => 'PGS – Polisportive Giovanili Salesiane',
        'UISP' => 'UISP – Unione Italiana Sport Per tutti',
        'US ACLI' => 'US ACLI – Unione Sportiva ACLI',
    ];

    private function __construct()
    {
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return self::OPTIONS;
    }

    /** @return list<string> */
    public static function selected(mixed $value): array
    {
        $values = is_array($value) ? $value : self::decodeString($value);
        $selected = [];

        foreach ($values as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $code = trim($candidate);
            if ($code !== '' && array_key_exists($code, self::OPTIONS)) {
                $selected[$code] = $code;
            }
        }

        return array_values($selected);
    }

    /** @return list<string> */
    public static function decode(?string $value): array
    {
        return self::selected($value);
    }

    /** @param list<string> $values */
    public static function encode(array $values): ?string
    {
        $selected = self::selected($values);

        return $selected === []
            ? null
            : json_encode($selected, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** @return list<mixed> */
    private static function decodeString(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [$value];
        }

        return is_array($decoded) ? $decoded : [$value];
    }
}
