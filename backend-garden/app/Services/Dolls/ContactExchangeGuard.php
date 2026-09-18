<?php

declare(strict_types=1);

namespace App\Services\Dolls;

use App\Enums\DollChatMessageType;
use App\Enums\ModerationActionSource;
use App\Enums\ModerationActionType;
use App\Enums\ModerationSurface;
use App\Models\DollChatMessage;
use App\Models\DollRequest;
use App\Models\ModerationAction;
use App\Services\Moderation\PiiScanner;
use Illuminate\Support\Facades\Log;

/**
 * Anti contact-exchange filter for the Doll chat
 * (docs/api/dolls.md § Salvaguardas, backend-garden/docs/moderacion.md § PII).
 *
 * It **warns, it does not block** — unlike random letters, where the same
 * patterns are rejected outright. Two reasons:
 *
 *  - A brief legitimately contains things that look like PII. "Escríbele a mi
 *    hermano, el del 91 de la calle Mayor" is the job, not an evasion.
 *  - Blocking silently teaches people to obfuscate. A visible warning to both
 *    parties is what actually protects them — and it protects the product rule
 *    that conversation lives here, not on WhatsApp (ADR-0005).
 *
 * The warning is a `system` message in the transcript, so it survives in the
 * history both parties read, and it is recorded as a `warn` moderation action.
 */
class ContactExchangeGuard
{
    public function __construct(private readonly PiiScanner $pii) {}

    /**
     * Scans an outgoing message. Returns the PII kinds found (empty when clean)
     * so the caller can stamp them on the message it is about to store.
     *
     * @return list<string>
     */
    public function inspect(string $body): array
    {
        return ModerationSurface::DollChat->warnsOnContactExchange()
            ? $this->pii->scan($body)
            : [];
    }

    /**
     * Records the warning: a `system` line both parties see, plus an audit row.
     * Called only when `inspect()` came back non-empty.
     *
     * @param  list<string>  $flags
     */
    public function warn(DollRequest $request, DollChatMessage $message, array $flags): DollChatMessage
    {
        $warning = new DollChatMessage([
            'type' => DollChatMessageType::System,
            'body' => $this->warningText($flags),
            'pii_flags' => $flags,
        ]);
        $warning->doll_request_id = $request->getKey();
        $warning->sender_id = null; // the platform speaks, not a person
        $warning->save();

        if ($message->sender_id !== null) {
            ModerationAction::query()->create([
                'user_id' => $message->sender_id,
                'type' => ModerationActionType::Warn,
                'source' => ModerationActionSource::Filter,
                'reason' => 'contact_exchange_in_doll_chat',
                'context' => [
                    'doll_request_id' => $request->getKey(),
                    'message_id' => $message->getKey(),
                    'flags' => $flags,
                ],
            ]);
        }

        Log::info('Doll chat: posible intercambio de contacto', [
            'doll_request_id' => $request->getKey(),
            'message_id' => $message->getKey(),
            'flags' => $flags,
        ]);

        return $warning;
    }

    /**
     * @param  list<string>  $flags
     */
    private function warningText(array $flags): string
    {
        $names = [
            'email' => 'un correo electrónico',
            'phone' => 'un número de teléfono',
            'social_handle' => 'un usuario de otra red',
            'url' => 'un enlace externo',
        ];

        $found = array_values(array_filter(array_map(
            fn (string $flag) => $names[$flag] ?? null,
            $flags,
        )));

        $what = $found === [] ? 'datos de contacto' : implode(', ', $found);

        return 'Este mensaje parece incluir '.$what.'. Toda la conversación de esta solicitud '
            .'ocurre aquí: llevarla fuera os deja a ambos sin el respaldo de Evergarden.';
    }
}
