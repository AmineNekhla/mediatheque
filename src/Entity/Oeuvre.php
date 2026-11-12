<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Oeuvre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $titre = '';

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $langueOriginale = null;

    #[ORM\Column(nullable: true)]
    private ?int $anneePremierePublication = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resume = null;

    /** @var Collection<int, Edition> */
    #[ORM\OneToMany(mappedBy: 'oeuvre', targetEntity: Edition::class)]
    private Collection $editions;

    /** @var Collection<int, Contribution> */
    #[ORM\OneToMany(mappedBy: 'oeuvre', targetEntity: Contribution::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $contributions;

    public function __construct()
    {
        $this->editions = new ArrayCollection();
        $this->contributions = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $v): static { $this->titre = $v; return $this; }

    public function getLangueOriginale(): ?string { return $this->langueOriginale; }
    public function setLangueOriginale(?string $v): static { $this->langueOriginale = $v; return $this; }

    public function getAnneePremierePublication(): ?int { return $this->anneePremierePublication; }
    public function setAnneePremierePublication(?int $v): static { $this->anneePremierePublication = $v; return $this; }

    public function getResume(): ?string { return $this->resume; }
    public function setResume(?string $v): static { $this->resume = $v; return $this; }

    /** @return Collection<int, Edition> */
    public function getEditions(): Collection { return $this->editions; }

    public function addEdition(Edition $edition): static
    {
        if (!$this->editions->contains($edition)) {
            $this->editions->add($edition);
            $edition->setOeuvre($this);
        }

        return $this;
    }

    public function removeEdition(Edition $edition): static
    {
        if ($this->editions->removeElement($edition)) {
            if ($edition->getOeuvre() === $this) {
                $edition->setOeuvre(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, Contribution> */
    public function getContributions(): Collection { return $this->contributions; }

    public function addContribution(Contribution $c): static
    {
        if (!$this->contributions->contains($c)) {
            $this->contributions->add($c);
            $c->setOeuvre($this);
        }
        return $this;
    }

    public function removeContribution(Contribution $c): static
    {
        $this->contributions->removeElement($c);
        return $this;
    }

    public function __toString(): string { return $this->titre; }
}