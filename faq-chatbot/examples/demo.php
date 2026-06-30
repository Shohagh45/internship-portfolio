<?php

declare(strict_types=1);

/**
 * Runnable demo for the FAQ retrieval core. No API key or database needed —
 * it uses the deterministic FakeEmbeddingProvider.
 *
 *   php examples/demo.php
 */

require __DIR__ . '/../src/EmbeddingProvider.php';
require __DIR__ . '/../src/FaqRetriever.php';

use Portfolio\FaqChatbot\FakeEmbeddingProvider;
use Portfolio\FaqChatbot\FaqRetriever;

$provider = new FakeEmbeddingProvider();

// In production these embeddings come pre-computed from the database. Here we
// embed the sample questions up front to mimic that cache.
$rawFaqs = [
    ['id' => 1, 'question' => 'How do I add a new user?',        'answer' => 'Open Settings > Users and click Add user.'],
    ['id' => 2, 'question' => 'How do I reset my password?',     'answer' => 'Use the "Forgot password" link on the login page.'],
    ['id' => 3, 'question' => 'How do I export a report to Excel?', 'answer' => 'Open the report and click the Excel icon.'],
    ['id' => 4, 'question' => 'How do I change my password?',     'answer' => 'Go to Profile > Security to change your password.'],
];

$faqs = array_map(function (array $faq) use ($provider) {
    $faq['embedding'] = $provider->embed($faq['question']);
    return $faq;
}, $rawFaqs);

// Lower threshold than production (0.80): the fake provider is intentionally
// weak, so realistic absolute scores are lower. The decision logic is identical.
$retriever = new FaqRetriever($provider, threshold: 0.45, margin: 0.05, topK: 3, suggestionLimit: 3);

$questions = [
    'hi',                                  // greeting short-circuit
    'how can I add a user to the system',  // should confidently match FAQ #1
    'how do I change or reset my password',// ambiguous between #2 and #4 -> suggestions
    'what is the capital of France',       // off-topic -> low/no confidence
];

foreach ($questions as $q) {
    $result = $retriever->retrieve($q, $faqs);
    echo "Q: {$q}\n";
    echo "   status   : {$result['status']}\n";
    if ($result['topScore'] !== null) {
        echo '   topScore : ' . number_format($result['topScore'], 3) . "\n";
    }
    if ($result['answerContext'] !== []) {
        $ids = implode(', ', array_map(fn($f) => '#' . $f['id'], $result['answerContext']));
        echo "   answer from FAQ(s): {$ids} -> \"{$result['answerContext'][0]['answer']}\"\n";
    }
    if ($result['suggestions'] !== []) {
        echo "   suggestions:\n";
        foreach ($result['suggestions'] as $s) {
            echo '     - #' . $s['id'] . ' ' . $s['question'] . ' (' . number_format($s['score'], 3) . ")\n";
        }
    }
    echo "\n";
}
