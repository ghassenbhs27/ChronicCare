<?php
namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'], message: 'Il existe déjà un compte avec cet email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const APPROVAL_PENDING = 'pending';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: "/^(\+33|0)[1-9](\d{8})$/",
        message: "Le téléphone doit être au format français valide (ex: 06 12 34 56 78 ou +33612345678)."
    )]
    #[Assert\Length(
        min: 10,
        max: 20,
        minMessage: "Le numéro de téléphone doit contenir au moins 10 chiffres.",
        maxMessage: "Le numéro de téléphone ne peut pas dépasser 20 chiffres."
    )]
    private ?string $telephone = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "L'email '{{ value }}' n'est pas valide.")]
    private ?string $email = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "Le nom est obligatoire.")]
    #[Assert\Length(min: 2, max: 50, minMessage: "Le nom doit contenir au moins 2 caractères.", maxMessage: "Le nom ne peut pas dépasser 50 caractères.")]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "Le prénom est obligatoire.")]
    #[Assert\Length(min: 2, max: 50, minMessage: "Le prénom doit contenir au moins 2 caractères.", maxMessage: "Le prénom ne peut pas dépasser 50 caractères.")]
    private ?string $prenom = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank(message: "Le genre est obligatoire.")]
    #[Assert\Choice(choices: ['Homme', 'Femme'], message: "Le genre doit être 'Homme' ou 'Femme'.")]
    private ?string $genre = null;

    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     * ✅ PAS de contraintes ici car le champ est mapped => false dans le formulaire
     * La validation se fait dans UserType.php
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isActive = false;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $requestedRole = null;

    #[ORM\Column(length: 20, options: ['default' => self::APPROVAL_PENDING])]
    private string $approvalStatus = self::APPROVAL_PENDING;

    #[ORM\Column(length: 64, nullable: true, unique: true)]
    private ?string $verificationToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $verificationTokenExpiresAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photoProfil = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profileImage = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $securityBlockedUntil = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $medicalCondition = null;

    #[ORM\Column(nullable: true)]
    private ?int $activityScore = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isMostActive = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $activityBadge = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $activityReward = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $activityBadgeAwardedAt = null;

    /**
     * @var Collection<int, Evenement>
     */
    #[ORM\OneToMany(targetEntity: Evenement::class, mappedBy: 'coach')]
    private Collection $evenements;

    /**
     * @var Collection<int, Etat>
     */
    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: Etat::class, orphanRemoval: true)]
    private Collection $etats;

    /**
     * @var Collection<int, Activite>
     */
    #[ORM\OneToMany(targetEntity: Activite::class, mappedBy: 'utilisateur', orphanRemoval: true)]
    private Collection $activites;

    /**
     * @var Collection<int, Commande>
     */
    #[ORM\OneToMany(targetEntity: Commande::class, mappedBy: 'utilisateur')]
    private Collection $commandes;

    /**
     * @var Collection<int, Publication>
     */
    #[ORM\OneToMany(targetEntity: Publication::class, mappedBy: 'auteur')]
    private Collection $publications;

    /**
     * Profil médical du patient pour les recommandations personnalisées
     */
    #[ORM\OneToOne(mappedBy: 'utilisateur', targetEntity: ProfilMedical::class, cascade: ['persist', 'remove'])]
    private ?ProfilMedical $profilMedical = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->evenements = new ArrayCollection();
        $this->etats = new ArrayCollection();
        $this->activites = new ArrayCollection();
        $this->commandes = new ArrayCollection();
        $this->publications = new ArrayCollection();
    }

    /**
     * @return Collection<int, Etat>
     */
    public function getEtats(): Collection
    {
        return $this->etats;
    }

    public function addEtat(Etat $etat): static
    {
        if (!$this->etats->contains($etat)) {
            $this->etats->add($etat);
            $etat->setUtilisateur($this);
        }
        return $this;
    }

    public function removeEtat(Etat $etat): static
    {
        $this->etats->removeElement($etat);
        return $this;
    }

    /**
     * @return Collection<int, Activite>
     */
    public function getActivites(): Collection
    {
        return $this->activites;
    }

    public function addActivite(Activite $activite): static
    {
        if (!$this->activites->contains($activite)) {
            $this->activites->add($activite);
            $activite->setUtilisateur($this);
        }
        return $this;
    }

    public function removeActivite(Activite $activite): static
    {
        $this->activites->removeElement($activite);
        return $this;
    }

    /**
     * @return Collection<int, Commande>
     */
    public function getCommandes(): Collection
    {
        return $this->commandes;
    }

    public function addCommande(Commande $commande): static
    {
        if (!$this->commandes->contains($commande)) {
            $this->commandes->add($commande);
            $commande->setUtilisateur($this);
        }
        return $this;
    }

    public function removeCommande(Commande $commande): static
    {
        $this->commandes->removeElement($commande);
        return $this;
    }

    /**
     * @return Collection<int, Publication>
     */
    public function getPublications(): Collection
    {
        return $this->publications;
    }

    public function addPublication(Publication $publication): static
    {
        if (!$this->publications->contains($publication)) {
            $this->publications->add($publication);
            $publication->setAuteur($this);
        }
        return $this;
    }

    public function removePublication(Publication $publication): static
    {
        $this->publications->removeElement($publication);
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): self
    {
        $this->telephone = $telephone;
        return $this;
    }

    /**
     * @return Collection<int, Evenement>
     */
    public function getEvenements(): Collection
    {
        return $this->evenements;
    }

    public function addEvenement(Evenement $evenement): static
    {
        if (!$this->evenements->contains($evenement)) {
            $this->evenements->add($evenement);
            $evenement->setCoach($this);
        }
        return $this;
    }

    public function removeEvenement(Evenement $evenement): static
    {
        $this->evenements->removeElement($evenement);
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function setGenre(string $genre): self
    {
        $this->genre = $genre;
        return $this;
    }

    public function getProfileImage(): ?string
    {
        return $this->profileImage;
    }

    public function setProfileImage(?string $profileImage): self
    {
        $this->profileImage = $profileImage;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        if ($roles !== []) {
            $roles[] = 'ROLE_USER';
        }
        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password ?? '';
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getFullName(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    public function getVerificationToken(): ?string
    {
        return $this->verificationToken;
    }

    public function setVerificationToken(?string $verificationToken): self
    {
        $this->verificationToken = $verificationToken;
        return $this;
    }

    public function getVerificationTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->verificationTokenExpiresAt;
    }

    public function setVerificationTokenExpiresAt(?\DateTimeImmutable $verificationTokenExpiresAt): self
    {
        $this->verificationTokenExpiresAt = $verificationTokenExpiresAt;
        return $this;
    }

    public function getPhotoProfil(): ?string
    {
        return $this->photoProfil;
    }

    public function setPhotoProfil(?string $photoProfil): self
    {
        $this->photoProfil = $photoProfil;
        return $this;
    }

    public function getRequestedRole(): ?string
    {
        return $this->requestedRole;
    }

    public function setRequestedRole(?string $requestedRole): self
    {
        $this->requestedRole = $requestedRole;
        return $this;
    }

    public function getApprovalStatus(): string
    {
        return $this->approvalStatus;
    }

    public function setApprovalStatus(string $approvalStatus): self
    {
        $this->approvalStatus = $approvalStatus;
        return $this;
    }

    public function isPendingApproval(): bool
    {
        return $this->approvalStatus === self::APPROVAL_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->approvalStatus === self::APPROVAL_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->approvalStatus === self::APPROVAL_REJECTED;
    }

    /**
     * Profil médical pour recommandations personnalisées
     */
    public function getProfilMedical(): ?ProfilMedical
    {
        return $this->profilMedical;
    }

    public function setProfilMedical(?ProfilMedical $profilMedical): self
    {
        // Unset the owning side of the relation if necessary
        if ($profilMedical === null && $this->profilMedical !== null) {
            $this->profilMedical->setUtilisateur(null);
        }

        // Set the owning side of the relation if necessary
        if ($profilMedical !== null && $profilMedical->getUtilisateur() !== $this) {
            $profilMedical->setUtilisateur($this);
        }

        $this->profilMedical = $profilMedical;
        return $this;
    }

    /**
     * Vérifie si l'utilisateur a un profil médical renseigné
     */
    public function hasProfilMedical(): bool
    {
        return $this->profilMedical !== null;
    }

    /**
     * Vérifie si le compte est temporairement bloqué
     */
    public function isTemporarilyBlocked(): bool
    {
        if ($this->securityBlockedUntil === null) {
            return false;
        }
        return $this->securityBlockedUntil > new \DateTimeImmutable();
    }

    public function getSecurityBlockedUntil(): ?\DateTimeImmutable
    {
        return $this->securityBlockedUntil;
    }

    public function setSecurityBlockedUntil(?\DateTimeImmutable $securityBlockedUntil): self
    {
        $this->securityBlockedUntil = $securityBlockedUntil;
        return $this;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): self
    {
        $this->lastSeenAt = $lastSeenAt;
        return $this;
    }

    /**
     * Vérifie si l'utilisateur est très actif (pour badge)
     * TODO: Implémenter la logique basée sur les publications/commentaires
     */
    public function isMostActive(): bool
    {
        return $this->isMostActive ?? false;
    }

    public function setIsMostActive(?bool $isMostActive): self
    {
        $this->isMostActive = $isMostActive;
        return $this;
    }

    public function getActivityScore(): ?int
    {
        return $this->activityScore;
    }

    public function setActivityScore(?int $activityScore): self
    {
        $this->activityScore = $activityScore;
        return $this;
    }

    public function getActivityBadge(): ?string
    {
        return $this->activityBadge;
    }

    public function setActivityBadge(?string $activityBadge): self
    {
        $this->activityBadge = $activityBadge;
        return $this;
    }

    public function getActivityReward(): ?string
    {
        return $this->activityReward;
    }

    public function setActivityReward(?string $activityReward): self
    {
        $this->activityReward = $activityReward;
        return $this;
    }

    public function getActivityBadgeAwardedAt(): ?\DateTimeImmutable
    {
        return $this->activityBadgeAwardedAt;
    }

    public function setActivityBadgeAwardedAt(?\DateTimeImmutable $activityBadgeAwardedAt): self
    {
        $this->activityBadgeAwardedAt = $activityBadgeAwardedAt;
        return $this;
    }

    public function getMedicalCondition(): ?string
    {
        return $this->medicalCondition;
    }

    public function setMedicalCondition(?string $medicalCondition): self
    {
        $this->medicalCondition = $medicalCondition;
        return $this;
    }
}
