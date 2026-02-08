<?php

namespace App\EventListener;

use App\Entity\Medecin;
use App\Entity\AideSoignant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Event\AuthenticationEvent;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ValidationCheckListener implements EventSubscriberInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => 'onCheckPassport',
        ];
    }

    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        $user = $passport->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }

        // Check if user is a Medecin and if they are validated
        $medecin = $this->em->getRepository(Medecin::class)->findOneBy(['email' => $user->getEmail()]);
        if ($medecin && !$medecin->isValidated()) {
            throw new AccessDeniedException('Votre compte de médecin est en attente de validation par un administrateur.');
        }

        // Check if user is an AideSoignant and if they are validated
        $aideSoignant = $this->em->getRepository(AideSoignant::class)->findOneBy(['email' => $user->getEmail()]);
        if ($aideSoignant && !$aideSoignant->isValidated()) {
            throw new AccessDeniedException('Votre compte d\'aide-soignant est en attente de validation par un administrateur.');
        }
    }
}
