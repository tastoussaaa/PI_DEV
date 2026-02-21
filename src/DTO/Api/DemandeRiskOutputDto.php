<?php

namespace App\DTO\Api;

use Symfony\Component\Serializer\Attribute\Groups;

class DemandeRiskOutputDto
{
    /**
     * @param list<string> $factors
     */
    public function __construct(
        #[Groups(['demande_risk:read'])]
        public readonly int $demandeId,
        #[Groups(['demande_risk:read'])]
        public readonly int $score,
        #[Groups(['demande_risk:read'])]
        public readonly string $level,
        #[Groups(['demande_risk:read'])]
        public readonly array $factors
    ) {
    }
}
