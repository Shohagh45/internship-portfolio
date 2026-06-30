# FAQ Chatbot — Retrieval Core

The retrieval-and-ranking core of an embedding-based FAQ assistant. Given a user
question, it finds the most relevant pre-answered FAQs by **semantic
similarity** and decides — based on confidence — whether to answer, to offer
"did you mean…" suggestions, or to log the question as unanswered.

This module isolates the part that's genuinely interesting and provider-neutral:
the scoring and decision logic. The LLM/embedding calls sit behind a small
interface, so the same core runs against OpenAI, Azure OpenAI, or (here) a fake
deterministic provider for an offline demo.

## The decision flow

1. **Normalize** the question (lowercase, collapse whitespace).
2. **Greeting short-circuit** — a bare "hi"/"hallo" returns a canned greeting
   instead of burning an embedding call.
3. **Embed** the question and score every active FAQ for the tenant by
   **cosine similarity** (embeddings are pre-computed and cached per FAQ, with
   their norm stored, so scoring is a cheap dot product).
4. **Confidence gate:**
   - top score `< threshold` → low confidence;
   - top two scores within `margin` of each other → ambiguous, also low
     confidence.
5. **Outcome:**
   - confident → return the top-`k` FAQs as context for the answer model;
   - low confidence → return the closest questions as **suggestions** and log
     the query as unanswered;
   - nothing scored → log as unanswered.

Multi-tenant (`tenantId`) scoping and bilingual (NL/EN) handling are part of the
original; kept here as parameters.

## Why this design

- **Embeddings are pre-computed**, not computed per request — the only live
  embedding call is for the incoming question, so a tenant with hundreds of FAQs
  still answers in one round-trip.
- **The confidence margin** matters in practice: without it the bot confidently
  answers ambiguous questions with whichever FAQ won by a hair. Returning
  suggestions instead measurably reduced wrong answers.
- **Unanswered logging** turns real misses into a backlog for improving the FAQ
  set.

## Files

- `src/EmbeddingProvider.php` — interface + a deterministic `FakeEmbeddingProvider`
  so the demo runs with no API key.
- `src/FaqRetriever.php` — normalization, cosine scoring, and the confidence
  decision.
- `schema.sql` — the `faqs` and `unanswered_queries` tables (embeddings cached
  as JSON with a stored norm).

## Run the demo

```bash
php examples/demo.php
```

It loads a handful of sample FAQs, then runs a confident question, an ambiguous
one (→ suggestions), and an off-topic one (→ no match), printing the decision
for each.

## Original context

Extracted from a production FAQ chatbot (`chatbot/api/chat.php`). The HTTP
endpoint, CORS, request-id error logging, and OpenAI/Azure transport are omitted
here; this is the scoring/decision core, with the embedding transport replaced
by an interface. No keys, prompts tied to the company, or real FAQ content are
included.
