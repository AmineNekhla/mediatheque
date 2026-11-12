<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Personne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $nom = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nationalite = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $biographie = null;

    public function getId(): ?int { return $this->id; }

    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(?string $v): static { $this->prenom = $v; return $this; }

    public function getNationalite(): ?string { return $this->nationalite; }
    public function setNationalite(?string $v): static { $this->nationalite = $v; return $this; }

    public function getBiographie(): ?string { return $this->biographie; }
    public function setBiographie(?string $v): static { $this->biographie = $v; return $this; }

    public function __toString(): string { return trim(($this->prenom ?? '') . ' ' . $this->nom); }
}