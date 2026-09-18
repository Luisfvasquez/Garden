<?php

declare(strict_types=1);

use App\Models\DollRequest;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * Broadcast channels. ADR-0005: the Doll chat is the ONLY real-time channel in
 * the product. Everything else travels as a letter, with its transit time.
 *
 * Whatever is authorised here is checked AGAIN in the message controller
 * (DollChatController) — channel authorisation is a subscription-time snapshot,
 * and a request can close between the handshake and the next POST.
 */

/** Personal notification channel: only ever the user themselves. */
Broadcast::channel('user.{userId}', fn (User $user, string $userId) => $user->id === $userId);

/**
 * The Doll chat. The channel exists only while the request is actually being
 * worked on — `in_progress` or `awaiting_client` (docs/api/dolls.md).
 */
Broadcast::channel('doll-request.{requestId}', function (User $user, string $requestId): bool {
    $request = DollRequest::query()->find($requestId);

    return $request !== null
        && $request->status->isOpenChannel()
        && in_array($user->id, [$request->client_id, $request->doll_id], true);
});
