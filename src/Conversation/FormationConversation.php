<?php
namespace App\Conversation;

use App\Bot\BotServiceLocator;
use App\Repository\FormationRepository;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;

class FormationConversation extends Conversation
{
    public function run(): void
    {
        $this->askCategory();
    }

    public function askCategory(): void
    {
        $this->ask('🔍 Quelle catégorie de formation recherchez-vous ?', function (Answer $answer) {
            $category = trim($answer->getText());

            /** @var FormationRepository $repo */
            $repo = BotServiceLocator::get(FormationRepository::class);
            $formations = $repo->findValidatedByCategory($category);

            if (empty($formations)) {
                $this->say("❌ Aucune formation trouvée pour : *$category*");
            } else {
                $this->say("✅ " . count($formations) . " formation(s) trouvée(s) :");
                foreach ($formations as $f) {
                    $this->say("📌 {$f->getTitle()} ({$f->getCategory()})");
                }
            }

            // Ask again
            $this->askAgain();
        });
    }

    private function askAgain(): void
    {
        $this->ask('🔄 Voulez-vous chercher une autre catégorie ? (oui/non)', function (Answer $answer) {
            $response = strtolower(trim($answer->getText()));

            if (str_contains($response, 'oui') || str_contains($response, 'yes')) {
                $this->askCategory();
            } else {
                $this->say('👋 Merci ! Bonne journée !');
            }
        });
    }
}