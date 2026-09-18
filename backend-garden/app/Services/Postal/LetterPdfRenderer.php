<?php

declare(strict_types=1);

namespace App\Services\Postal;

use App\Models\Letter;
use App\Support\TiptapContent;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Exports a letter to PDF keeping its chosen aesthetic (docs/api/cartas.md).
 * "Muy solicitado": people want to keep, print and frame what they were sent.
 *
 * Deliberately Dompdf and not `spatie/laravel-pdf`, which the spec suggested:
 * that one drives a headless Chromium, which means Node + a browser on every
 * machine that renders a PDF. This runs in pure PHP. The cost is weaker CSS,
 * which a letter — paper colour, ink colour, a serif face, an optional frame —
 * can afford. See ADR-0015.
 *
 * Fidelity limit worth knowing: the catalogue's webfonts (Cormorant, EB
 * Garamond, Lora) are not shipped as files in the repo, so the PDF falls back
 * to Dompdf's bundled serif. Ink, paper and frame do carry over. Dropping the
 * TTFs into `storage/fonts` and registering them is the upgrade path.
 */
class LetterPdfRenderer
{
    /** Paper tones, matched to the catalogue in config/letter_styles.php. */
    private const PAPERS = [
        'parchment' => '#f4ecd8',
        'cream_laid' => '#faf5e9',
        'ivory_smooth' => '#fffff4',
        'kraft' => '#d8c3a5',
    ];

    private const DEFAULT_PAPER = '#faf5e9';

    private const DEFAULT_INK = '#2a231b';

    public function __construct(private readonly LetterStyleCatalog $styles) {}

    /**
     * @param  string|null  $senderLine  what to print as the sender. Pass null
     *                                   for an anonymous letter — the caller
     *                                   decides, this class never looks it up.
     */
    public function render(Letter $letter, ?string $senderLine = null): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);   // no network fetches from a document body
        $options->set('isJavascriptEnabled', false);
        $options->set('defaultFont', 'serif');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4');
        $dompdf->loadHtml($this->html($letter, $senderLine), 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /** `cartas-de-<quien>-<fecha>.pdf`, safe for a Content-Disposition header. */
    public function filename(Letter $letter): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', (string) ($letter->title ?: 'carta'));
        $slug = trim(strtolower((string) $slug), '-') ?: 'carta';

        return $slug.'-'.$letter->created_at->format('Y-m-d').'.pdf';
    }

    private function html(Letter $letter, ?string $senderLine): string
    {
        $style = $letter->style ?? [];

        $paper = self::PAPERS[$style['paper'] ?? ''] ?? self::DEFAULT_PAPER;
        $ink = $this->inkHex($style['ink'] ?? null);
        $frame = ($style['border'] ?? 'none') !== 'none'
            ? "border: 2px solid {$ink}; padding: 28pt;"
            : 'padding: 28pt;';

        $title = $letter->title !== null && $letter->title !== ''
            ? '<h1>'.$this->escape($letter->title).'</h1>'
            : '';

        $from = $senderLine !== null
            ? '<p class="from">'.$this->escape($senderLine).'</p>'
            : '';

        $body = TiptapContent::toHtml($letter->body ?? []);
        $date = $this->escape($letter->created_at->format('d/m/Y'));

        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head><meta charset="utf-8"></head>
        <body style="margin:0; background: {$paper};">
          <div style="{$frame} color: {$ink}; font-family: serif; font-size: 12pt; line-height: 1.7;">
            <style>
              h1 { font-size: 18pt; font-weight: normal; margin: 0 0 18pt; }
              p { margin: 0 0 10pt; text-align: justify; }
              blockquote { margin: 10pt 0 10pt 18pt; font-style: italic; }
              hr { border: none; border-top: 1px solid {$ink}; margin: 16pt 20%; }
              .meta { font-size: 9pt; text-align: right; margin-bottom: 20pt; }
              .from { font-size: 10pt; margin-top: 24pt; text-align: right; font-style: italic; }
            </style>
            <p class="meta">{$date}</p>
            {$title}
            {$body}
            {$from}
          </div>
        </body>
        </html>
        HTML;
    }

    private function inkHex(?string $key): string
    {
        foreach ($this->styles->all()['inks'] ?? [] as $ink) {
            if (($ink['key'] ?? null) === $key && isset($ink['hex'])) {
                return (string) $ink['hex'];
            }
        }

        return self::DEFAULT_INK;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
