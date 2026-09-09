<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A comment can be removed by its author or by the author of the post it is on
 * (docs/api/blog.md).
 */
class CommentPolicy
{
    public function delete(User $user, Comment $comment): Response
    {
        return $user->id === $comment->author_id || $user->id === $comment->post->author_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
