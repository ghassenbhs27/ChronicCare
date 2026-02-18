<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'publication_view', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'unique_user_publication_view', columns: ['user_id', 'publication_id'])
])]
class PublicationView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Publication $publication;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $viewedAt;

    public function __construct(User $user, Publication $publication)
    {
        $this->user = $user;
        $this->publication = $publication;
        $this->viewedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPublication(): Publication
    {
        return $this->publication;
    }

    public function getViewedAt(): \DateTimeImmutable
    {
        return $this->viewedAt;
    }
}
