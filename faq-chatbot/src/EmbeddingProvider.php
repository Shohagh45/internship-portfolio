<?php

declare(strict_types=1);

namespace Portfolio\FaqChatbot;

/**
 * Turns text into a vector embedding. In production this wraps OpenAI / Azure
 * OpenAI; the interface keeps the retrieval core independent of the transport.
 */
interface EmbeddingProvider
{
    /** @return float[] */
    public function embed(string $text): array;
}

/**
 * Deterministic, dependency-free embedding for demos and tests. It is NOT a
 * semantic model — it hashes character n-grams into a fixed-size vector so that
 * texts sharing words land near each other under cosine similarity. Good enough
 * to demonstrate the retrieval logic offline; swap in a real provider for use.
 */
final class FakeEmbeddingProvider implements EmbeddingProvider
{
    public function __construct(private int $dimensions = 64)
    {
    }

    public function embed(string $text): array
    {
        $vector = array_fill(0, $this->dimensions, 0.0);
        $tokens = preg_split('/\s+/u', strtolower(trim($text))) ?: [];

        foreach ($tokens as $token) {
            $token = preg_replace('/[^\p{L}\p{N}]/u', '', $token) ?? '';
            if ($token === '') {
                continue;
            }
            // Hash the whole token and its 3-char shingles into buckets.
            $this->accumulate($vector, $token, 1.0);
            $len = mb_strlen($token);
            for ($i = 0; $i + 3 <= $len; $i++) {
                $this->accumulate($vector, mb_substr($token, $i, 3), 0.5);
            }
        }

        return $vector;
    }

    private function accumulate(array &$vector, string $feature, float $weight): void
    {
        $bucket = (int)(hexdec(substr(md5($feature), 0, 8)) % $this->dimensions);
        $vector[$bucket] += $weight;
    }
}
