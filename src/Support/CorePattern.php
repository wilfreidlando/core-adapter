<?php

declare(strict_types=1);

namespace CoreAdapter\Support;

/**
 * Le motif de la clé unique, dans le format du Core.
 *
 * L'application écrit le motif **pour elle** : un motif `preg`, avec ses
 * délimiteurs, ses `\d` et ses groupes non capturants :
 *
 * ```php
 * ['attribut' => 'code', 'motif' => '/^AE[0-9]{4}(?:[0-9]{2}){0,2}$/']
 * ```
 *
 * Le Core n'accepte qu'un motif **nu**, dans l'alphabet d'une référence
 * (`A-Za-z0-9._:-`), avec `^` en tête et `$` en fin. C'est le même motif,
 * lu par deux lecteurs aux formats incompatibles — d'où cette classe : elle
 * traduit le motif de l'application en motif du Core, et **refuse** ce qui
 * n'a pas de traduction honnête.
 *
 * ```
 * motif applicatif : /^AE[0-9]{4}(?:[0-9]{2}){0,2}$/
 * motif envoyé     : ^AE[0-9]{4}([0-9]{2}){0,2}$
 * ```
 *
 * Le motif **applicatif** n'est jamais modifié : c'est lui que la validation
 * locale de la référence utilise.
 */
final class CorePattern
{
    /** L'alphabet d'une référence du Core. */
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789._:-';

    /** La longueur maximale d'un motif : c'est une forme, pas une donnée. */
    private const MAX_LENGTH = 256;

    /**
     * Le motif du Core, à partir du motif de l'application.
     *
     * @throws PatternRefused le motif n'a pas de traduction honnête
     */
    public static function normalize(string $applicative): string
    {
        $pattern = self::stripDelimiters($applicative);

        if ($pattern === '') {
            throw PatternRefused::because($applicative, 'le motif est vide');
        }

        $output = '';
        $length = strlen($pattern);

        for ($index = 0; $index < $length; $index++) {
            $char = $pattern[$index];

            if ($char === '\\') {
                $output .= self::escape($pattern, $index, $length, $applicative);
                // L'échappement a consommé deux caractères : le `\` et l'escapé.
                $index++;

                continue;
            }

            if ($char === '[') {
                $end = self::classEnd($pattern, $index, $applicative);
                self::assertClassIsWithinTheAlphabet(substr($pattern, $index + 1, $end - $index - 1), $applicative);

                $output .= substr($pattern, $index, $end - $index + 1);
                $index = $end;

                continue;
            }

            if ($char === '{') {
                $end = self::quantifierEnd($pattern, $index, $applicative);

                $output .= substr($pattern, $index, $end - $index + 1);
                $index = $end;

                continue;
            }

            if ($char === '(') {
                if (($pattern[$index + 1] ?? '') === '?') {
                    if (substr($pattern, $index, 3) === '(?:') {
                        // Le groupe non capturant : le Core n'exploite pas les
                        // captures, un groupe simple dit la même chose.
                        $output .= '(';
                        $index += 2;

                        continue;
                    }

                    throw PatternRefused::because(
                        $applicative,
                        'les groupes étendus ((?i), (?P<…>)) ne sont pas admis par le Core',
                    );
                }

                $output .= '(';

                continue;
            }

            if ($char === '.') {
                throw PatternRefused::because(
                    $applicative,
                    'le joker « . » est interdit : il matcherait un caractère hors de l\'alphabet du Core',
                );
            }

            if ($char === ']') {
                throw PatternRefused::because($applicative, 'un crochet fermant qui n\'ouvre rien');
            }

            // Les ancres : le Core n'admet `^` qu'en tête et `$` qu'en fin.
            // Ailleurs, elles ne disent rien — le motif est ancré sur la
            // référence entière — et le Core les refuserait.
            if ($char === '^') {
                if ($index === 0) {
                    $output .= '^';
                }

                continue;
            }

            if ($char === '$') {
                if ($index === $length - 1) {
                    $output .= '$';
                }

                continue;
            }

            if (str_contains(self::ALPHABET, $char) || in_array($char, [')', '|', '*', '+', '?'], true)) {
                $output .= $char;

                continue;
            }

            throw PatternRefused::because(
                $applicative,
                sprintf('le caractère « %s » est hors de l\'alphabet d\'une référence du Core (A-Za-z0-9._:-)', $char),
            );
        }

        if ($output === '') {
            throw PatternRefused::because($applicative, 'le motif est vide');
        }

        if (mb_strlen($output) > self::MAX_LENGTH) {
            throw PatternRefused::because(
                $applicative,
                sprintf('le motif dépasse %d caractères', self::MAX_LENGTH),
            );
        }

        // Le Core compile le motif de la même façon : on ne lui envoie rien
        // qui ne compilerait pas chez lui.
        if (@preg_match('~\A(?:'.$output.')\z~', '') === false) {
            throw PatternRefused::because($applicative, 'le motif n\'est pas une expression régulière valide');
        }

        return $output;
    }

