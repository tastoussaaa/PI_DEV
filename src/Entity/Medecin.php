<?php

namespace App\Entity;

use App\Repository\MedecinRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MedecinRepository::class)]
class Medecin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $specialite = null;

    #[ORM\Column]
    private ?int $numeroOrdre = null;

    #[ORM\Column]
    private ?int $anneesExperience = null;

    #[ORM\Column]
    private ?bool $disponible = null;

    /**
     * @var Collection<int, Consultation>
     */
    #[ORM\OneToMany(targetEntity: Consultation::class, mappedBy: 'medecin')]
    private Collection $consultations;

    /**
     * @var Collection<int, Formation>
     */
    #[ORM\OneToMany(targetEntity: Formation::class, mappedBy: 'medecin')]
    private Collection $formations;

    /**
     * @var Collection<int, AideSoignant>
     */
    #[ORM\OneToMany(targetEntity: AideSoignant::class, mappedBy: 'medecin')]
    private Collection $aideSoignants;

    public function __construct()
    {
        $this->consultations = new ArrayCollection();
        $this->formations = new ArrayCollection();
        $this->aideSoignants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSpecialite(): ?string
    {
        return $this->specialite;
    }

    public function setSpecialite(string $specialite): static
    {
        $this->specialite = $specialite;

        return $this;
    }

    public function getNumeroOrdre(): ?int
    {
        return $this->numeroOrdre;
    }

    public function setNumeroOrdre(int $numeroOrdre): static
    {
        $this->numeroOrdre = $numeroOrdre;

        return $this;
    }

    public function getAnneesExperience(): ?int
    {
        return $this->anneesExperience;
    }

    public function setAnneesExperience(int $anneesExperience): static
    {
        $this->anneesExperience = $anneesExperience;

        return $this;
    }

    public function isDisponible(): ?bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $disponible): static
    {
        $this->disponible = $disponible;

        return $this;
    }

    /**
     * @return Collection<int, Consultation>
     */
    public function getConsultations(): Collection
    {
        return $this->consultations;
    }

    public function addConsultation(Consultation $consultation): static
    {
        if (!$this->consultations->contains($consultation)) {
            $this->consultations->add($consultation);
            $consultation->setMedecin($this);
        }

        return $this;
    }

    public function removeConsultation(Consultation $consultation): static
    {
        if ($this->consultations->removeElement($consultation)) {
            // set the owning side to null (unless already changed)
            if ($consultation->getMedecin() === $this) {
                $consultation->setMedecin(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Formation>
     */
    public function getFormations(): Collection
    {
        return $this->formations;
    }

    public function addFormation(Formation $formation): static
    {
        if (!$this->formations->contains($formation)) {
            $this->formations->add($formation);
            $formation->setMedecin($this);
        }

        return $this;
    }

    public function removeFormation(Formation $formation): static
    {
        if ($this->formations->removeElement($formation)) {
            // set the owning side to null (unless already changed)
            if ($formation->getMedecin() === $this) {
                $formation->setMedecin(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AideSoignant>
     */
    public function getAideSoignants(): Collection
    {
        return $this->aideSoignants;
    }

    public function addAideSoignant(AideSoignant $aideSoignant): static
    {
        if (!$this->aideSoignants->contains($aideSoignant)) {
            $this->aideSoignants->add($aideSoignant);
            $aideSoignant->setMedecin($this);
        }

        return $this;
    }

    public function removeAideSoignant(AideSoignant $aideSoignant): static
    {
        if ($this->aideSoignants->removeElement($aideSoignant)) {
            // set the owning side to null (unless already changed)
            if ($aideSoignant->getMedecin() === $this) {
                $aideSoignant->setMedecin(null);
            }
        }

        return $this;
    }
}
