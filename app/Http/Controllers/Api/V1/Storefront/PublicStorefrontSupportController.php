<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class PublicStorefrontSupportController extends Controller
{
    public function contact(): JsonResponse
    {
        $settings = Setting::query()->active()
            ->whereIn('key', ['general.contact_phone', 'general.contact_email', 'general.support_channels'])
            ->pluck('value', 'key');

        $channels = json_decode((string) ($settings['general.support_channels'] ?? '[]'), true);
        if (! is_array($channels)) {
            $channels = [];
        }

        return response()->json([
            'data' => [
                'phone' => filled($settings['general.contact_phone'] ?? null) ? $settings['general.contact_phone'] : null,
                'email' => filled($settings['general.contact_email'] ?? null) ? $settings['general.contact_email'] : null,
                'channels' => array_values(array_intersect($channels, ['chat', 'email', 'phone'])),
            ],
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
