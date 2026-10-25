<?php

namespace App\Services;

use App\Models\SalesOrder;
use App\Models\SalesOrderPhoneVerification;
use App\Models\SalesOrderRiskAssessment;
use Illuminate\Support\Facades\DB;

class OrderRiskService
{
    public function assess(SalesOrder $order): SalesOrderRiskAssessment
    {
        $score = 0;
        $signals = [];
        $phone = preg_replace('/\D+/', '', (string) $order->customer_phone) ?: '';
        if (strlen($phone) < 10) {
            $score += 25;
            $signals[] = ['code' => 'missing_or_invalid_phone', 'points' => 25, 'message' => 'Customer phone is missing or appears invalid.'];
        }
        if (trim((string) $order->customer_name) === '') {
            $score += 20;
            $signals[] = ['code' => 'missing_name', 'points' => 20, 'message' => 'Customer name is missing.'];
        }
        $samePhone = $phone !== '' ? SalesOrder::query()->where('id', '<>', $order->id)->where('customer_phone', $order->customer_phone)->where('created_at', '>=', now()->subDays(90))->get(['id', 'status']) : collect();
        $velocity = $phone !== '' ? SalesOrder::query()->where('customer_phone', $order->customer_phone)->where('created_at', '>=', now()->subHours(24))->where('id', '<>', $order->id)->count() : 0;
        if ($velocity >= (int) config('order_integrations.risk.phone_velocity_count', 3)) {
            $score += 30;
            $signals[] = ['code' => 'phone_order_velocity', 'points' => 30, 'count' => $velocity, 'message' => 'Multiple orders from the same phone in 24 hours.'];
        }
        $cancelled = $samePhone->where('status', 'cancelled')->count();
        if ($cancelled >= 2) {
            $score += min(30, $cancelled * 10);
            $signals[] = ['code' => 'repeat_cancellations', 'points' => min(30, $cancelled * 10), 'count' => $cancelled, 'message' => 'This phone has multiple previously cancelled orders.'];
        }
        $dupes = $phone !== '' ? DB::table('sales_orders')->where('customer_phone', $order->customer_phone)->where('created_at', '>=', now()->subHours(2))->where('id', '<>', $order->id)->count() : 0;
        $phoneVerified = SalesOrderPhoneVerification::query()->where('sales_order_id', $order->id)->whereNotNull('verified_at')->exists();
        if ($phoneVerified) {
            $signals[] = ['code' => 'phone_verified', 'points' => 0, 'message' => 'Customer phone verification completed.'];
        }
        if ($dupes > 0) {
            $score += 20;
            $signals[] = ['code' => 'recent_duplicate', 'points' => 20, 'count' => $dupes, 'message' => 'Another order using this phone was recently placed.'];
        }
        $score = min(100, $score);
        $reviewScore = (int) config('order_integrations.risk.review_score', 35);
        $highScore = (int) config('order_integrations.risk.high_score', 65);
        $level = $score >= $highScore ? 'high' : ($score >= $reviewScore ? 'medium' : 'low');
        $decision = $score >= $highScore ? 'manual_review' : ($score >= $reviewScore ? 'verify' : 'allow');

        return SalesOrderRiskAssessment::query()->create(['sales_order_id' => $order->id, 'score' => $score, 'level' => $level, 'decision' => $decision, 'signals' => $signals]);
    }
}
