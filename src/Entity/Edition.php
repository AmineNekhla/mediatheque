<?php

namespace App\Entity;

use App\Enum\SourceEdition;
use App\Enum\TypeDocument;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Edition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'editions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Oeuvre $oeuvre = null;

    #[ORM\Column(length: 20, unique: true, nullable: true)]
    private ?string $isbn = null;

    #[ORM\Column(length: 20, enumType: TypeDocument::class)]
    private TypeDocument $typeDocument = TypeDocument::LIVRE;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $editeur = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $langue = null;

    #[ORM\Column(nullable: true)]
    private ?int $anneePublication = null;

    #[ORM\Column(nullable: true)]
    private ?int $nombrePages = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $urlCouverture = null;

    #[ORM\Column(length: 20, enumType: SourceEdition::class)]
    private SourceEdition $source = SourceEdition::MANUELLE;

    #[ORM\Column(options: ['default' => false])]
    private bool $aVerifier = false;

    /** @var Collection<int, Contribution> */
    #[ORM\OneToMany(mappedBy: 'edition', targetEntity: Contribution::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $contributions;

    /** @var Collection<int, Exemplaire> */
    #[ORM\OneToMany(mappedBy: 'edition', targetEntity: Exemplaire::class)]
    private Collection $exemplaires;

    public function __construct()
    {
        $this->contributions = new ArrayCollection();
        $this->exemplaires = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getOeuvre(): ?Oeuvre { return $this->oeuvre; }
    public function setOeuvre(?Oeuvre $v): static { $this->oeuvre = $v; return $this; }

    public function getIsbn(): ?string { return $this->isbn; }
    public function setIsbn(?string $v): static { $this->isbn = $v; return $this; }

    public function getTypeDocument(): TypeDocument { return $this->typeDocument; }
    public function setTypeDocument(TypeDocument $v): static { $this->typeDocument = $v; return $this; }

    public function getEditeur(): ?string { return $this->editeur; }
    public function setEditeur(?string $v): static { $this->editeur = $v; return $this; }

    public function getLangue(): ?string { return $this->langue; }
    public function setLangue(?string $v): static { $this->langue = $v; return $this; }

    public function getAnneePublication(): ?int { return $this->anneePublication; }
    public function setAnneePublication(?int $v): static { $this->anneePublication = $v; return $this; }

    public function getNombrePages(): ?int { return $this->nombrePages; }
    public function setNombrePages(?int $v): static { $this->nombrePages = $v; return $this; }

    public function getUrlCouverture(): ?string { return $this->urlCouverture; }
    public function setUrlCouverture(?string $v): static { $this->urlCouverture = $v; return $this; }

    public function getSource(): SourceEdition { return $this->source; }
    public function setSource(SourceEdition $v): static { $this->source = $v; return $this; }

    public function isAVerifier(): bool { return $this->aVerifier; }
    public function setAVerifier(bool $v): static { $this->aVerifier = $v; return $this; }

    /** @return Collection<int, Contribution> */
    public function getContributions(): Collection { return $this->contributions; }

    public function addContribution(Contribution $c): static
    {
        if (!$this->contributions->contains($c)) {
            $this->contributions->add($c);
            $c->setEdition($this);
        }
        return $this;
    }

    public function removeContribution(Contribution $c): static
    {
        $this->contributions->removeElement($c);
        return $this;
    }

    /** @return Collection<int, Exemplaire> */
    public function getExemplaires(): Collection { return $this->exemplaires; }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->oeuvre?->getTitre() ?? '?', $this->editeur ?? $this->typeDocument->value);
    }
}