    /**
     * L'échappement, traduit.
     *
     * @return string le motif de l'application, ou son équivalent du Core
     */
    private static function escape(string $pattern, int $index, int $length, string $applicative): string
    {
        $escaped = $pattern[$index + 1] ?? '';

        if ($escaped === 'd') {
            return '[0-9]';
        }

        if ($escaped === 'w') {
            return '[A-Za-z0-9]';
        }

        // `\A` et `\z` disent la même chose que `^` et `$` : le Core n'admet
        // ces ancres qu'en tête et en fin ; ailleurs, elles ne disent rien.
        if ($escaped === 'A') {
            return $index === 0 ? '^' : '';
        }

        if ($escaped === 'z' || $escaped === 'Z') {
            return $index + 1 === $length - 1 ? '$' : '';
        }

        if ($escaped === 's') {
            throw PatternRefused::because(
                $applicative,
                'les espaces (\\s) ne font pas partie de l\'alphabet d\'une référence du Core',
            );
        }

        if (in_array($escaped, ['D', 'W', 'S'], true)) {
            throw PatternRefused::because(
                $applicative,
                sprintf('l\'échappement \\%s décrit des caractères hors de l\'alphabet d\'une référence du Core', $escaped),
            );
        }

        // Le Core n'admet que ces quatre échappements : ils sont littéraux.
        if (in_array($escaped, ['.', '-', '_', ':'], true)) {
            return '\\'.$escaped;
        }

        throw PatternRefused::because(
            $applicative,
            sprintf('l\'échappement \\%s n\'est pas admis par le Core', $escaped),
        );
    }

    /**
     * Les délimiteurs et les modificateurs (`/…/i`) retirés.
     *
     * Un motif sans délimiteur est rendu tel quel : c'est déjà un motif nu.
     */
    private static function stripDelimiters(string $applicative): string
    {
        $pattern = trim($applicative);

        if ($pattern === '') {
            return '';
        }

        $first = $pattern[0];

        // Un littéral, une ancre ou un échappement en tête : pas de délimiteur.
        if (ctype_alnum($first) || in_array($first, ['\\', '^', '$', '[', '('], true)) {
            return $pattern;
        }

        $closing = strpos($pattern, $first, 1);

        if ($closing === false) {
            return $pattern;
        }

        $modifiers = substr($pattern, $closing + 1);

        // Ce qui suit le délimiteur doit être un modificateur (i, u, m…) :
        // sinon, ce n'est pas un motif délimité.
        if (! preg_match('/^[a-zA-Z]*$/', $modifiers)) {
            return $pattern;
        }

        return substr($pattern, 1, $closing - 1);
    }

    /**
     * L'index du crochet fermant d'une classe de caractères.
     */
    private static function classEnd(string $pattern, int $start, string $applicative): int
    {
        $length = strlen($pattern);
        $index = $start + 1;

        // Un `]` juste après `[` est un littéral.
        if ($index < $length && $pattern[$index] === ']') {
            $index++;
        }

        while ($index < $length) {
            if ($pattern[$index] === '\\') {
                $index += 2;

                continue;
            }

            if ($pattern[$index] === ']') {
                return $index;
            }

            $index++;
        }

        throw PatternRefused::because($applicative, 'une classe de caractères n\'est pas fermée');
    }

    /**
     * L'index de l'accolade fermante d'un quantificateur.
     *
     * Le quantificateur est lu comme un bloc : `{4}`, `{4,8}`, `{4,}`.
     */
    private static function quantifierEnd(string $pattern, int $start, string $applicative): int
    {
        $length = strlen($pattern);
        $index = $start + 1;
        $digits = 0;

        while ($index < $length && ctype_digit($pattern[$index])) {
            $index++;
            $digits++;
        }

        if ($digits === 0) {
            throw PatternRefused::because($applicative, 'un quantificateur doit porter un nombre');
        }

        if ($index < $length && $pattern[$index] === ',') {
            $index++;

            while ($index < $length && ctype_digit($pattern[$index])) {
                $index++;
            }
        }

        if ($index >= $length || $pattern[$index] !== '}') {
            throw PatternRefused::because($applicative, 'un quantificateur est mal formé');
        }

        return $index;
    }

    /**
     * La classe ne décrit que des caractères de l'alphabet du Core.
     */
    private static function assertClassIsWithinTheAlphabet(string $content, string $applicative): void
    {
        if ($content === '') {
            throw PatternRefused::because($applicative, 'une classe de caractères est vide');
        }

        if (str_starts_with($content, '^')) {
            throw PatternRefused::because(
                $applicative,
                'une classe négative est interdite : elle décrit des caractères hors de l\'alphabet du Core',
            );
        }

        $items = self::classItems($content);

        foreach ($items as $index => $item) {
            if ($item === '-') {
                // Le tiret est l'opérateur de plage : ses deux bornes sont
                // vérifiées par la boucle, au tour suivant.
                continue;
            }

            if (! str_contains(self::ALPHABET, $item)) {
                throw PatternRefused::because(
                    $applicative,
                    sprintf('le caractère « %s » est hors de l\'alphabet d\'une référence du Core', $item),
                );
            }

            $next = $items[$index + 1] ?? null;

            if ($next !== '-') {
                continue;
            }

            $from = $item;
            $to = $items[$index + 2] ?? null;

            if ($to === null || $to === '-') {
                throw PatternRefused::because(
                    $applicative,
                    'un tiret de plage se tient seul dans une classe de caractères',
                );
            }

            if (ord($to) < ord($from)) {
                throw PatternRefused::because($applicative, 'une plage de classe de caractères est à l\'envers');
            }
        }
    }

    /**
     * Les éléments d'une classe : les littéraux, et les tirets de plage.
     *
     * @return list<string>
     */
    private static function classItems(string $content): array
    {
        $items = [];
        $index = 0;
        $length = strlen($content);

        while ($index < $length) {
            $char = $content[$index];

            if ($char === '\\') {
                $items[] = $content[$index + 1] ?? '';
                $index += 2;

                continue;
            }

            $items[] = $char;
            $index++;
        }

        return $items;
    }
}
