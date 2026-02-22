<?php

namespace App\Entity;

use App\Repository\ExerciceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExerciceRepository::class)]
class Exercice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'exercices')]
    #[ORM\JoinColumn(nullable: true)]
    #[Assert\NotNull(message: 'L evenement est obligatoire.')]
    private ?Evenement $evenement = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caracteres.',
        max: 200,
        maxMessage: 'Le nom ne peut pas depasser {{ limit }} caracteres.'
    )]
    private string $nom;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        min: 10,
        minMessage: 'La description doit contenir au moins {{ limit }} caracteres.',
        max: 5000,
        maxMessage: 'La description ne peut pas depasser {{ limit }} caracteres.'
    )]
    private string $description;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La duree est obligatoire.')]
    #[Assert\Range(
        min: 1,
        max: 300,
        notInRangeMessage: 'La duree doit etre comprise entre {{ min }} et {{ max }} minutes.'
    )]
    private int $duree;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(
        min: 0,
        max: 1000,
        notInRangeMessage: 'Le nombre de repetitions doit etre compris entre {{ min }} et {{ max }}.'
    )]
    private ?int $repetitions = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): self
    {
        $this->evenement = $evenement;
        return $this;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDuree(): int
    {
        return $this->duree;
    }

    public function setDuree(int $duree): self
    {
        $this->duree = $duree;
        return $this;
    }

    public function getRepetitions(): ?int
    {
        return $this->repetitions;
    }

    public function setRepetitions(?int $repetitions): self
    {
        $this->repetitions = $repetitions;
        return $this;
    }
}
