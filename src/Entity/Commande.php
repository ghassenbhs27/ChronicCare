<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use App\Validator\ValidAddress;
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
    )]
    private string $numeroCommande;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['en_attente', 'validee', 'expediee', 'livree', 'annulee'],
        message: "Le statut '{{ value }}' n'est pas valide. Utilisez: en_attente, validee, expediee, livree, annulee."
    )]
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

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'L adresse ne peut pas depasser {{ limit }} caracteres.'
    )]
    #[ValidAddress]
    private ?string $adresse = null;

    #[ORM\Column(length: 30, options: ['default' => 'card'])]
    #[Assert\Choice(
        choices: ['card', 'cash_on_delivery'],
        message: "Le mode de paiement '{{ value }}' n'est pas valide."
    )]
    private string $methodePaiement = 'card';

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

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): self
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getMethodePaiement(): string
    {
        return $this->methodePaiement;
    }

    public function setMethodePaiement(string $methodePaiement): self
    {
        $this->methodePaiement = $methodePaiement;
        return $this;
    }
}
