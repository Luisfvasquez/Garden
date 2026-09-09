<?php

declare(strict_types=1);

namespace App\Services\Moderation\Drivers;

use App\Enums\ModerationCategory;
use App\Enums\ModerationDecision;
use App\Enums\ModerationSurface;
use App\Services\Moderation\ContentModerator;
use App\Services\Moderation\ModerationContext;
use App\Services\Moderation\ModerationVerdict;
use App\Services\Moderation\PiiScanner;
use Illuminate\Support\Str;

/**
 * Lexicon + regex moderator. No external calls, fully deterministic — it gates
 * the launch and keeps the test suite hermetic. Config lives in
 * `config/moderation.php` (backend-garden/docs/moderacion.md).
 */
class LocalModerator implements ContentModerator
{
    /**
     * @param  array{thresholds: array{reject: float, flag: float}, categories: array<string, array{weight: float, force: string|null}>, lexicons: array<string, list<string>>}  $config
     */
    public function __construct(
        private readonly PiiScanner $pii,
        private readonly array $config,
    ) {}

    public function check(string $text, ModerationContext $context): ModerationVerdict
    {
        $haystack = $this->normalise($text);

        $score = 0.0;
        $forced = null;
        /** @var array<string, ModerationCategory> $categories keyed by value to dedupe */
        $categories = [];

        foreach ($this->config['lexicons'] as $category => $phrases) {
            $hits = 0;
            foreach ($phrases as $phrase) {
                if ($phrase !== '' && str_contains($haystack, $this->normalise($phrase))) {
                    $hits++;
                }
            }

            if ($hits === 0) {
                continue;
            }

            $rules = $this->config['categories'][$category] ?? ['weight' => 0.4, 'force' => null];
            $score += $rules['weight'] * $hits;
            $categories[$category] = ModerationCategory::from($category);

            $forced = $this->strongerForce($forced, $rules['force']);
        }

        // Contact exchange: block on random letters, flag elsewhere (Doll chat
        // warning is handled by the caller, not here).
        $piiKinds = $this->pii->scan($text);
        if ($piiKinds !== [] && $context->surface !== ModerationSurface::DollChat) {
            $categories[ModerationCategory::Pii->value] = ModerationCategory::Pii;
            $score += 0.4 * count($piiKinds);

            if ($context->surface->blocksContactExchange()) {
                $forced = $this->strongerForce($forced, 'reject');
            } else {
                $forced = $this->strongerForce($forced, 'flag');
            }
        }

        return $this->verdict($score, $forced, array_values($categories));
    }

    /**
     * @param  list<ModerationCategory>  $categories
     */
    private function verdict(float $score, ?string $forced, array $categories): ModerationVerdict
    {
        $score = min(1.0, round($score, 2));

        if ($categories === []) {
            return ModerationVerdict::approved('local');
        }

        $decision = match (true) {
            $forced === 'reject', $score >= $this->config['thresholds']['reject'] => ModerationDecision::Rejected,
            $forced === 'flag', $score >= $this->config['thresholds']['flag'] => ModerationDecision::Flagged,
            default => ModerationDecision::Approved,
        };

        if ($decision === ModerationDecision::Approved) {
            return ModerationVerdict::approved('local');
        }

        return new ModerationVerdict($decision, $categories, $score, 'local');
    }

    private function strongerForce(?string $current, ?string $candidate): ?string
    {
        $rank = ['flag' => 1, 'reject' => 2];

        if ($candidate === null) {
            return $current;
        }

        if ($current === null) {
            return $candidate;
        }

        return ($rank[$candidate] ?? 0) > ($rank[$current] ?? 0) ? $candidate : $current;
    }

    private function normalise(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }
}
