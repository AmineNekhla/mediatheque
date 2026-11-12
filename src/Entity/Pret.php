<?php

namespace App\Entity;

use App\Enum\StatutPret;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Pret
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Exemplaire $exemplaire = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Adherent $adherent = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateEmprunt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateRetourPrevue = null;

    // null tant que le document n'est pas rendu
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateRetour = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $nombreProlongations = 0;

    #[ORM\Column(length: 20, enumType: StatutPret::class)]
    private StatutPret $statut = StatutPret::EN_COURS;

    public function getId(): ?int { return $this->id; }

    public function getExemplaire(): ?Exemplaire { return $this->exemplaire; }
    public function setExemplaire(?Exemplaire $v): static { $this->exemplaire = $v; return $this; }

    public function getAdherent(): ?Adherent { return $this->adherent; }
    public function setAdherent(?Adherent $v): static { $this->adherent = $v; return $this; }

    public function getDateEmprunt(): ?\DateTimeImmutable { return $this->dateEmprunt; }
    public function setDateEmprunt(?\DateTimeImmutable $v): static { $this->dateEmprunt = $v; return $this; }

    public function getDateRetourPrevue(): ?\DateTimeImmutable { return $this->dateRetourPrevue; }
    public function setDateRetourPrevue(?\DateTimeImmutable $v): static { $this->dateRetourPrevue = $v; return $this; }

    public function getDateRetour(): ?\DateTimeImmutable { return $this->dateRetour; }
    public function setDateRetour(?\DateTimeImmutable $v): static { $this->dateRetour = $v; return $this; }

    public function getNombreProlongations(): int { return $this->nombreProlongations; }
    public function setNombreProlongations(int $v): static { $this->nombreProlongations = $v; return $this; }

    public function getStatut(): StatutPret { return $this->statut; }
    public function setStatut(StatutPret $v): static { $this->statut = $v; return $this; }

    /** Un prêt non rendu dont l'échéance est dépassée à la date donnée est en retard. */
    public function estEnRetardLe(\DateTimeImmutable $date): bool
    {
        return $this->dateRetour === null
            && $this->dateRetourPrevue !== null
            && $this->dateRetourPrevue < $date;
    }

    public function __toString(): string
    {
        return sprintf('Prêt #%d', $this->id ?? 0);
    }
}