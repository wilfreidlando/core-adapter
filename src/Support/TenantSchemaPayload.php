<?php

declare(strict_types=1);

namespace CoreAdapter\Support;

/**
 * La charge utile de la déclaration de la forme du locataire.
 *
 * L'application écrit sa déclaration dans **son** vocabulaire — une clé
 * unique et son motif, des champs avec leur libellé, leur type et leur
 * longueur. Le Core n'en lit qu'une partie, dans **son** format : le motif
 * y est nu, et une source `declared.<x>` y nomme la clé du champ.
 *
 * Cette classe fait la traduction, et **refuse** ce qui n'a pas de
 * traduction honnête — sur place, avant l'envoi.
 */
final class TenantSchemaPayload
{
    /**
     * La déclaration, dans le format du Core.
     *
     * @param array{attribut: string, motif: string} $cleUnique la clé unique de l'application et son motif
     * @param list<array{cle: string, source: string, requis?: bool, libelle?: string, type?: string, longueur?: int}> $champs les champs dont l'application a besoin
     * @return array{unique_key: string, unique_key_pattern: string, fields: list<array{key: string, label: string, type: string, required: bool, max_length: int|null, source: string}>}
     *
     * @throws PatternRefused le motif n'a pas de traduction honnête
     * @throws DeclarationRefused une source `declared.<x>` ne nomme pas la clé du champ
     */
    public static function build(array $cleUnique, array $champs): array
    {
        $fields = [];

        foreach ($champs as $champ) {
            self::assertSourceNamesTheField($champ['cle'], $champ['source']);

            $fields[] = [
                'key' => $champ['cle'],
                'label' => $champ['libelle'] ?? $champ['cle'],
                'type' => $champ['type'] ?? 'string',
                'required' => (bool) ($champ['requis'] ?? false),
                'max_length' => $champ['longueur'] ?? null,
                'source' => $champ['source'],
            ];
        }

        return [
            'unique_key' => $cleUnique['attribut'],
            // Le motif de l'application est un motif `preg` ; le Core n'en
            // veut qu'un motif nu. La traduction est ici, et nulle part ailleurs.
            'unique_key_pattern' => CorePattern::normalize($cleUnique['motif']),
            'fields' => $fields,
        ];
    }

    /**
     * Une source `declared.<x>` nomme la clé du champ.
     *
     * Le Core stocke la valeur sous la **clé du champ** ; le paquet la lit
     * sous le **suffixe de la source**. Les deux ne coïncident que si la
     * source nomme la clé — et le Core refuse désormais la déclaration qui
     * ne le fait pas (ADR-0130 §2). Le paquet le vérifie avant l'envoi.
     */
    private static function assertSourceNamesTheField(string $cle, string $source): void
    {
        if (! str_starts_with($source, 'declared.')) {
            return;
        }

        if (substr($source, strlen('declared.')) !== $cle) {
            throw DeclarationRefused::becauseSourceDoesNotNameTheField($cle, $source);
        }
    }
}
