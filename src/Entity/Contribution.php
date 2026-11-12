<?php

namespace App\Entity;

use App\Enum\RoleContribution;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
class Contribution
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Personne $personne = null;

    #[ORM\ManyToOne(inversedBy: 'contributions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Oeuvre $oeuvre = null;

    #[ORM\ManyToOne(inversedBy: 'contributions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Edition $edition = null;

    #[ORM\Column(length: 30, enumType: RoleContribution::class)]
    private RoleContribution $role = RoleContribution::AUTEUR;

    #[Assert\Callback]
    public function validerCible(ExecutionContextInterface $context): void
    {
        // Exactement une cible : soit une œuvre, soit une édition.
        if (($this->oeuvre === null) === ($this->edition === null)) {
            $context->buildViolation('Une contribution doit être liée à une œuvre OU à une édition (exactement une des deux).')
                ->atPath('oeuvre')
                ->addViolation();
        }
    }

    public function getId(): ?int { return $this->id; }

    public function getPersonne(): ?Personne { return $this->personne; }
    public function setPersonne(?Personne $v): static { $this->personne = $v; return $this; }

    public function getOeuvre(): ?Oeuvre { return $this->oeuvre; }
    public function setOeuvre(?Oeuvre $v): static { $this->oeuvre = $v; return $this; }

    public function getEdition(): ?Edition { return $this->edition; }
    public function setEdition(?Edition $v): static { $this->edition = $v; return $this; }

    public function getRole(): RoleContribution { return $this->role; }
    public function setRole(RoleContribution $v): static { $this->role = $v; return $this; }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->personne?->__toString() ?? '?', $this->role->value);
    }
}