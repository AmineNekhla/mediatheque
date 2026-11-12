<?php

namespace App\Entity;

use App\Enum\StatutExemplaire;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Exemplaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'exemplaires')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Edition $edition = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Site $siteAttache = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Site $siteActuel = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $codeBarres = '';

    #[ORM\Column(length: 20, enumType: StatutExemplaire::class)]
    private StatutExemplaire $statut = StatutExemplaire::DISPONIBLE;

    // Verrou optimiste : Doctrine incrémente cette valeur à chaque UPDATE
    // et lève une OptimisticLockException si quelqu'un d'autre l'a modifiée entre-temps.
    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    public function getId(): ?int { return $this->id; }

    public function getEdition(): ?Edition { return $this->edition; }
    public function setEdition(?Edition $v): static { $this->edition = $v; return $this; }

    public function getSiteAttache(): ?Site { return $this->siteAttache; }
    public function setSiteAttache(?Site $v): static { $this->siteAttache = $v; return $this; }

    public function getSiteActuel(): ?Site { return $this->siteActuel; }
    public function setSiteActuel(?Site $v): static { $this->siteActuel = $v; return $this; }

    public function getCodeBarres(): string { return $this->codeBarres; }
    public function setCodeBarres(string $v): static { $this->codeBarres = $v; return $this; }

    public function getStatut(): StatutExemplaire { return $this->statut; }
    public function setStatut(StatutExemplaire $v): static { $this->statut = $v; return $this; }

    public function getVersion(): int { return $this->version; }

    public function __toString(): string
    {
        return sprintf('%s [%s]', $this->codeBarres, $this->edition?->getOeuvre()?->getTitre() ?? '?');
    }
}