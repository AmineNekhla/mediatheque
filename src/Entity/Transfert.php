<?php

namespace App\Entity;

use App\Enum\MotifTransfert;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Transfert
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
    private ?Site $siteOrigine = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Site $siteDestination = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Reservation $reservation = null;

    #[ORM\Column(length: 20, enumType: MotifTransfert::class)]
    private MotifTransfert $motif = MotifTransfert::RESERVATION;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateDemande = null;

    // null tant que la navette n'est pas arrivée
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateReception = null;

    public function getId(): ?int { return $this->id; }

    public function getExemplaire(): ?Exemplaire { return $this->exemplaire; }
    public function setExemplaire(?Exemplaire $v): static { $this->exemplaire = $v; return $this; }

    public function getSiteOrigine(): ?Site { return $this->siteOrigine; }
    public function setSiteOrigine(?Site $v): static { $this->siteOrigine = $v; return $this; }

    public function getSiteDestination(): ?Site { return $this->siteDestination; }
    public function setSiteDestination(?Site $v): static { $this->siteDestination = $v; return $this; }

    public function getReservation(): ?Reservation { return $this->reservation; }
    public function setReservation(?Reservation $v): static { $this->reservation = $v; return $this; }

    public function getMotif(): MotifTransfert { return $this->motif; }
    public function setMotif(MotifTransfert $v): static { $this->motif = $v; return $this; }

    public function getDateDemande(): ?\DateTimeImmutable { return $this->dateDemande; }
    public function setDateDemande(?\DateTimeImmutable $v): static { $this->dateDemande = $v; return $this; }

    public function getDateReception(): ?\DateTimeImmutable { return $this->dateReception; }
    public function setDateReception(?\DateTimeImmutable $v): static { $this->dateReception = $v; return $this; }

    public function __toString(): string
    {
        return sprintf('Transfert #%d', $this->id ?? 0);
    }
}