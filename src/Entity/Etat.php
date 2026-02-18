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
    public const TEMPERATURE_CORPORELLE_CHOICES = [
        '35.0',
        '35.5',
        '36.0',
        '36.5',
        '37.0',
        '37.5',
        '38.0',
        '38.5',
        '39.0',
        '39.5',
        '40.0',
    ];

    public const NIVEAU_HYDRATATION_CHOICES = [
        '1.0',
        '1.5',
        '2.0',
        '2.5',
        '3.0',
        '3.5',
        '4.0',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    

    #[ORM\ManyToOne(inversedBy: 'etats')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $utilisateur = null;

    

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Le traitement ne peut pas depasser {{ limit }} caracteres.')]
    private ?string $traitementEnCours = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000, maxMessage: 'Les remarques cliniques ne peuvent pas depasser {{ limit }} caracteres.')]
    private ?string $remarquesCliniques = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Choice(choices: self::TEMPERATURE_CORPORELLE_CHOICES, message: 'Temperature corporelle invalide.')]
    private ?string $temperatureCorporelle = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Choice(choices: self::NIVEAU_HYDRATATION_CHOICES, message: 'Niveau d hydratation invalide.')]
    private ?string $niveauHydratation = null;

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

    public function getTraitementEnCours(): ?string
    {
        return $this->traitementEnCours;
    }

    public function setTraitementEnCours(?string $traitementEnCours): self
    {
        $this->traitementEnCours = $traitementEnCours;
        return $this;
    }

    public function getRemarquesCliniques(): ?string
    {
        return $this->remarquesCliniques;
    }

    public function setRemarquesCliniques(?string $remarquesCliniques): self
    {
        $this->remarquesCliniques = $remarquesCliniques;
        return $this;
    }

    public function getTemperatureCorporelle(): ?string
    {
        return $this->temperatureCorporelle;
    }

    public function setTemperatureCorporelle(?string $temperatureCorporelle): self
    {
        $this->temperatureCorporelle = $temperatureCorporelle;
        return $this;
    }

    public function getNiveauHydratation(): ?string
    {
        return $this->niveauHydratation;
    }

    public function setNiveauHydratation(?string $niveauHydratation): self
    {
        $this->niveauHydratation = $niveauHydratation;
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
