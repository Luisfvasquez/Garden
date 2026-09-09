<?php

declare(strict_types=1);

namespace App\Jobs\Maintenance;

use App\Models\ScheduledPush;
use App\Models\User;
use App\Services\Push\WebPushClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Every minute: send the due `scheduled_pushes`, collapsing several of the same
 * `type` for one user into a single notification (docs/api/comunidad-notificaciones.md).
 * Quiet-hours rows simply become due when their window ends.
 */
class DispatchDuePushesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    public function handle(WebPushClient $client): void
    {
        ScheduledPush::query()
            ->due()
            ->orderBy('deliver_after')
            ->get()
            ->groupBy(fn (ScheduledPush $p): string => $p->user_id.'|'.$p->type)
            ->each(function (Collection $group) use ($client): void {
                /** @var Collection<int, ScheduledPush> $group */
                $first = $group->first();
                $user = User::with('pushSubscriptions')->find($first->user_id);

                if ($user !== null) {
                    $payload = $this->payloadFor($group);
                    foreach ($user->pushSubscriptions as $subscription) {
                        if (! $client->send($subscription, $payload)) {
                            $subscription->delete();
                        }
                    }
                }

                ScheduledPush::whereKey($group->pluck('id'))->update(['sent_at' => now()]);
            });
    }

    /**
     * @param  Collection<int, ScheduledPush>  $group
     */
    private function payloadFor(Collection $group): string
    {
        $first = $group->first();

        if ($group->count() === 1) {
            return (string) json_encode([
                'title' => $first->title,
                'body' => $first->body,
                'data' => $first->data ?? [],
            ]);
        }

        return (string) json_encode([
            'title' => $first->title,
            'body' => $group->count().' novedades nuevas.',
            'data' => ['url' => $first->data['url'] ?? '/escritorio', 'count' => $group->count()],
        ]);
    }
}
