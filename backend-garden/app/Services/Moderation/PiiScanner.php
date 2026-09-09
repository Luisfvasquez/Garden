<?php

declare(strict_types=1);

namespace App\Services\Moderation;

/**
 * Spots attempts to move a conversation off-platform: emails, phone numbers,
 * social @handles and URLs. Used to block random letters and to warn both
 * parties in Doll chat (backend-garden/docs/moderacion.md §PII).
 *
 * Deliberately conservative on phone numbers — the product is full of dates
 * ("12 de abril de 2027") and years that look numeric.
 */
class PiiScanner
{
    private const EMAIL = '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i';

    private const URL = '#\b(?:https?://|www\.)[^\s<]+#i';

    // @ followed by a handle-ish token, not an email (no dot-tld right after).
    private const SOCIAL_HANDLE = '/(?<![\w.])@[a-z0-9_]{3,30}\b/i';

    // 9+ digits allowing spaces, dots or dashes, optional leading +.
    private const PHONE = '/(?<!\d)(?:\+\d{1,3}[ .\-]?)?(?:\(?\d{2,4}\)?[ .\-]?){2,4}\d{2,4}(?!\d)/';

    /**
     * @return list<string> the PII kinds found: `email`, `url`, `social_handle`, `phone`
     */
    public function scan(string $text): array
    {
        $found = [];

        if (preg_match(self::EMAIL, $text) === 1) {
            $found[] = 'email';
        }

        if (preg_match(self::URL, $text) === 1) {
            $found[] = 'url';
        }

        if (preg_match(self::SOCIAL_HANDLE, $text) === 1) {
            $found[] = 'social_handle';
        }

        if ($this->looksLikePhone($text)) {
            $found[] = 'phone';
        }

        return $found;
    }

    public function hasPii(string $text): bool
    {
        return $this->scan($text) !== [];
    }

    private function looksLikePhone(string $text): bool
    {
        if (preg_match_all(self::PHONE, $text, $matches) === 0) {
            return false;
        }

        foreach ($matches[0] as $candidate) {
            if (preg_match_all('/\d/', $candidate) >= 9) {
                return true;
            }
        }

        return false;
    }
}
