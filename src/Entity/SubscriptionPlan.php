<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class SubscriptionPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $name = '';

    #[ORM\Column]
    private int $maxLoans = 3;

    #[ORM\Column]
    private int $loanDays = 21;

    #[ORM\Column]
    private int $maxRenewals = 1;

    #[ORM\Column]
    private int $maxReservations = 2;

    #[ORM\Column]
    private int $lateFeeCentsPerDay = 50;

    #[ORM\Column]
    private int $priceCents = 0;

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }

    public function getMaxLoans(): int { return $this->maxLoans; }
    public function setMaxLoans(int $v): static { $this->maxLoans = $v; return $this; }

    public function getLoanDays(): int { return $this->loanDays; }
    public function setLoanDays(int $v): static { $this->loanDays = $v; return $this; }

    public function getMaxRenewals(): int { return $this->maxRenewals; }
    public function setMaxRenewals(int $v): static { $this->maxRenewals = $v; return $this; }

    public function getMaxReservations(): int { return $this->maxReservations; }
    public function setMaxReservations(int $v): static { $this->maxReservations = $v; return $this; }

    public function getLateFeeCentsPerDay(): int { return $this->lateFeeCentsPerDay; }
    public function setLateFeeCentsPerDay(int $v): static { $this->lateFeeCentsPerDay = $v; return $this; }

    public function getPriceCents(): int { return $this->priceCents; }
    public function setPriceCents(int $v): static { $this->priceCents = $v; return $this; }

    public function __toString(): string { return $this->name; }
}