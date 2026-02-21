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

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $dateDebut = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $dateFin = null;

    #[ORM\Column(length: 255)]
    private ?string $StatutMission = null;

    #[ORM\Column]
    private ?int $prixFinal = null;

    #[ORM\Column(nullable: true)]
    private ?int $Note = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Commentaire = null;

    // Relation ManyToOne vers DemandeAide
    #[ORM\ManyToOne(targetEntity: DemandeAide::class, inversedBy: "missions")]
    #[ORM\JoinColumn(nullable: false)]
    private ?DemandeAide $demandeAide = null;

    // Relation ManyToOne vers AideSoignant
    #[ORM\ManyToOne(targetEntity: AideSoignant::class, inversedBy: "missions")]
    #[ORM\JoinColumn(nullable: true)]
    private ?AideSoignant $aideSoignant = null;

    #[ORM\Column(length: 255)]
    private ?string $TitreM = null;

    #[ORM\Column(nullable: true)]
    private ?float $latitudeCheckin = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitudeCheckin = null;

    #[ORM\Column(nullable: true)]
    private ?float $latitudeCheckout = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitudeCheckout = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $checkInAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $checkOutAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statusVerification = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pdfFilePath = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $proofPhotoData = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $signatureData = null;

    // ================= ARCHIVE & HISTORIQUE =================

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $archivedAt = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $finalStatus = null; // TERMINÉE, EXPIRÉE, ANNULÉE

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $archiveReason = null;

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

    public function setNote(?int $Note): static
    {
        $this->Note = $Note;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->Commentaire;
    }

    public function setCommentaire(?string $Commentaire): static
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

    public function getAideSoignant(): ?AideSoignant
    {
        return $this->aideSoignant;
    }

    public function setAideSoignant(?AideSoignant $aideSoignant): static
    {
        $this->aideSoignant = $aideSoignant;
        return $this;
    }

    public function getTitreM(): ?string
    {
        return $this->TitreM;
    }

    public function setTitreM(string $TitreM): static
    {
        $this->TitreM = $TitreM;

        return $this;
    }

    public function getLatitudeCheckin(): ?float
    {
        return $this->latitudeCheckin;
    }

    public function setLatitudeCheckin(?float $latitudeCheckin): static
    {
        $this->latitudeCheckin = $latitudeCheckin;

        return $this;
    }

    public function getLongitudeCheckin(): ?float
    {
        return $this->longitudeCheckin;
    }

    public function setLongitudeCheckin(?float $longitudeCheckin): static
    {
        $this->longitudeCheckin = $longitudeCheckin;

        return $this;
    }

    public function getLatitudeCheckout(): ?float
    {
        return $this->latitudeCheckout;
    }

    public function setLatitudeCheckout(?float $latitudeCheckout): static
    {
        $this->latitudeCheckout = $latitudeCheckout;

        return $this;
    }

    public function getLongitudeCheckout(): ?float
    {
        return $this->longitudeCheckout;
    }

    public function setLongitudeCheckout(?float $longitudeCheckout): static
    {
        $this->longitudeCheckout = $longitudeCheckout;

        return $this;
    }

    public function getCheckInAt(): ?\DateTime
    {
        return $this->checkInAt;
    }

    public function setCheckInAt(?\DateTime $checkInAt): static
    {
        $this->checkInAt = $checkInAt;

        return $this;
    }

    public function getCheckOutAt(): ?\DateTime
    {
        return $this->checkOutAt;
    }

    public function setCheckOutAt(?\DateTime $checkOutAt): static
    {
        $this->checkOutAt = $checkOutAt;

        return $this;
    }

    public function getStatusVerification(): ?string
    {
        return $this->statusVerification;
    }

    public function setStatusVerification(?string $statusVerification): static
    {
        $this->statusVerification = $statusVerification;

        return $this;
    }

    public function getPdfFilePath(): ?string
    {
        return $this->pdfFilePath;
    }

    public function setPdfFilePath(?string $pdfFilePath): static
    {
        $this->pdfFilePath = $pdfFilePath;

        return $this;
    }

    public function getProofPhotoData(): ?string
    {
        return $this->proofPhotoData;
    }

    public function setProofPhotoData(?string $proofPhotoData): static
    {
        $this->proofPhotoData = $proofPhotoData;

        return $this;
    }

    public function getSignatureData(): ?string
    {
        return $this->signatureData;
    }

    public function setSignatureData(?string $signatureData): static
    {
        $this->signatureData = $signatureData;

        return $this;
    }

    // ================= ARCHIVE & HISTORIQUE =================

    public function getArchivedAt(): ?\DateTime
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?\DateTime $archivedAt): static
    {
        $this->archivedAt = $archivedAt;

        return $this;
    }

    public function getFinalStatus(): ?string
    {
        return $this->finalStatus;
    }

    public function setFinalStatus(?string $finalStatus): static
    {
        $this->finalStatus = $finalStatus;

        return $this;
    }

    public function getArchiveReason(): ?string
    {
        return $this->archiveReason;
    }

    public function setArchiveReason(?string $archiveReason): static
    {
        $this->archiveReason = $archiveReason;

        return $this;
    }
}
