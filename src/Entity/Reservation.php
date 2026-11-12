<?php

namespace App\Entity;

use App\Enum\StatutReservation;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Adherent $adherent = null;

    // Œuvre obligatoire : on réserve "le livre"...
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Oeuvre $oeuvre = null;

    // ... et éventuellement une édition précise (null = n'importe quelle édition).
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Edition $edition = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Site $siteRetrait = null;

    // Exemplaire mis de côté quand la réservation passe à DISPONIBLE.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Exemplaire $exemplaireReserve = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateExpirationRetrait = null;

    #[ORM\Column(length: 20, enumType: StatutReservation::class)]
    private StatutReservation $statut = StatutReservation::EN_ATTENTE;

    public function getId(): ?int { return $this->id; }

    public function getAdherent(): ?Adherent { return $this->adherent; }
    public function setAdherent(?Adherent $v): static { $this->adherent = $v; return $this; }

    public function getOeuvre(): ?Oeuvre { return $this->oeuvre; }
    public function setOeuvre(?Oeuvre $v): static { $this->oeuvre = $v; return $this; }

    public function getEdition(): ?Edition { return $this->edition; }
    public function setEdition(?Edition $v): static { $this->edition = $v; return $this; }

    public function getSiteRetrait(): ?Site { return $this->siteRetrait; }
    public function setSiteRetrait(?Site $v): static { $this->siteRetrait = $v; return $this; }

    public function getExemplaireReserve(): ?Exemplaire { return $this->exemplaireReserve; }
    public function setExemplaireReserve(?Exemplaire $v): static { $this->exemplaireReserve = $v; return $this; }

    public function getDateCreation(): ?\DateTimeImmutable { return $this->dateCreation; }
    public function setDateCreation(?\DateTimeImmutable $v): static { $this->dateCreation = $v; return $this; }

    public function getDateExpirationRetrait(): ?\DateTimeImmutable { return $this->dateExpirationRetrait; }
    public function setDateExpirationRetrait(?\DateTimeImmutable $v): static { $this->dateExpirationRetrait = $v; return $this; }

    public function getStatut(): StatutReservation { return $this->statut; }
    public function setStatut(StatutReservation $v): static { $this->statut = $v; return $this; }

    public function __toString(): string
    {
        return sprintf('Réservation #%d (%s)', $this->id ?? 0, $this->oeuvre?->getTitre() ?? '?');
    }
}