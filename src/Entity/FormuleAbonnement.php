<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FormuleAbonnement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $nom = '';

    #[ORM\Column]
    private int $pretsMax = 3;

    #[ORM\Column]
    private int $dureePretJours = 21;

    #[ORM\Column]
    private int $prolongationsMax = 1;

    #[ORM\Column]
    private int $reservationsMax = 2;

    #[ORM\Column]
    private int $penaliteCentimesParJour = 50;

    #[ORM\Column]
    private int $prixCentimes = 0;

    public function getId(): ?int { return $this->id; }

    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }

    public function getPretsMax(): int { return $this->pretsMax; }
    public function setPretsMax(int $v): static { $this->pretsMax = $v; return $this; }

    public function getDureePretJours(): int { return $this->dureePretJours; }
    public function setDureePretJours(int $v): static { $this->dureePretJours = $v; return $this; }

    public function getProlongationsMax(): int { return $this->prolongationsMax; }
    public function setProlongationsMax(int $v): static { $this->prolongationsMax = $v; return $this; }

    public function getReservationsMax(): int { return $this->reservationsMax; }
    public function setReservationsMax(int $v): static { $this->reservationsMax = $v; return $this; }

    public function getPenaliteCentimesParJour(): int { return $this->penaliteCentimesParJour; }
    public function setPenaliteCentimesParJour(int $v): static { $this->penaliteCentimesParJour = $v; return $this; }

    public function getPrixCentimes(): int { return $this->prixCentimes; }
    public function setPrixCentimes(int $v): static { $this->prixCentimes = $v; return $this; }

    public function __toString(): string { return $this->nom; }
}