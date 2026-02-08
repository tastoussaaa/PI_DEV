<?php

namespace App\Entity;

use App\Repository\MissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\DemandeAide;

#[ORM\Entity(repositoryClass: MissionRepository::class)]
class Mission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $dateDebut = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $dateFin = null;

    #[ORM\Column(length: 255)]
    private ?string $StatutMission = null;

    #[ORM\Column]
    private ?int $prixFinal = null;

    #[ORM\Column]
    private ?int $Note = null;

    #[ORM\Column(length: 255)]
    private ?string $Commentaire = null;

    // Relation ManyToOne vers DemandeAide
    #[ORM\ManyToOne(targetEntity: DemandeAide::class, inversedBy: "missions")]
    #[ORM\JoinColumn(nullable: false)]
    private ?DemandeAide $demandeAide = null;

    // ================= Getters et Setters =================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateDebut(): ?\DateTime
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTime $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTime
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTime $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getStatutMission(): ?string
    {
        return $this->StatutMission;
    }

    public function setStatutMission(string $StatutMission): static
    {
        $this->StatutMission = $StatutMission;
        return $this;
    }

    public function getPrixFinal(): ?int
    {
        return $this->prixFinal;
    }

    public function setPrixFinal(int $prixFinal): static
    {
        $this->prixFinal = $prixFinal;
        return $this;
    }

    public function getNote(): ?int
    {
        return $this->Note;
    }

    public function setNote(int $Note): static
    {
        $this->Note = $Note;
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->Commentaire;
    }

    public function setCommentaire(string $Commentaire): static
    {
        $this->Commentaire = $Commentaire;
        return $this;
    }

    public function getDemandeAide(): ?DemandeAide
    {
        return $this->demandeAide;
    }

    public function setDemandeAide(?DemandeAide $demandeAide): static
    {
        $this->demandeAide = $demandeAide;
        return $this;
    }
}
