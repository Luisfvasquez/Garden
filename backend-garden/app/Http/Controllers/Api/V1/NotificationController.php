<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Support\CursorPage;
use App\Support\DeltaSync;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    #[QueryParameter(
        'updated_since',
        'Sincronización delta: sólo lo cambiado después de esta marca de agua. Usa el `meta.synced_at` de la respuesta anterior, nunca el reloj del cliente (docs/api/_convenciones.md).',
        required: false,
        type: 'string',
        format: 'date-time',
        example: '2026-09-17T10:00:00Z',
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $sync = DeltaSync::fromRequest($request);

        $query = $request->user()->notifications()->getQuery();

        // Marking one read touches `updated_at`, so a delta carries read state
        // across devices — the point of syncing notifications at all.
        $sync->apply($query, fn ($q) => $q->orderByDesc('created_at')->orderByDesc('id'));

        $page = $query->cursorPaginate(CursorPage::perPage((int) $request->query('per_page', '20')));

        return NotificationResource::collection($page->getCollection())
            ->additional(['meta' => [...CursorPage::meta($page), ...$sync->meta()]]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['count' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    public function markAsRead(Request $request, string $notification): Response
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return response()->noContent();
    }

    public function markAllAsRead(Request $request): Response
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }
}
