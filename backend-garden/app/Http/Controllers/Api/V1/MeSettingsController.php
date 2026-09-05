<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\UpdateSettingsRequest;
use App\Http\Resources\UserSettingsResource;
use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->respond($this->settingsFor($request->user()));
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $userColumns = array_intersect_key($data, array_flip(['accepts_random_letters', 'random_letters_daily_cap']));
        if ($userColumns !== []) {
            // Not in User::$fillable by design; this endpoint is their sanctioned writer.
            $user->forceFill($userColumns)->save();
        }

        $settings = $this->settingsFor($user);
        $settings->fill(array_diff_key($data, $userColumns))->save();

        return $this->respond($settings);
    }

    private function respond(UserSettings $settings): JsonResponse
    {
        // Settings always "exist" to the client; never surface a 201 from firstOrCreate.
        return (new UserSettingsResource($settings))->response()->setStatusCode(200);
    }

    private function settingsFor(User $user): UserSettings
    {
        $settings = UserSettings::query()->firstOrCreate(['user_id' => $user->id]);
        $settings->wasRecentlyCreated = false;
        $settings->setRelation('user', $user);

        return $settings;
    }
}
