<?php
// src/Bot/BotRepositoryRegistry.php
namespace App\Bot;

use App\Repository\FormationRepository;

class BotRepositoryRegistry
{
    public function __construct(
        private FormationRepository $formationRepository
    ) {}

    public function getFormationRepository(): FormationRepository
    {
        return $this->formationRepository;
    }
}