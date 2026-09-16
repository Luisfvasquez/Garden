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
}
