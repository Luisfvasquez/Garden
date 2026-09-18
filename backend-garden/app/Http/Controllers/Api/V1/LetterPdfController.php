<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Services\Postal\LetterPdfRenderer;
use App\Support\SenderView;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF export (docs/api/cartas.md). Two doors, because a letter has two owners:
 * the person who wrote it and the person who received it.
 */
class LetterPdfController extends Controller
{
    public function __construct(private readonly LetterPdfRenderer $renderer) {}

    /**
     * GET /api/v1/letters/{letter}/pdf — the author exports their own copy.
     */
    public function mine(Letter $letter): Response
    {
        $this->authorize('view', $letter);

        // The author knows who they are; a "from" line would just be noise.
        return $this->stream($letter, null);
    }

    /**
     * GET /api/v1/mailbox/{delivery}/pdf — the recipient exports what arrived.
     */
    public function received(LetterDelivery $delivery): Response
    {
        // Same gate as opening it in the mailbox: never `in_transit`, never
        // someone else's (docs/api/entregas-buzon.md).
        $this->authorize('viewInMailbox', $delivery);

        $delivery->loadMissing(['letter', 'sender']);

        // Anonymity is resolved exactly once, here, by the same helper the API
        // resources use. A PDF must not become the one surface that leaks a
        // sender the mailbox is still hiding.
        $senderLine = SenderView::isHidden($delivery)
            ? null
            : 'De '.SenderView::summary($delivery)['display_name'];

        return $this->stream($delivery->letter, $senderLine);
    }

    private function stream(Letter $letter, ?string $senderLine): Response
    {
        $pdf = $this->renderer->render($letter, $senderLine);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->renderer->filename($letter).'"',
            // A letter is private: never let a shared cache hold on to it.
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
