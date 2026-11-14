<?php

namespace App\Tests\Service;

use App\Service\OpenLibrary\IsbnValidateur;
use PHPUnit\Framework\TestCase;

class IsbnValidateurTest extends TestCase
{
    public function testNormaliseUnIsbn13AvecTirets(): void
    {
        $this->assertSame('9780140328721', IsbnValidateur::normaliser('978-0-14-032872-1'));
    }

    public function testAccepteUnIsbn10(): void
    {
        $this->assertSame('0140328726', IsbnValidateur::normaliser('0-14-032872-6'));
    }

    public function testAccepteUnIsbn10TerminePar_X_enMinuscule(): void
    {
        $this->assertSame('080442957X', IsbnValidateur::normaliser('080442957x'));
    }

    public function testRefuseUneCleDeControleIncorrecte(): void
    {
        $this->assertNull(IsbnValidateur::normaliser('9780140328722'));
        $this->assertNull(IsbnValidateur::normaliser('0140328727'));
    }

    public function testRefuseUnFormatInvalide(): void
    {
        $this->assertNull(IsbnValidateur::normaliser(''));
        $this->assertNull(IsbnValidateur::normaliser('abc'));
        $this->assertNull(IsbnValidateur::normaliser('12345'));
        $this->assertNull(IsbnValidateur::normaliser('97801403287211'));
    }
}
