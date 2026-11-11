<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Site
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $nom = '';

    #[ORM\Column(length: 255)]
    private string $adresse = '';

    public function getId(): ?int { return $this->id; }

    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }

    public function getAdresse(): string { return $this->adresse; }
    public function setAdresse(string $v): static { $this->adresse = $v; return $this; }

    public function __toString(): string { return $this->nom; }
}