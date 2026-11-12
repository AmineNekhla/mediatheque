<?php

namespace App\Entity;

use App\Enum\StatutPenalite;
use App\Enum\TypePenalite;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Penalite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Adherent $adherent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pret $pret = null;

    #[ORM\Column(length: 20, enumType: TypePenalite::class)]
    private TypePenalite $type = TypePenalite::RETARD;

    // Montant en centimes (entier) : pas d'erreurs d'arrondi.
    #[ORM\Column]
    private int $montantCentimes = 0;

    #[ORM\Column(length: 20, enumType: StatutPenalite::class)]
    private StatutPenalite $statut = StatutPenalite::IMPAYEE;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateCreation = null;

    public function getId(): ?int { return $this->id; }

    public function getAdherent(): ?Adherent { return $this->adherent; }
    public function setAdherent(?Adherent $v): static { $this->adherent = $v; return $this; }

    public function getPret(): ?Pret { return $this->pret; }
    public function setPret(?Pret $v): static { $this->pret = $v; return $this; }

    public function getType(): TypePenalite { return $this->type; }
    public function setType(TypePenalite $v): static { $this->type = $v; return $this; }

    public function getMontantCentimes(): int { return $this->montantCentimes; }
    public function setMontantCentimes(int $v): static { $this->montantCentimes = $v; return $this; }

    public function getStatut(): StatutPenalite { return $this->statut; }
    public function setStatut(StatutPenalite $v): static { $this->statut = $v; return $this; }

    public function getDateCreation(): ?\DateTimeImmutable { return $this->dateCreation; }
    public function setDateCreation(?\DateTimeImmutable $v): static { $this->dateCreation = $v; return $this; }

    public function __toString(): string
    {
        return sprintf('Pénalité #%d (%.2f MAD)', $this->id ?? 0, $this->montantCentimes / 100);
    }
}