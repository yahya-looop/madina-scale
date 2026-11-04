<?php

namespace App\Http\Controllers;

use App\Models\MarketingCampaign;
use App\Models\MarketingCoupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MarketingController extends Controller
{
    public function page(Request $r, string $view = 'overview'): InertiaResponse
    {
        abort_unless($r->user()?->can('system.marketing.view'), 403);

        return Inertia::render('Marketing/Index', ['view' => $view]);
    }

    public function campaigns(Request $r): JsonResponse
    {
        return response()->json(MarketingCampaign::query()->when($r->filled('status'), fn ($q) => $q->where('status', $r->input('status')))->latest()->paginate(25));
    }

    public function storeCampaign(Request $r): JsonResponse
    {
        $d = $this->campaignData($r);
        $d['created_by'] = $r->user()->id;

        return response()->json(['data' => MarketingCampaign::create($d)], 201);
    }

    public function updateCampaign(Request $r, MarketingCampaign $campaign): JsonResponse
    {
        $campaign->update($this->campaignData($r));

        return response()->json(['data' => $campaign->fresh()]);
    }

    public function deleteCampaign(MarketingCampaign $campaign): JsonResponse
    {
        $campaign->delete();

        return response()->json(['message' => 'Campaign deleted.']);
    }

    /** @return array<string, mixed> */
    private function campaignData(Request $r): array
    {
        return $r->validate(['name' => ['required', 'string', 'max:180'], 'channel' => ['required', Rule::in(['onsite', 'email', 'sms', 'social', 'paid_ads', 'other'])], 'objective' => ['nullable', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:5000'], 'status' => ['required', Rule::in(['draft', 'scheduled', 'active', 'paused', 'completed', 'archived'])], 'budget' => ['required', 'numeric', 'min:0'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'], 'audience_rules' => ['nullable', 'array'], 'settings' => ['nullable', 'array']]);
    }

    public function coupons(): JsonResponse
    {
        return response()->json(['data' => MarketingCoupon::query()->latest()->get()]);
    }

    public function storeCoupon(Request $r): JsonResponse
    {
        $d = $this->couponData($r);
        $d['code'] = mb_strtoupper(trim($d['code']));
        $d['created_by'] = $r->user()->id;

        return response()->json(['data' => MarketingCoupon::create($d)], 201);
    }

    public function updateCoupon(Request $r, MarketingCoupon $coupon): JsonResponse
    {
        $d = $this->couponData($r);
        $d['code'] = mb_strtoupper(trim($d['code']));
        $coupon->update($d);

        return response()->json(['data' => $coupon->fresh()]);
    }

    public function deleteCoupon(MarketingCoupon $coupon): JsonResponse
    {
        $coupon->delete();

        return response()->json(['message' => 'Coupon deleted.']);
    }

    /** @return array<string, mixed> */
    private function couponData(Request $r): array
    {
        $couponRoute = $r->route('coupon');
        $couponId = $couponRoute instanceof MarketingCoupon ? $couponRoute->getKey() : null;

        return $r->validate(['code' => ['required', 'string', 'max:64', Rule::unique('marketing_coupons', 'code')->ignore($couponId)], 'name' => ['required', 'string', 'max:180'], 'discount_type' => ['required', Rule::in(['percentage', 'fixed'])], 'discount_value' => ['required', 'numeric', 'gt:0', Rule::when($r->input('discount_type') === 'percentage', ['max:100'])], 'minimum_order' => ['nullable', 'numeric', 'min:0'], 'maximum_discount' => ['nullable', 'numeric', 'gt:0'], 'usage_limit' => ['nullable', 'integer', 'min:1'], 'per_customer_limit' => ['nullable', 'integer', 'min:1'], 'is_active' => ['required', 'boolean'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'], 'conditions' => ['nullable', 'array']]);
    }

    public function track(Request $r, MarketingCampaign $campaign): JsonResponse
    {
        // Client-originated tracking must never be allowed to manufacture revenue/conversions.
        $d = $r->validate([
            'event_type' => ['required', Rule::in(['impression', 'click'])],
            'source' => ['nullable', 'string', 'max:40'],
            'visitor_key' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
        ]);
        abort_unless($campaign->status === 'active'
         && (! $campaign->starts_at || Carbon::parse($campaign->starts_at)->lte(now()))
         && (! $campaign->ends_at || Carbon::parse($campaign->ends_at)->gte(now())), 404);
        $d['visitor_key'] = isset($d['visitor_key']) ? hash('sha256', $d['visitor_key']) : null;
        $campaign->events()->create($d);

        return response()->json(['ok' => true], 201);
    }

    public function analytics(): JsonResponse
    {
        $rows = MarketingCampaign::query()->withCount(['events as impressions_count' => fn ($q) => $q->where('event_type', 'impression'), 'events as clicks_count' => fn ($q) => $q->where('event_type', 'click'), 'events as conversions_count' => fn ($q) => $q->where('event_type', 'conversion')])->withSum(['events as conversion_value' => fn ($q) => $q->where('event_type', 'conversion')], 'value')->latest()->get();

        return response()->json(['data' => $rows]);
    }
}
