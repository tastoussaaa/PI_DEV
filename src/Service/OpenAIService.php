<?php

namespace App\Service;

use OpenAI;

class OpenAIService
{
    private OpenAI\Client $client;

    public function __construct(string $apiKey)
    {
        $this->client = OpenAI::client($apiKey);
    }

    /**
     * Enhance and structure consultation motif (reason) using OpenAI
     */
    public function enhanceConsultationMotif(string $motif): string
    {
        if (!$motif || strlen(trim($motif)) === 0) {
            return $motif;
        }

        try {
            error_log('OpenAI: Starting enhancement for motif: ' . substr($motif, 0, 100));
            
            $response = $this->client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a medical assistant. Your task is to enhance and structure patient consultation reasons into a clear, professional medical statement. Improve grammar, clarity, and medical relevance. Keep it 1-3 sentences max.'
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Enhance this consultation reason: ' . $motif
                    ]
                ],
                'max_tokens' => 200,
                'temperature' => 0.5,
            ]);

            if (!isset($response['choices']) || empty($response['choices'])) {
                error_log('OpenAI: No choices in response');
                return $motif;
            }

            $choice = $response['choices'][0];
            if (!isset($choice['message']['content'])) {
                error_log('OpenAI: No content in message');
                return $motif;
            }

            $enhanced = trim($choice['message']['content']);
            error_log('OpenAI: Successfully enhanced. Original: "' . $motif . '" => Enhanced: "' . $enhanced . '"');
            
            return !empty($enhanced) ? $enhanced : $motif;
        } catch (\Exception $e) {
            error_log('OpenAI Enhancement Error: ' . $e->getMessage());
            error_log('OpenAI Stack: ' . $e->getTraceAsString());
            return $motif;
        }
    }

    /**
     * Comprehensive analysis of consultation motif
     */
    public function analyzeMotifComprehensive(string $motif): array
    {
        $cleanMotif = trim($motif);
        
        if (!$cleanMotif || strlen($cleanMotif) === 0) {
            return [
                'enhanced' => $motif,
                'urgency' => 'moderee',
                'isValid' => false,
                'message' => 'Veuillez entrer un motif de consultation.'
            ];
        }

        try {
            error_log('OpenAI: Starting comprehensive analysis for motif: ' . substr($motif, 0, 100));
            
            $systemPrompt = 'Tu es assistant médical français. Analyse et retourne JSON seul:
{"enhanced":"...","urgency":"elevee|moderee|faible","isValid":true|false,"message":"..."}
Urgence: elevee=douleur intense/grave, moderee=symptômes persistants, faible=routine.
isValid=false si charabia. Motif: ' . $motif;

            $response = $this->client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ]
                ],
                'max_tokens' => 300,
                'temperature' => 0.3,
            ]);

            $content = $response->choices[0]->message->content;
            error_log('OpenAI comprehensive response: ' . $content);

            $result = json_decode($content, true);
            
            if (!$result || !isset($result['enhanced'])) {
                error_log('OpenAI: Failed to parse JSON response, using fallback');
                return $this->fallbackAnalysis($motif);
            }

            $urgency = isset($result['urgency']) ? strtolower($result['urgency']) : 'moderee';
            if (!in_array($urgency, ['elevee', 'moderee', 'faible'])) {
                $urgency = 'moderee';
            }

            return [
                'enhanced' => $result['enhanced'] ?? $motif,
                'urgency' => $urgency,
                'isValid' => isset($result['isValid']) ? (bool)$result['isValid'] : true,
                'message' => $result['message'] ?? ($result['isValid'] ? 'Motif valide et amélioré.' : 'Veuillez reformuler votre motif.')
            ];

        } catch (\Exception $e) {
            error_log('OpenAI Comprehensive Analysis Error: ' . $e->getMessage());
            return $this->fallbackAnalysis($motif);
        }
    }

    /**
     * Fallback analysis when OpenAI fails
     */
    private function fallbackAnalysis(string $motif): array
    {
        $cleanMotif = trim($motif);
        $motifLower = mb_strtolower($cleanMotif);
        
        if (strlen($cleanMotif) < 3) {
            return [
                'enhanced' => $motif,
                'urgency' => 'moderee',
                'isValid' => false,
                'message' => 'Votre message est trop court. Veuillez décrire vos symptômes plus en détail.'
            ];
        }
        
        $suspiciousPatterns = [
            'abc', 'xyz', '123', '!!!', '???', 'xxx', 'aaa', 
            'bbb', 'ccc', 'ddd', 'eee', 'fff', 'ggg',
            'qwerty', 'asdf', 'zxcv', '111', '222', '333',
            'test', 'testing', 'blah', 'foo', 'bar', 'baz',
            'random', 'nonsense', 'charabia'
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($cleanMotif, $pattern) !== false) {
                return [
                    'enhanced' => $motif,
                    'urgency' => 'moderee',
                    'isValid' => false,
                    'message' => 'Votre message semble incompréhensible. Veuillez entrer un motif de consultation valide.'
                ];
            }
        }
        
        if (preg_match('/(.)\1{2,}/', $cleanMotif)) {
            return [
                'enhanced' => $motif,
                'urgency' => 'moderee',
                'isValid' => false,
                'message' => 'Votre message contient des caractères répétés. Veuillez entrer un motif valide.'
            ];
        }
        
        if (preg_match('/^[0-9]+$/', $cleanMotif)) {
            return [
                'enhanced' => $motif,
                'urgency' => 'moderee',
                'isValid' => false,
                'message' => 'Les chiffres seuls ne sont pas un motif valide. Veuillez décrire vos symptômes.'
            ];
        }

        $urgencyKeywords = [
            'elevee' => [
                'douleur intense', 'forte douleur', 'saignement', 'difficulté respirer', 
                'urgence', 'grave', 'inconscient', 'douleur thoracique', 'accident',
                'crise', 'étouffement', 'convulsion', 'infarctus',
                'brûlure grave', 'fracture', 'hemorragie'
            ],
            'faible' => [
                'check-up', 'bilan', 'routine', 'prévention', 'vaccin', 
                'renouvellement', 'ordonnance', 'certificat', 'visite de contrôle'
            ]
        ];

        $urgency = 'moderee';
        
        foreach ($urgencyKeywords['elevee'] as $keyword) {
            if (strpos($motifLower, $keyword) !== false) {
                $urgency = 'elevee';
                break;
            }
        }
        
        if ($urgency === 'moderee') {
            foreach ($urgencyKeywords['faible'] as $keyword) {
                if (strpos($motifLower, $keyword) !== false) {
                    $urgency = 'faible';
                    break;
                }
            }
        }

        return [
            'enhanced' => $motif,
            'urgency' => $urgency,
            'isValid' => true,
            'message' => 'Motif de consultation enregistré.'
        ];
    }

    /**
     * Generate suggested medical keywords from consultation motif
     */
    public function generateMedicalKeywords(string $motif): array
    {
        try {
            $response = $this->client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a medical assistant. Extract 3-5 relevant medical keywords from the consultation reason. Return only the keywords separated by commas, no explanations.'
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Extract medical keywords from: "' . $motif . '"'
                    ]
                ],
                'max_tokens' => 100,
                'temperature' => 0.5,
            ]);

            $keywords = $response->choices[0]->message->content;
            return array_map('trim', explode(',', $keywords));
        } catch (\Exception $e) {
            error_log('OpenAI Keywords Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Analyze symptom severity from consultation description
     */
    public function analyzeSeverity(string $motif): string
    {
        try {
            $response = $this->client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a medical assistant. Analyze the severity of the symptoms described. Respond with ONLY one word: mild, moderate, or severe.'
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Assess severity: "' . $motif . '"'
                    ]
                ],
                'max_tokens' => 10,
                'temperature' => 0.3,
            ]);

            $severity = strtolower(trim($response->choices[0]->message->content));
            
            if (in_array($severity, ['mild', 'moderate', 'severe'])) {
                return $severity;
            }
            
            return 'moderate';
        } catch (\Exception $e) {
            error_log('OpenAI Severity Analysis Error: ' . $e->getMessage());
            return 'moderate';
        }
    }
}
