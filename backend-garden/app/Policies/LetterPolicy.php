<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Letter;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A letter is private to its author. Someone else's letter must look like it
 * does not exist (404, not 403) — docs/api/_convenciones.md.
 */
class LetterPolicy
{
    public function view(User $user, Letter $letter): Response
    {
        return $user->id === $letter->author_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Letter $letter): Response
    {
        return $this->view($user, $letter);
    }

    public function delete(User $user, Letter $letter): Response
    {
        return $this->view($user, $letter);
    }

    public function send(User $user, Letter $letter): Response
    {
        return $this->view($user, $letter);
    }
}
