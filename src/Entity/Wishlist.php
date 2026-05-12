<?php

namespace App\Entity;

use App\Repository\WishlistRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WishlistRepository::class)]
#[ORM\Table(name: 'wishlist', uniqueConstraints: [new ORM\UniqueConstraint(name: 'unique_wishlist', columns: ['utilisateur_id', 'produit_id'])])]
class Wishlist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $utilisateur;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Produit $produit;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateAjout;

    public function __construct()
    {
        $this->dateAjout = new \DateTimeImmutable();
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

    public function getProduit(): Produit
    {
        return $this->produit;
    }

    public function setProduit(Produit $produit): self
    {
        $this->produit = $produit;
        return $this;
    }

    public function getDateAjout(): \DateTimeImmutable
    {
        return $this->dateAjout;
    }

    public function setDateAjout(\DateTimeImmutable $dateAjout): self
    {
        $this->dateAjout = $dateAjout;
        return $this;
    }
}
