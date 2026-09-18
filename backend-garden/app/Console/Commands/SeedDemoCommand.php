<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DeliveryEventType;
use App\Enums\DeliveryMode;
use App\Enums\DeliveryStatus;
use App\Enums\DollChatMessageType;
use App\Enums\LeapDayPolicy;
use App\Enums\LetterKind;
use App\Enums\ModerationStatus;
use App\Enums\RecurrenceType;
use App\Enums\TransitTier;
use App\Enums\UserRole;
use App\Models\DollChatMessage;
use App\Models\DollProfile;
use App\Models\DollRequest;
use App\Models\FeatureFlag;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Models\LetterSchedule;
use App\Models\User;
use App\Services\Blog\BlogPublisher;
use App\Services\Scheduling\OccurrenceGenerator;
use App\Services\Scheduling\OccurrenceMaterializer;
use App\Support\TiptapContent;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * A coherent demo scenario for local front-end work: readable handles, letters
 * in every delivery state, a schedule with occurrences, blog posts (including
 * one awaiting consent and one held for review), Doll profiles (verified
 * and pending) and Doll requests (pending and in progress) —
 * backend-garden/docs/migraciones.md ("sin esto, probar el front es
 * lentísimo").
 *
 * Safe to re-run: every demo user shares the `@demo.evergarden.test` domain and
 * is wiped (cascading through their letters, deliveries, posts…) before
 * reseeding. Refuses to run in production.
 */
class SeedDemoCommand extends Command
{
    protected $signature = 'evergarden:seed-demo';

    protected $description = 'Seed a coherent demo scenario (users, letters, a schedule, blog posts)';

    private const DEMO_DOMAIN = '@demo.evergarden.test';

    private const PASSWORD_NOTE = 'password';

    public function handle(
        BlogPublisher $blog,
        OccurrenceGenerator $generator,
        OccurrenceMaterializer $materializer,
    ): int {
        if (app()->environment('production')) {
            $this->components->error('evergarden:seed-demo se niega a correr en producción.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($blog, $generator, $materializer): void {
            $this->wipePreviousRun();

            $users = $this->seedUsers();
            $this->seedLetters($users);
            $this->seedSchedule($users, $generator, $materializer);
            $this->seedBlog($users, $blog);
            $this->seedDolls($users);
            $this->enableFlags();
        });

        $this->components->info('Escenario de demo listo. Todos los usuarios comparten la contraseña "'.self::PASSWORD_NOTE.'".');

        return self::SUCCESS;
    }

    private function wipePreviousRun(): void
    {
        $existing = User::withTrashed()->where('email', 'like', '%'.self::DEMO_DOMAIN)->get();
        if ($existing->isEmpty()) {
            return;
        }

        $this->components->info("Retirando {$existing->count()} usuarios de una tanda anterior…");

        // doll_requests.client_id/doll_id are nullOnDelete (like letter_deliveries'
        // sender/recipient — preserves history for real accounts), so a stale run's
        // requests would otherwise survive as orphans instead of being replaced.
        $ids = $existing->pluck('id');
        DollRequest::query()
            ->where(fn ($q) => $q->whereIn('client_id', $ids)->orWhereIn('doll_id', $ids))
            ->delete();

        $existing->each(fn (User $user) => $user->forceDelete());
    }

    /**
     * @return array<string, User> keyed by first name, lowercase
     */
    private function seedUsers(): array
    {
        $people = [
            ['name' => 'Violet Evergarden', 'handle' => 'violet-evergarden', 'accepts_random' => true, 'country' => 'ES'],
            ['name' => 'Gilbert Bougainvillea', 'handle' => 'gilbert-b', 'accepts_random' => false, 'country' => 'ES'],
            ['name' => 'Cattleya Baudelaire', 'handle' => 'cattleya', 'accepts_random' => true, 'country' => 'FR'],
            ['name' => 'Iris Cannary', 'handle' => 'iris-cannary', 'accepts_random' => false, 'country' => 'GB'],
            ['name' => 'Erica Brown', 'handle' => 'erica-brown', 'accepts_random' => true, 'country' => 'US'],
            ['name' => 'Benedict Blue', 'handle' => 'benedict-blue', 'accepts_random' => false, 'country' => 'US'],
            // Runs the CH Postal Company in canon — the natural admin for a moderation panel.
            ['name' => 'Claudia Hodgins', 'handle' => 'hodgins', 'accepts_random' => false, 'country' => 'ES', 'role' => UserRole::Admin],
        ];

        $users = [];
        foreach ($people as $person) {
            $first = explode(' ', $person['name'])[0];

            $user = User::factory()->withSettings()->create([
                'name' => $person['name'],
                'postal_handle' => $person['handle'],
                'email' => mb_strtolower($first).self::DEMO_DOMAIN,
                'email_verified_at' => now()->subMonths(6),
                'country_code' => $person['country'],
                'last_active_at' => now(),
                'accepts_random_letters' => $person['accepts_random'],
                'role' => $person['role'] ?? UserRole::Client,
            ]);
            $user->settings()->update(['notify_push' => true]);

            $users[mb_strtolower($first)] = $user;
        }

        $this->components->info(count($users).' usuarios de demo creados (contraseña: '.self::PASSWORD_NOTE.').');

        return $users;
    }

    /**
     * @param  array<string, User>  $u
     */
    private function seedLetters(array $u): void
    {
        $states = [
            ['sender' => 'violet', 'recipient' => 'gilbert', 'title' => 'Doce cartas', 'status' => 'queued'],
            ['sender' => 'gilbert', 'recipient' => 'violet', 'title' => 'El significado de "te quiero"', 'status' => 'in_transit'],
            ['sender' => 'cattleya', 'recipient' => 'iris', 'title' => 'Gracias por tu ayuda', 'status' => 'delivered'],
            ['sender' => 'iris', 'recipient' => 'cattleya', 'title' => 'Una disculpa tardía', 'status' => 'read'],
            ['sender' => 'erica', 'recipient' => 'benedict', 'title' => 'Nos vemos pronto', 'status' => 'cancelled'],
            ['sender' => 'benedict', 'recipient' => 'erica', 'title' => 'Perdida en el correo', 'status' => 'failed'],
        ];

        foreach ($states as $s) {
            $letter = $this->makeLetter($u[$s['sender']], $s['title'], "Querido/a {$u[$s['recipient']]->displayName()}, esto es una carta de demostración ({$s['status']}).");

            $delivery = new LetterDelivery([
                'sender_id' => $u[$s['sender']]->getKey(),
                'recipient_id' => $u[$s['recipient']]->getKey(),
                'delivery_mode' => DeliveryMode::Direct,
                'tier' => TransitTier::Standard,
                'scheduled_for' => now()->subHour(),
                'is_anonymous' => false,
            ]);
            $delivery->letter()->associate($letter);
            $delivery->save();
            $delivery->recordEvent(DeliveryEventType::Created);
            $delivery->recordEvent(DeliveryEventType::Queued);

            match ($s['status']) {
                'queued' => null,
                'in_transit' => $delivery->markInTransit(180, now()->addHours(2), now()->addHours(2)->addMinutes(10)),
                'delivered' => $this->fastForwardToDelivered($delivery),
                'read' => $this->fastForwardToDelivered($delivery)->markRead(),
                'cancelled' => $delivery->cancel(),
                'failed' => $delivery->markFailed('recipient_unavailable'),
            };

            $letter->lock();
        }

        // One "bottle at sea" held for human review, so the moderation queue is
        // never empty in a fresh demo.
        $held = $this->makeLetter($u['violet'], null, 'A veces todavía pienso en la guerra y no sé qué hacer con ese peso.');
        $heldDelivery = new LetterDelivery([
            'sender_id' => $u['violet']->getKey(),
            'recipient_id' => null,
            'delivery_mode' => DeliveryMode::Random,
            'tier' => TransitTier::Standard,
            'scheduled_for' => now(),
            'is_anonymous' => true,
        ]);
        $heldDelivery->letter()->associate($held);
        // `status` and `held_at` aren't mass-assignable (RandomLetterSender sets
        // them the same way once a verdict comes back `flagged`).
        $heldDelivery->status = DeliveryStatus::Held;
        $heldDelivery->held_at = now();
        $heldDelivery->save();
        $heldDelivery->recordEvent(DeliveryEventType::Created);
        $held->forceFill(['kind' => LetterKind::Random, 'moderation_status' => ModerationStatus::Flagged])->save();

        $this->components->info('6 cartas en distintos estados + 1 aleatoria retenida para revisión.');
    }

    private function fastForwardToDelivered(LetterDelivery $delivery): LetterDelivery
    {
        $delivery->markInTransit(1, now(), now());
        $delivery->forceFill(['delivered_at' => now()->subMinutes(1)])->save();
        $delivery->markDelivered();

        return $delivery;
    }

    private function makeLetter(User $author, ?string $title, string $text): Letter
    {
        $body = ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
        ]];
        $plain = TiptapContent::toPlainText($body);

        $letter = new Letter([
            'title' => $title,
            'body' => $body,
            'body_plain' => $plain,
            'word_count' => TiptapContent::wordCount($plain),
            'style' => ['paper' => 'parchment', 'font' => 'cormorant', 'ink' => 'sepia'],
            'kind' => LetterKind::Direct,
        ]);
        $letter->author_id = $author->getKey();
        $letter->save();

        return $letter;
    }

    /**
     * @param  array<string, User>  $u
     */
    private function seedSchedule(array $u, OccurrenceGenerator $generator, OccurrenceMaterializer $materializer): void
    {
        $letter = $this->makeLetter($u['violet'], 'Feliz cumpleaños', 'Cada año que pasa pienso más en lo mucho que has crecido.');
        $letter->lock();

        $schedule = new LetterSchedule([
            'recipient_id' => $u['gilbert']->getKey(),
            'name' => 'Cumpleaños de Gilbert',
            'recurrence_type' => RecurrenceType::Yearly,
            'anchor_date' => now()->addDays(20)->toDateString(),
            'local_time' => '09:00',
            'timezone' => 'Europe/Madrid',
            'occurrences_total' => 5,
            'leap_day_policy' => LeapDayPolicy::Feb28,
            'letter_id' => $letter->getKey(),
            'tier' => TransitTier::Standard,
        ]);
        $schedule->user_id = $u['violet']->getKey();
        $schedule->save();

        foreach ($generator->upcoming($schedule, CarbonImmutable::now(), 90) as $occurrence) {
            $materializer->materialise($schedule, $occurrence);
        }

        $this->components->info('1 programación anual con ocurrencias materializadas.');
    }

    /**
     * @param  array<string, User>  $u
     */
    private function seedBlog(array $u, BlogPublisher $blog): void
    {
        $reflection = $blog->createPost($u['iris'], [
            'type' => 'reflection',
            'title' => 'Lo que aprendí escribiendo cartas ajenas',
            'body' => ['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Cada carta que escribo para otra persona me enseña algo sobre las palabras que yo misma no sé decir.']]],
            ]],
            'tags' => ['oficio', 'palabras'],
        ]);

        $blog->createComment($u['cattleya'], $reflection, [
            'body' => 'Esto me llegó mucho, gracias por compartirlo.',
        ]);

        $blog->createPost($u['erica'], [
            'type' => 'poem',
            'title' => 'Orilla',
            'body' => ['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'El mar no guarda las cartas, / solo las presta un momento.']]],
            ]],
            'tags' => ['poesía'],
        ]);

        // Awaiting the original sender's consent — keeps the consent inbox non-empty.
        $delivery = LetterDelivery::factory()->read()->create([
            'sender_id' => $u['benedict']->getKey(),
            'recipient_id' => $u['violet']->getKey(),
            'letter_id' => $this->makeLetter($u['benedict'], null, 'Gracias por escucharme aquel día.')->getKey(),
        ]);
        $blog->createPost($u['violet'], [
            'type' => 'shared_letter',
            'letter_delivery_id' => $delivery->getKey(),
            'title' => 'La carta que me devolvió la esperanza',
            'body' => ['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'No esperaba que unas pocas líneas cambiaran tanto mi semana.']]],
            ]],
            'testimonial' => 'Gracias, Benedict, por escribirme esto.',
            'is_anonymous' => true,
            'tags' => ['gratitud'],
        ]);

        $this->components->info('3 publicaciones de blog (una pendiente de consentimiento) + 1 comentario.');
    }

    /**
     * @param  array<string, User>  $u
     */
    private function seedDolls(array $u): void
    {
        $cattleya = new DollProfile([
            'headline' => 'Especialista en cartas de despedida',
            'bio' => 'Diez años escuchando lo que la gente no sabe decir.',
            'specialties' => ['duelo', 'disculpa'],
            'languages' => ['es', 'fr'],
            'tone_tags' => ['íntimo', 'sobrio'],
            'is_available' => true,
        ]);
        $cattleya->user_id = $u['cattleya']->getKey();
        $cattleya->save();
        $cattleya->markVerified();

        $erica = new DollProfile([
            'headline' => 'Cartas de celebración y negocios',
            'bio' => 'De propuestas a agradecimientos corporativos, con la formalidad justa.',
            'specialties' => ['celebración', 'negocios'],
            'languages' => ['en'],
            'tone_tags' => ['formal'],
            'is_available' => false,
        ]);
        $erica->user_id = $u['erica']->getKey();
        $erica->save();
        $erica->markVerified();

        // Pending review — keeps the Filament verification queue non-empty.
        $iris = new DollProfile([
            'headline' => 'Me encantaría ayudar con cartas de amor',
            'bio' => 'Nueva en esto, pero llevo un diario desde los doce años.',
            'specialties' => ['amor'],
            'languages' => ['en'],
            'tone_tags' => ['poético'],
        ]);
        $iris->user_id = $u['iris']->getKey();
        $iris->save();

        // A pending ask (Cattleya hasn't answered yet) and one already underway —
        // exercises the accept/reject/start/cancel flow in the front demo.
        DollRequest::factory()->create([
            'client_id' => $u['violet']->getKey(),
            'doll_id' => $u['cattleya']->getKey(),
            'occasion' => 'Disculpa a un hermano',
            'brief_notes' => 'Llevamos tres años sin hablar y no sé cómo empezar.',
            'target_recipient_hint' => 'Mi hermano mayor',
            'desired_tone' => ['íntimo', 'sobrio'],
        ]);

        $underway = DollRequest::factory()->inProgress()->create([
            'client_id' => $u['gilbert']->getKey(),
            'doll_id' => $u['cattleya']->getKey(),
            'occasion' => 'Carta de agradecimiento',
            'brief_notes' => 'Quiero agradecerle a mi mentor todo lo que hizo por mí.',
            'target_recipient_hint' => 'Mi antiguo mentor',
            'desired_tone' => ['cálido', 'formal'],
        ]);

        $this->seedDollChat($underway, client: $u['gilbert'], doll: $u['cattleya']);

        $this->components->info('3 perfiles Doll (2 verificadas, 1 pendiente de revisión) + 2 solicitudes (pendiente, en curso con chat y borrador v1 sin aprobar).');
    }

    /**
     * A short transcript ending in an unapproved draft: the demo lands on the
     * one screen where the client has something to decide (Fase 3C).
     */
    private function seedDollChat(DollRequest $request, User $client, User $doll): void
    {
        $lines = [
            [$doll, 'Gracias por confiarme esto. ¿Qué te enseñó tu mentor que no supiste agradecerle en su momento?'],
            [$client, 'Que se puede ser exigente sin ser cruel. Tardé años en entenderlo.'],
            [$doll, '¿Hay algún momento concreto? Una carta se sostiene mejor sobre una escena que sobre una idea.'],
            [$client, 'La tarde que rompió un informe mío delante de todos y luego se quedó hasta las diez ayudándome a rehacerlo.'],
        ];

        foreach ($lines as $i => [$sender, $body]) {
            $message = new DollChatMessage([
                'type' => DollChatMessageType::Text,
                'body' => $body,
            ]);
            $message->doll_request_id = $request->getKey();
            $message->sender_id = $sender->getKey();
            $message->save();
            // Spread them out so the transcript reads as a conversation, not a burst.
            $message->forceFill(['created_at' => now()->subMinutes(40 - ($i * 8))])->save();
        }

        $draft = new DollChatMessage([
            'type' => DollChatMessageType::Draft,
            'body' => 'Primera versión. Dime si el tono es el que buscabas.',
            'draft_version' => 1,
            'draft_payload' => [
                'title' => 'Para quien me enseñó a exigir sin herir',
                'body' => [
                    'type' => 'doc',
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => [[
                            'type' => 'text',
                            'text' => 'Nunca le di las gracias por aquella tarde. Rompió mi informe delante '
                                .'de todos y después se quedó hasta las diez ayudándome a rehacerlo. Tardé '
                                .'años en entender que esas dos cosas eran la misma.',
                        ]],
                    ]],
                ],
                'style' => [],
            ],
        ]);
        $draft->doll_request_id = $request->getKey();
        $draft->sender_id = $doll->getKey();
        $draft->save();
    }

    private function enableFlags(): void
    {
        FeatureFlag::query()->update(['enabled' => true]);
        $this->components->info('Todos los feature flags activados para la demo.');
    }
}
