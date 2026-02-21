<?php

namespace App\Entity;

use App\Repository\CommentaireRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommentaireRepository::class)]
class Commentaire
{
    // Font style constants
    public const FONT_STYLE_NORMAL = 'normal';
    public const FONT_STYLE_BOLD = 'bold';
    public const FONT_STYLE_ITALIC = 'italic';
    public const FONT_STYLE_UNDERLINE = 'underline';
    public const FONT_STYLE_STRIKETHROUGH = 'strikethrough';
    
    public const FONT_STYLES = [
        self::FONT_STYLE_NORMAL,
        self::FONT_STYLE_BOLD,
        self::FONT_STYLE_ITALIC,
        self::FONT_STYLE_UNDERLINE,
        self::FONT_STYLE_STRIKETHROUGH,
    ];

    // Font size constants
    public const FONT_SIZE_SMALL = 'small';
    public const FONT_SIZE_MEDIUM = 'medium';
    public const FONT_SIZE_LARGE = 'large';
    
    public const FONT_SIZES = [
        self::FONT_SIZE_SMALL,
        self::FONT_SIZE_MEDIUM,
        self::FONT_SIZE_LARGE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'commentaires')]
    #[ORM\JoinColumn(nullable: false)]
    private Publication $publication;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $auteur = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le commentaire ne peut pas etre vide.')]
    #[Assert\Length(
        min: 3,
        max: 3000,
        minMessage: 'Le commentaire doit contenir au moins {{ limit }} caracteres.',
        maxMessage: 'Le commentaire ne peut pas depasser {{ limit }} caracteres.'
    )]
    private string $contenu;

    #[ORM\Column]
    private bool $isAnonymous = false;

    #[ORM\Column]
    private int $likeCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $formattedText = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(choices: Commentaire::FONT_STYLES)]
    private ?string $fontStyle = self::FONT_STYLE_NORMAL;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Choice(choices: Commentaire::FONT_SIZES)]
    private ?string $fontSize = self::FONT_SIZE_MEDIUM;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $textColor = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $backgroundColor = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $emojis = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublication(): Publication
    {
        return $this->publication;
    }

    public function setPublication(Publication $publication): self
    {
        $this->publication = $publication;
        return $this;
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

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): self
    {
        $this->contenu = $contenu;
        return $this;
    }

    public function isAnonymous(): bool
    {
        return $this->isAnonymous;
    }

    public function setIsAnonymous(bool $isAnonymous): self
    {
        $this->isAnonymous = $isAnonymous;
        return $this;
    }

    public function getLikeCount(): int
    {
        return $this->likeCount;
    }

    public function incrementLikes(): void
    {
        $this->likeCount++;
    }

    public function decrementLikes(): self
    {
        if ($this->likeCount > 0) {
            $this->likeCount--;
        }
        return $this;
    }

    public function getNomAuteur(): string
    {
        if ($this->isAnonymous) {
            return 'Anonyme';
        }
        if (!$this->auteur) {
            return 'Utilisateur inconnu';
        }
        return trim($this->auteur->getNom() . ' ' . $this->auteur->getPrenom());
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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

    public function getFormattedText(): ?string
    {
        return $this->formattedText;
    }

    public function setFormattedText(?string $formattedText): self
    {
        $this->formattedText = $formattedText;
        return $this;
    }

    public function getFontStyle(): ?string
    {
        return $this->fontStyle ?? self::FONT_STYLE_NORMAL;
    }

    public function setFontStyle(?string $fontStyle): self
    {
        if ($fontStyle && !in_array($fontStyle, self::FONT_STYLES, true)) {
            throw new \InvalidArgumentException('Invalid font style');
        }
        $this->fontStyle = $fontStyle ?? self::FONT_STYLE_NORMAL;
        return $this;
    }

    public function getFontSize(): ?string
    {
        return $this->fontSize ?? self::FONT_SIZE_MEDIUM;
    }

    public function setFontSize(?string $fontSize): self
    {
        if ($fontSize && !in_array($fontSize, self::FONT_SIZES, true)) {
            throw new \InvalidArgumentException('Invalid font size');
        }
        $this->fontSize = $fontSize ?? self::FONT_SIZE_MEDIUM;
        return $this;
    }

    public function getTextColor(): ?string
    {
        return $this->textColor;
    }

    public function setTextColor(?string $textColor): self
    {
        $this->textColor = $textColor;
        return $this;
    }

    public function getBackgroundColor(): ?string
    {
        return $this->backgroundColor;
    }

    public function setBackgroundColor(?string $backgroundColor): self
    {
        $this->backgroundColor = $backgroundColor;
        return $this;
    }

    public function getEmojis(): ?string
    {
        return $this->emojis;
    }

    public function setEmojis(?string $emojis): self
    {
        $this->emojis = $emojis;
        return $this;
    }

    public function hasRichContent(): bool
    {
        return $this->formattedText !== null
            || $this->fontStyle !== self::FONT_STYLE_NORMAL
            || $this->fontSize !== self::FONT_SIZE_MEDIUM
            || $this->textColor !== null
            || $this->backgroundColor !== null
            || $this->emojis !== null;
    }
}
