<?php

declare(strict_types=1);

namespace Portfolio\FaqChatbot;

/**
 * Retrieval-and-ranking core of an embedding-based FAQ assistant.
 *
 * Given a question it scores candidate FAQs by cosine similarity and returns a
 * decision: a confident answer set, low-confidence suggestions, or no match.
 * The embedding transport is injected, so this class has no external deps.
 *
 * A FAQ candidate is an associative array:
 *   ['id' => int, 'question' => string, 'answer' => string, 'embedding' => float[]]
 * where 'embedding' is the pre-computed vector for the question.
 */
final class FaqRetriever
{
    public function __construct(
        private EmbeddingProvider $embeddings,
        private float $threshold = 0.80,
        private float $margin = 0.05,
        private int $topK = 5,
        private int $suggestionLimit = 3,
    ) {
    }

    /**
     * @param array<int,array<string,mixed>> $faqs Candidate FAQs for the tenant.
     * @return array{status:string,answerContext:array,suggestions:array,topScore:?float}
     */
    public function retrieve(string $question, array $faqs): array
    {
        $normalized = $this->normalize($question);

        if ($this->isGreeting($normalized)) {
            return $this->result('greeting');
        }

        $queryVec  = $this->embeddings->embed($normalized !== '' ? $normalized : $question);
        $queryNorm = $this->vectorNorm($queryVec);

        $scored = $this->scoreFaqs($faqs, $queryVec, $queryNorm);
        if ($scored === []) {
            return $this->result('no_match');
        }

        $topScore    = $scored[0]['score'];
        $secondScore = $scored[1]['score'] ?? null;

        $lowConfidence = $topScore < $this->threshold
            || ($secondScore !== null && ($topScore - $secondScore) < $this->margin);

        if ($lowConfidence) {
            return $this->result('suggestions', [], $this->buildSuggestions($scored), $topScore);
        }

        $context = array_slice(
            array_filter($scored, fn($f) => $f['score'] >= $this->threshold),
            0,
            max(1, $this->topK)
        );

        return $this->result('answered', array_values($context), [], $topScore);
    }

    public function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = trim($text);
        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    private function isGreeting(string $normalized): bool
    {
        if ($normalized === '') {
            return false;
        }
        // Only a bare greeting counts — strip punctuation, then exact-match.
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', '', $normalized) ?? '';
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');

        $greetings = [
            'hi', 'hello', 'hey', 'yo',
            'hoi', 'hallo', 'goedemorgen', 'goedemiddag', 'goedenavond',
            'good morning', 'good afternoon', 'good evening',
        ];
        return in_array($clean, $greetings, true);
    }

    /**
     * @param array<int,array<string,mixed>> $faqs
     * @return array<int,array<string,mixed>> sorted by score desc, each with 'score'
     */
    private function scoreFaqs(array $faqs, array $queryVec, float $queryNorm): array
    {
        $scored = [];
        foreach ($faqs as $faq) {
            $embedding = $faq['embedding'] ?? null;
            if (!is_array($embedding) || $embedding === []) {
                continue;
            }
            $faq['score'] = $this->cosine($queryVec, $queryNorm, $embedding, $this->vectorNorm($embedding));
            $scored[] = $faq;
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return $scored;
    }

    /** @return array<int,array{id:mixed,question:mixed,score:float}> */
    private function buildSuggestions(array $scored): array
    {
        return array_map(
            fn($f) => ['id' => $f['id'], 'question' => $f['question'], 'score' => $f['score']],
            array_slice($scored, 0, max(0, $this->suggestionLimit))
        );
    }

    private function cosine(array $a, float $aNorm, array $b, float $bNorm): float
    {
        if ($aNorm == 0.0 || $bNorm == 0.0) {
            return 0.0;
        }
        $dot   = 0.0;
        $count = min(count($a), count($b));
        for ($i = 0; $i < $count; $i++) {
            $dot += $a[$i] * $b[$i];
        }
        return $dot / ($aNorm * $bNorm);
    }

    private function vectorNorm(array $vector): float
    {
        $sum = 0.0;
        foreach ($vector as $v) {
            $sum += $v * $v;
        }
        return sqrt($sum);
    }

    private function result(string $status, array $answerContext = [], array $suggestions = [], ?float $topScore = null): array
    {
        return [
            'status'        => $status,        // greeting | answered | suggestions | no_match
            'answerContext' => $answerContext, // FAQs to feed the answer model
            'suggestions'   => $suggestions,   // "did you mean ..." list
            'topScore'      => $topScore,
        ];
    }
}
