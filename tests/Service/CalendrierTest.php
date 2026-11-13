<?php

namespace App\Tests\Service;

use App\Service\Calendrier;
use PHPUnit\Framework\TestCase;

class CalendrierTest extends TestCase
{
    public function testSeptJoursOuvresDepuisUnLundi(): void
    {
        $resultat = (new Calendrier())->ajouterJoursOuvres(new \DateTimeImmutable('2026-11-16 10:00'), 7);

        $this->assertSame('2026-11-25', $resultat->format('Y-m-d')); // lundi 16 -> mercredi 25
    }

    public function testLeWeekEndNeCompteJamais(): void
    {
        $resultat = (new Calendrier())->ajouterJoursOuvres(new \DateTimeImmutable('2026-11-20'), 1); // vendredi

        $this->assertSame('2026-11-23', $resultat->format('Y-m-d')); // lundi
    }

    public function testDepuisUnSamediLeProchainJourOuvreEstLeLundi(): void
    {
        $resultat = (new Calendrier())->ajouterJoursOuvres(new \DateTimeImmutable('2026-11-21'), 1); // samedi

        $this->assertSame('2026-11-23', $resultat->format('Y-m-d'));
    }

    public function testZeroJourNeChangeRien(): void
    {
        $date = new \DateTimeImmutable('2026-11-16 10:00');

        $this->assertEquals($date, (new Calendrier())->ajouterJoursOuvres($date, 0));
    }
}