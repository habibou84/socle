<?php

namespace Jibiat\Socle\Tests;

use Jibiat\Socle\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public static function validNumbers(): array
    {
        return [
            'local 10 chiffres' => ['0707070707', '+2250707070707'],
            'local avec espaces' => ['07 07 07 07 07', '+2250707070707'],
            'local avec tirets et points' => ['07-07.07-07-07', '+2250707070707'],
            'indicatif sans plus' => ['2250707070707', '+2250707070707'],
            'indicatif avec plus' => ['+225 07 07 07 07 07', '+2250707070707'],
            'préfixe 00' => ['002250707070707', '+2250707070707'],
            'numéro étranger' => ['+33 6 12 34 56 78', '+33612345678'],
        ];
    }

    #[DataProvider('validNumbers')]
    public function test_it_normalizes_numbers_to_e164(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    public static function invalidNumbers(): array
    {
        return [
            'vide' => [''],
            'lettres' => ['abc'],
            'trop court' => ['0707'],
            'local à 8 chiffres sans indicatif' => ['07070707'],
            'trop long' => ['+1234567890123456'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_invalid_numbers(string $input): void
    {
        $this->assertNull(PhoneNumber::normalize($input));
        $this->assertFalse(PhoneNumber::isValid($input));
    }

    public function test_old_ivorian_whatsapp_numbers_match_their_new_number(): void
    {
        $this->assertSame('+2250747123456', PhoneNumber::modernize('+22547123456'));
        $this->assertSame('+2250555123456', PhoneNumber::modernize('+22555123456'));
        $this->assertSame('+2250102123456', PhoneNumber::modernize('+22502123456'));
        $this->assertSame('+2250171123456', PhoneNumber::modernize('+22571123456'));
        $this->assertSame('+2250707123456', PhoneNumber::modernize('+2250707123456'));
        $this->assertSame('+22520123456', PhoneNumber::modernize('+22520123456'));
        $this->assertSame('+33612345678', PhoneNumber::modernize('+33612345678'));

        $this->assertTrue(PhoneNumber::sameNumber('+2250747123456', '+2250747123456'));
        $this->assertTrue(PhoneNumber::sameNumber('0747123456', '+22547123456'));
        $this->assertFalse(PhoneNumber::sameNumber('+2250147123456', '+22547123456'));
        $this->assertFalse(PhoneNumber::sameNumber('+2250747123456', '+2250547123456'));
        $this->assertFalse(PhoneNumber::sameNumber(null, '+22547123456'));
    }
}
