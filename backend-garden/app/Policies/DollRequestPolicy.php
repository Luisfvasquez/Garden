<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DollRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Two perspectives on a request, same shape as `LetterDeliveryPolicy`. Anyone
 * who isn't the client or the Doll on a request must never learn it exists.
 */
class DollRequestPolicy
{
    public function viewAsClient(User $user, DollRequest $request): Response
    {
        return $user->id === $request->client_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function viewAsDoll(User $user, DollRequest $request): Response
    {
        return $user->id === $request->doll_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /** Either side may look at their own request. */
    public function view(User $user, DollRequest $request): Response
    {
        return in_array($user->id, [$request->client_id, $request->doll_id], true)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /** Only the Doll answers a pending request. */
    public function respond(User $user, DollRequest $request): Response
    {
        return $this->viewAsDoll($user, $request);
    }

    /** Either side may back out. */
    public function cancel(User $user, DollRequest $request): Response
    {
        return $this->view($user, $request);
    }

    /**
     * Read or write the chat. Participation only — whether the channel is still
     * OPEN is a separate check (`CHANNEL_CLOSED`, 403) made in the controller,
     * because "you may not look at this" and "this conversation is over" are
     * different answers and the client shows different things for each.
     */
    public function chat(User $user, DollRequest $request): Response
    {
        return $this->view($user, $request);
    }

    /** Only the Doll shares drafts. */
    public function sendDraft(User $user, DollRequest $request): Response
    {
        return $this->viewAsDoll($user, $request);
    }

    /** Only the client approves one — approving is what closes the request. */
    public function approveDraft(User $user, DollRequest $request): Response
    {
        return $this->viewAsClient($user, $request);
    }

    /** Only the client rates, and only their own request. */
    public function rate(User $user, DollRequest $request): Response
    {
        return $this->viewAsClient($user, $request);
    }
}
