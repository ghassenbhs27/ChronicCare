<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[UniqueEntity(fields: ['numeroCommande'], message: 'Ce numero de commande existe deja.')]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L utilisateur est obligatoire.')]
    private User $utilisateur;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Le numero de commande est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: 'Le numero doit contenir au moins 3 caracteres.',
        maxMessage: 'Le numero de commande ne peut pas depasser {{ limit }} caracteres.'
    )]
    #[Assert\Regex(
        pattern: "/^[A-Z0-9\-]+$/",
        message: "Le numero de commande doit contenir uniquement des lettres majuscules, chiffres et tirets."
    )]
    private string $numeroCommande;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['en_attente', 'validee', 'expediee', 'livree', 'annulee'],
        message: "Le statut '{{ value }}' n'est pas valide. Utilisez: en_attente, validee, expediee, livree, annulee."
    )]
    private string $statut;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le total est obligatoire.')]
    #[Assert\Type(type: 'numeric', message: 'Le total doit etre un nombre valide.')]
    #[Assert\GreaterThan(value: 0, message: 'Le total doit etre superieur a 0.')]
    #[Assert\LessThanOrEqual(value: 99999.99, message: 'Le total ne peut pas depasser 99999,99.')]
    private string $total;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de creation est obligatoire.')]
    #[Assert\Type("DateTimeInterface")]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $region = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $timezone = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $isp = null;

    #[ORM\Column(length: 30, options: ['default' => 'card'])]
    #[Assert\Choice(
        choices: ['card', 'cash', 'cash_on_delivery', 'stripe'],
        message: "Le mode de paiement '{{ value }}' n'est pas valide."
    )]
    private string $methodePaiement = 'card';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motifAnnulation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeSessionId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    /**
     * @var Collection<int, LigneCommande>
     */
    #[ORM\OneToMany(targetEntity: LigneCommande::class, mappedBy: 'commande', orphanRemoval: true, cascade: ['persist'])]
    #[Assert\Valid]
    #[Assert\Count(min: 1, minMessage: 'La commande doit contenir au moins une ligne de commande.')]
    private Collection $ligneCommandes;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->statut = 'en_attente';
        $this->ligneCommandes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(User $utilisateur): self
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    public function getNumeroCommande(): string
    {
        return $this->numeroCommande;
    }

    public function setNumeroCommande(string $numeroCommande): self
    {
        $this->numeroCommande = $numeroCommande;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string $total): self
    {
        $this->total = $total;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, LigneCommande>
     */
    public function getLigneCommandes(): Collection
    {
        return $this->ligneCommandes;
    }

    public function addLigneCommande(LigneCommande $ligneCommande): static
    {
        if (!$this->ligneCommandes->contains($ligneCommande)) {
            $this->ligneCommandes->add($ligneCommande);
            $ligneCommande->setCommande($this);
        }

        return $this;
    }

    public function removeLigneCommande(LigneCommande $ligneCommande): static
    {
        $this->ligneCommandes->removeElement($ligneCommande);

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): self
    {
        $this->region = $region;
        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function getIsp(): ?string
    {
        return $this->isp;
    }

    public function setIsp(?string $isp): self
    {
        $this->isp = $isp;
        return $this;
    }

    /**
     * Retourne l'adresse formatée pour l'affichage
     */
    public function getAdresse(): ?string
    {
        if (!$this->city && !$this->country) {
            return null;
        }
        $parts = [];
        if ($this->region) {
            $parts[] = $this->region;
        }
        if ($this->city) {
            $parts[] = $this->city;
        }
        if ($this->country) {
            $parts[] = $this->country;
        }
        return implode(', ', $parts);
    }

    public function getMethodePaiement(): string
    {
        return $this->methodePaiement;
    }

    public function setMethodePaiement(string $methodePaiement): self
    {
        $this->methodePaiement = $methodePaiement === 'cash_on_delivery' ? 'cash' : $methodePaiement;
        return $this;
    }

    public function getStripeSessionId(): ?string
    {
        return $this->stripeSessionId;
    }

    public function setStripeSessionId(?string $stripeSessionId): self
    {
        $this->stripeSessionId = $stripeSessionId;
        return $this;
    }

    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
    }

    public function setStripePaymentIntentId(?string $stripePaymentIntentId): self
    {
        $this->stripePaymentIntentId = $stripePaymentIntentId;
        return $this;
    }

    public function getMotifAnnulation(): ?string
    {
        return $this->motifAnnulation;
    }

    public function setMotifAnnulation(?string $motifAnnulation): self
    {
        $this->motifAnnulation = $motifAnnulation;
        return $this;
    }
}
