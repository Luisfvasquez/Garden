<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LetterSchedule;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A schedule is private to its owner. Someone else's must look like it does not
 * exist — 404, not 403 (docs/api/_convenciones.md).
 */
class LetterSchedulePolicy
{
    public function view(User $user, LetterSchedule $schedule): Response
    {
        return $user->id === $schedule->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, LetterSchedule $schedule): Response
    {
        return $this->view($user, $schedule);
    }

    public function delete(User $user, LetterSchedule $schedule): Response
    {
        return $this->view($user, $schedule);
    }
}
