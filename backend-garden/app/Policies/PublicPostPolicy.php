<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PublicPost;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Anyone may read a published, public post. Editing and deleting belong to the
 * author; a draft/held/pending post is invisible to everyone else (404).
 */
class PublicPostPolicy
{
    public function view(?User $user, PublicPost $post): Response
    {
        if ($post->isPublished() && $post->visibility->value === 'public') {
            return Response::allow();
        }

        return $user !== null && $user->id === $post->author_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, PublicPost $post): Response
    {
        return $user->id === $post->author_id ? Response::allow() : Response::denyAsNotFound();
    }

    public function delete(User $user, PublicPost $post): Response
    {
        return $this->update($user, $post);
    }
}
