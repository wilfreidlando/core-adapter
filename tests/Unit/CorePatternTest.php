<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Unit;

use CoreAdapter\Support\CorePattern;
use CoreAdapter\Support\PatternRefused;
use PHPUnit\Framework\TestCase;

/**
 * Le motif de la clé unique, traduit dans le format du Core.
 *
 * L'application écrit un motif `preg` — avec ses délimiteurs, ses `\d` et
 * ses groupes non capturants ; le Core n'accepte qu'un motif nu, dans
 * l'alphabet d'une référence. C'est le même motif, lu par deux lecteurs
 * aux formats incompatibles.
 */
final class CorePatternTest extends TestCase
{
    public function test_the_delimiters_the_modifiers_and_the_non_capturing_groups_are_translated(): void
    {
        self::assertSame(
            '^AE[0-9]{4}([0-9]{2}){0,2}$',
            CorePattern::normalize('/^AE[0-9]{4}(?:[0-9]{2}){0,2}$/'),
        );
    }

    public function test_the_delimiters_and_the_modifiers_are_removed(): void
    {
        self::assertSame('^AE[0-9]{4,8}$', CorePattern::normalize('/^AE[0-9]{4,8}$/i'));
        self::assertSame('^AE[0-9]{4,8}$', CorePattern::normalize('#^AE[0-9]{4,8}$#'));
        self::assertSame('^AE[0-9]{4,8}$', CorePattern::normalize('~^AE[0-9]{4,8}$~'));
    }

    public function test_the_word_anchors_are_translated_into_the_anchors_of_the_core(): void
    {
        self::assertSame('^AE[0-9]{4}$', CorePattern::normalize('/\AAE[0-9]{4}$/'));
        self::assertSame('AE[0-9]{4}$', CorePattern::normalize('/AE[0-9]{4}\z/'));
    }

    public function test_a_bare_pattern_is_left_alone(): void
    {
        self::assertSame('AE[0-9]{4,8}', CorePattern::normalize('AE[0-9]{4,8}'));
    }

    public function test_the_word_classes_become_their_alphabet(): void
    {
        self::assertSame('^[0-9]{4}$', CorePattern::normalize('/^\d{4}$/'));
        self::assertSame('^[A-Za-z0-9]+$', CorePattern::normalize('/^\w+$/'));
    }

    public function test_the_escapes_the_core_admits_are_kept(): void
    {
        self::assertSame('^A\.B$', CorePattern::normalize('/^A\.B$/'));
        self::assertSame('^A\-B$', CorePattern::normalize('/^A\-B$/'));
    }

    public function test_the_anchors_are_kept_where_the_core_admits_them(): void
    {
        self::assertSame('^AE$', CorePattern::normalize('/^AE$/'));
        self::assertSame('AE', CorePattern::normalize('/AE/'));
    }

    public function test_a_spaces_pattern_is_refused_locally(): void
    {
        $this->expectException(PatternRefused::class);
        $this->expectExceptionMessageMatches('/\\\\s/');

        CorePattern::normalize('/^AE\\s[0-9]{4}$/');
    }

    public function test_a_wildcard_is_refused_locally(): void
    {
        $this->expectException(PatternRefused::class);
        $this->expectExceptionMessageMatches('/joker/');

        CorePattern::normalize('/^AE.+$/');
    }

    public function test_a_negative_class_is_refused_locally(): void
    {
        $this->expectException(PatternRefused::class);
        $this->expectExceptionMessageMatches('/négative/');

        CorePattern::normalize('/^[^A-Z]+$/');
    }

    public function test_an_extended_group_is_refused_locally(): void
    {
        $this->expectException(PatternRefused::class);
        $this->expectExceptionMessageMatches('/groupes étendus/');

        CorePattern::normalize('/^(?i)ae[0-9]{4}$/');
    }

    public function test_a_character_outside_the_alphabet_is_refused_locally(): void
    {
        $this->expectException(PatternRefused::class);
        $this->expectExceptionMessageMatches('/hors de l.alphabet/');

        CorePattern::normalize('/^AE[0-9]{4}é$/');
    }

    public function test_the_refusal_says_what_to_do(): void
    {
        try {
            CorePattern::normalize('/^AE[0-9]{4}é$/');
            self::fail('Le motif aurait dû être refusé.');
        } catch (PatternRefused $e) {
            self::assertStringContainsString('ne peut pas être envoyé au Core', $e->getMessage());
            self::assertStringContainsString('motif **nu**', $e->getMessage());
            self::assertStringContainsString('A-Za-z0-9._:-', $e->getMessage());
        }
    }

    public function test_an_empty_pattern_is_refused_locally(): void
    {
        $this->expectException(PatternRefused::class);

        CorePattern::normalize('   ');
    }
}
