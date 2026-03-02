<?php

namespace App\Service;

class RuleBasedConsultationService
{
    /**
     * @return array{resume: string, conseils: string}
     */
    public function generateSummaryAndAdvice(string $notes, ?string $diagnostic): array
    {
        $cleanNotes = $this->normalizeText($notes);
        $cleanDiag = $this->normalizeText($diagnostic ?? '');

        $resumeParts = [];
        if ($cleanNotes !== '') {
            $resumeParts[] = $this->summarizeText($cleanNotes, 2);
        }
        if ($cleanDiag !== '') {
            $resumeParts[] = 'Diagnostic: ' . $this->truncate($cleanDiag, 140);
        }
        if ($resumeParts === []) {
            $resumeParts[] = 'Consultation realisee. Aucun element detaille n a ete saisi.';
        }
        $resume = implode("\n", $resumeParts);

        $tips = $this->buildAdvice($cleanNotes . ' ' . $cleanDiag);
        $conseils = $this->formatAdvice($tips);

        return [
            'resume' => $resume,
            'conseils' => $conseils,
        ];
    }

    /**
     * @return string[]
     */
    private function buildAdvice(string $text): array
    {
        $text = mb_strtolower($text);
        $tips = [];

        if ($this->containsAny($text, ['diabete', 'glycemie', 'sucre'])) {
            $tips[] = 'Surveiller la glycemie et limiter les sucres rapides.';
        }
        if ($this->containsAny($text, ['hypertension', 'tension', 'hta'])) {
            $tips[] = 'Limiter le sel et mesurer la tension regulierement.';
        }
        if ($this->containsAny($text, ['poids', 'surpoids', 'obesite', 'imc'])) {
            $tips[] = 'Adopter un plan alimentaire equilibre et surveiller le poids.';
        }
        if ($this->containsAny($text, ['cholesterol', 'lipides'])) {
            $tips[] = 'Reduire les graisses saturees et augmenter les fibres.';
        }
        if ($this->containsAny($text, ['stress', 'anxiete', 'angoisse'])) {
            $tips[] = 'Pratiquer des exercices de respiration ou relaxation.';
        }
        if ($this->containsAny($text, ['sommeil', 'insomnie', 'fatigue'])) {
            $tips[] = 'Maintenir une routine de sommeil et limiter les ecrans le soir.';
        }
        if ($this->containsAny($text, ['douleur', 'arthrose', 'articulation'])) {
            $tips[] = 'Favoriser des activites douces et adapter l effort.';
        }
        if ($this->containsAny($text, ['nutrition', 'alimentation', 'regime'])) {
            $tips[] = 'Fractionner les repas et privilegier les aliments peu transformes.';
        }
        if ($this->containsAny($text, ['sport', 'activite', 'exercice'])) {
            $tips[] = 'Garder une activite physique reguliere, 30 minutes par jour si possible.';
        }

        if ($tips === []) {
            $tips = [
                'Boire suffisamment d eau chaque jour.',
                'Maintenir une activite physique adaptee.',
                'Suivre les traitements et revenir en cas de symptomes nouveaux.',
            ];
        }

        return array_slice(array_values(array_unique($tips)), 0, 5);
    }

    private function formatAdvice(array $tips): string
    {
        return implode("\n", array_map(static fn (string $t) => '- ' . $t, $tips));
    }

    private function normalizeText(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text);
        return $text ?? '';
    }

    private function summarizeText(string $text, int $sentences): string
    {
        $parts = preg_split('/(?<=[\.\!\?])\s+/', $text) ?: [$text];
        $slice = array_slice($parts, 0, $sentences);
        $summary = implode(' ', $slice);
        return $this->truncate($summary, 300);
    }

    private function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }
        return false;
    }
}
