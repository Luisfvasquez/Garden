<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LetterDelivery;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Two perspectives on a delivery. The sender owns the outbox and tracking; the
 * recipient owns the mailbox, and only once the letter has arrived. Anything
 * else must look like it does not exist (404).
 */
class LetterDeliveryPolicy
{
    public function viewAsSender(User $user, LetterDelivery $delivery): Response
    {
        return $user->id === $delivery->sender_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function cancel(User $user, LetterDelivery $delivery): Response
    {
        return $this->viewAsSender($user, $delivery);
    }

    public function viewInMailbox(User $user, LetterDelivery $delivery): Response
    {
        return $user->id === $delivery->recipient_id && $delivery->status->isInMailbox()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function mutateMailbox(User $user, LetterDelivery $delivery): Response
    {
        return $this->viewInMailbox($user, $delivery);
    }
}
