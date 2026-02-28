<?php
namespace App\Controller;

use App\Conversation\FormationConversation;
use BotMan\BotMan\BotMan;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BotManController extends AbstractController
{
    #[Route('/botman/frame', name: 'botman_frame', methods: ['GET'])]
    public function frame(): Response
    {
        return $this->render('botman/frame.html.twig');
    }

    // Remove stateless: true — use ob_start to capture output cleanly instead
    #[Route('/botman', name: 'botman', methods: ['GET', 'POST'])]
    public function handle(BotMan $botman): Response
    {
        $botman->hears('.*', function (BotMan $bot) {
            $bot->startConversation(new FormationConversation());
        });

        ob_start();
        $botman->listen();
        $content = ob_get_clean();

        // Strip anything after the JSON (warnings, HTML injected by profiler)
        $content = $this->extractJson($content);

        return new Response($content, 200, ['Content-Type' => 'application/json']);
    }

    #[Route('/chat', name: 'chat', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('chat_bot/index.html.twig');
    }

    private function extractJson(string $content): string
    {
        // Find where valid JSON ends and strip everything after it
        $start = strpos($content, '{');
        if ($start === false) {
            return $content;
        }

        $depth = 0;
        $end = $start;
        $len = strlen($content);

        for ($i = $start; $i < $len; $i++) {
            if ($content[$i] === '{') $depth++;
            if ($content[$i] === '}') $depth--;
            if ($depth === 0) {
                $end = $i;
                break;
            }
        }

        return substr($content, $start, $end - $start + 1);
    }
}