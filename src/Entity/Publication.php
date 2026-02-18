<?php

namespace App\Entity;

use App\Repository\PublicationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PublicationRepository::class)]
class Publication
{
    public const CATEGORIES = ['nutrition', 'exercice', 'sante_mentale', 'bien_etre', 'autre'];
    public const STATUT_BROUILLON = 'brouillon';
    public const STATUT_PUBLIE = 'publie';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'publications')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $auteur = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caracteres.',
        maxMessage: 'Le titre ne peut pas depasser {{ limit }} caracteres.'
    )]
    private string $titre;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le contenu est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 10000,
        minMessage: 'Le contenu doit contenir au moins {{ limit }} caracteres.',
        maxMessage: 'Le contenu ne peut pas depasser {{ limit }} caracteres.'
    )]
    private string $contenu;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Choice(
        choices: self::CATEGORIES,
        message: 'La categorie selectionnee est invalide.'
    )]
    private ?string $categorie = null;

    #[ORM\Column]
    private int $nbVues = 0;

    #[ORM\Column]
    private int $nbLikes = 0;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: [self::STATUT_BROUILLON, self::STATUT_PUBLIE],
        message: 'Le statut selectionne est invalide.'
    )]
    private string $statut;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Commentaire>
     */
    #[ORM\OneToMany(targetEntity: Commentaire::class, mappedBy: 'publication', orphanRemoval: true)]
    private Collection $commentaires;

    /**
     * @var Collection<int, PublicationLike>
     */
    #[ORM\OneToMany(targetEntity: PublicationLike::class, mappedBy: 'publication', orphanRemoval: true)]
    private Collection $likes;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->statut = self::STATUT_PUBLIE;
        $this->commentaires = new ArrayCollection();
        $this->likes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuteur(): ?User
    {
        return $this->auteur;
    }

    public function setAuteur(?User $auteur): self
    {
        $this->auteur = $auteur;
        return $this;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;
        return $this;
    }

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): self
    {
        $this->contenu = $contenu;
        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): self
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getNbVues(): int
    {
        return $this->nbVues;
    }

    public function incrementVues(): void
    {
        $this->nbVues++;
    }

    public function getNbLikes(): int
    {
        return $this->nbLikes;
    }

    public function incrementLikes(): void
    {
        $this->nbLikes++;
    }

    public function setNbVues(int $nbVues): self
    {
        $this->nbVues = $nbVues;
        return $this;
    }

    public function setNbLikes(int $nbLikes): self
    {
        $this->nbLikes = $nbLikes;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Commentaire>
     */
    public function getCommentaires(): Collection
    {
        return $this->commentaires;
    }

    public function addCommentaire(Commentaire $commentaire): static
    {
        if (!$this->commentaires->contains($commentaire)) {
            $this->commentaires->add($commentaire);
            $commentaire->setPublication($this);
        }

        return $this;
    }

    public function removeCommentaire(Commentaire $commentaire): static
    {
        $this->commentaires->removeElement($commentaire);

        return $this;
    }

    /**
     * @return Collection<int, PublicationLike>
     */
    public function getLikes(): Collection
    {
        return $this->likes;
    }

    public function isLikedByUser(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        foreach ($this->likes as $like) {
            if ($like->getUser() === $user) {
                return true;
            }
        }

        return false;
    }
}
