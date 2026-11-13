<?php

namespace App\Tests\Entity;

use App\Entity\Adherent;
use PHPUnit\Framework\TestCase;

class AdherentTest extends TestCase
{
    private function adherent(): Adherent
    {
        return (new Adherent())
            ->setDebutAbonnement(new \DateTimeImmutable('2026-01-01'))
            ->setFinAbonnement(new \DateTimeImmutable('2026-12-31')); // minuit, comme en base
    }

    public function testActifLeDernierJourMemeEnJournee(): void
    {
        // Régression : comparer 31/12 à 10h avec 31/12 à minuit refusait à tort ce jour-là.
        $this->assertTrue($this->adherent()->abonnementActifLe(new \DateTimeImmutable('2026-12-31 10:00')));
    }

    public function testActifPendantLAbonnement(): void
    {
        $this->assertTrue($this->adherent()->abonnementActifLe(new \DateTimeImmutable('2026-06-15 14:30')));
    }

    public function testInactifAvantEtApres(): void
    {
        $this->assertFalse($this->adherent()->abonnementActifLe(new \DateTimeImmutable('2025-12-31 23:59')));
        $this->assertFalse($this->adherent()->abonnementActifLe(new \DateTimeImmutable('2027-01-01 00:00')));
    }
}