<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Adherent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Nullable : un adhérent peut exister sans compte en ligne (inscrit au guichet).
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: true, unique: true, onDelete: 'SET NULL')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?FormuleAbonnement $formule = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Site $site = null;

    #[ORM\Column(length: 20, unique: true)]
    private string $numeroCarte = '';

    #[ORM\Column(length: 100)]
    private string $nom = '';

    #[ORM\Column(length: 100)]
    private string $prenom = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $debutAbonnement = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $finAbonnement = null;

    public function getId(): ?int { return $this->id; }

    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $v): static { $this->utilisateur = $v; return $this; }

    public function getFormule(): ?FormuleAbonnement { return $this->formule; }
    public function setFormule(?FormuleAbonnement $v): static { $this->formule = $v; return $this; }

    public function getSite(): ?Site { return $this->site; }
    public function setSite(?Site $v): static { $this->site = $v; return $this; }

    public function getNumeroCarte(): string { return $this->numeroCarte; }
    public function setNumeroCarte(string $v): static { $this->numeroCarte = $v; return $this; }

    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }

    public function getPrenom(): string { return $this->prenom; }
    public function setPrenom(string $v): static { $this->prenom = $v; return $this; }

    public function getDebutAbonnement(): ?\DateTimeImmutable { return $this->debutAbonnement; }
    public function setDebutAbonnement(?\DateTimeImmutable $v): static { $this->debutAbonnement = $v; return $this; }

    public function getFinAbonnement(): ?\DateTimeImmutable { return $this->finAbonnement; }
    public function setFinAbonnement(?\DateTimeImmutable $v): static { $this->finAbonnement = $v; return $this; }

    /** Règle simple, utilisée plus tard par PretService : l'abonnement couvre-t-il cette date ? */
    public function abonnementActifLe(\DateTimeImmutable $date): bool
    {
        return $this->debutAbonnement !== null
            && $this->finAbonnement !== null
            && $date >= $this->debutAbonnement
            && $date <= $this->finAbonnement;
    }

    public function __toString(): string
    {
        return sprintf('%s %s (%s)', $this->prenom, $this->nom, $this->numeroCarte);
    }
}