<?php

namespace App\Entity;

use App\Repository\EtatRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EtatRepository::class)]
class Etat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    

    #[ORM\ManyToOne(inversedBy: 'etats')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $utilisateur = null;

    

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le type est obligatoire.')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Le type ne peut pas depasser {{ limit }} caracteres.'
    )]
    private string $type;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La valeur est obligatoire.')]
    private ?float $valeur = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'L unite est obligatoire.')]
    #[Assert\Length(
        max: 50,
        maxMessage: 'L unite ne peut pas depasser {{ limit }} caracteres.'
    )]
    private string $unite;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'La date du releve est obligatoire.')]
    private ?\DateTimeInterface $dateReleve = null;

    /**
     * @var Collection<int, Activite>
     */
    #[ORM\OneToMany(targetEntity: Activite::class, mappedBy: 'etat')]
    private Collection $activites;

    public function __construct()
    {
        $this->activites = new ArrayCollection();
    }

   

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): ?User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?User $utilisateur): self
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getValeur(): ?float
    {
        return $this->valeur;
    }

    public function setValeur(?float $valeur): self
    {
        $this->valeur = $valeur;
        return $this;
    }

    public function getUnite(): string
    {
        return $this->unite;
    }

    public function setUnite(string $unite): self
    {
        $this->unite = $unite;
        return $this;
    }

    public function getDateReleve(): ?\DateTimeInterface
    {
        return $this->dateReleve;
    }

    public function setDateReleve(?\DateTimeInterface $dateReleve): self
    {
        $this->dateReleve = $dateReleve;
        return $this;
    }

    /**
     * @return Collection<int, Activite>
     */
    public function getActivites(): Collection
    {
        return $this->activites;
    }

    public function addActivite(Activite $activite): self
    {
        if (!$this->activites->contains($activite)) {
            $this->activites->add($activite);
            $activite->setEtat($this);
        }

        return $this;
    }

    public function removeActivite(Activite $activite): self
    {
        if ($this->activites->removeElement($activite)) {
            if ($activite->getEtat() === $this) {
                $activite->setEtat(null);
            }
        }

        return $this;
    }
}
