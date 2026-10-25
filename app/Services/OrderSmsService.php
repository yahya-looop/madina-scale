<?php

namespace App\Services;

use App\Models\SalesOrder;
use App\Models\SalesOrderSmsLog;
use Illuminate\Support\Facades\Http;
use Throwable;

class OrderSmsService
{
    public function send(SalesOrder $order, string $event, string $message, ?string $phone = null, bool $sensitive = false): SalesOrderSmsLog
    {
        $recipient = $this->normalizePhone($phone ?: (string) $order->customer_phone);
        $log = SalesOrderSmsLog::query()->create([
            'sales_order_id' => $order->id, 'recipient_phone' => $recipient, 'event' => $event,
            'message' => $sensitive ? '[REDACTED: sensitive SMS content]' : $message, 'provider' => (string) config('order_integrations.sms.driver', 'generic'),
            'status' => 'queued',
        ]);
        $sms = config('order_integrations.sms');
        if (empty($sms['enabled']) || empty($sms['endpoint']) || empty($sms['api_key'])) {
            $log->update(['status' => 'skipped', 'error_message' => 'SMS gateway is not configured or is disabled.']);

            return $log->refresh();
        }
        try {
            $request = Http::timeout((int) ($sms['timeout'] ?? 10))->acceptJson();
            $payload = [($sms['to_field'] ?? 'to') => $recipient, ($sms['message_field'] ?? 'message') => $message];
            if (($sms['auth_mode'] ?? 'header') === 'body') {
                $payload[$sms['api_key_field'] ?? 'api_key'] = $sms['api_key'];
            } else {
                $header = (string) ($sms['api_key_header'] ?? 'Authorization');
                $prefix = (string) ($sms['api_key_prefix'] ?? 'Bearer ');
                $request = $request->withHeaders([$header => $prefix.$sms['api_key']]);
            }
            if (! empty($sms['sender_id'])) {
                $payload[$sms['sender_field'] ?? 'sender_id'] = $sms['sender_id'];
            }
            $response = $request->post((string) $sms['endpoint'], $payload);
            $body = $response->json();
            if (! $response->successful()) {
                throw new \RuntimeException('SMS gateway returned HTTP '.$response->status());
            }
            $log->update(['status' => 'sent', 'sent_at' => now(), 'provider_message_id' => data_get($body, 'message_id') ?? data_get($body, 'id'), 'provider_response' => is_array($body) ? $body : ['body' => $response->body()]]);
        } catch (Throwable $exception) {
            $log->update(['status' => 'failed', 'error_message' => mb_substr($exception->getMessage(), 0, 2000)]);
        }

        return $log->refresh();
    }

    public function sendOrderEvent(SalesOrder $order, string $event): SalesOrderSmsLog
    {
        $templates = [
            'confirmed' => 'আপনার অর্ডার {order} নিশ্চিত করা হয়েছে। মোট: ৳{total}। ধন্যবাদ।',
            'shipment_booked' => 'আপনার অর্ডার {order} কুরিয়ারে পাঠানো হয়েছে। ট্র্যাকিং: {tracking}।',
            'delivered' => 'আপনার অর্ডার {order} ডেলিভারি সম্পন্ন হয়েছে। ধন্যবাদ।',
            'verification' => 'Madina Scale অর্ডার যাচাই কোড: {code}। কোডটি কাউকে জানাবেন না।',
        ];
        $template = $templates[$event] ?? 'আপনার অর্ডার {order} সম্পর্কে আপডেট রয়েছে।';
        $message = strtr($template, ['{order}' => $order->order_number, '{total}' => number_format((float) $order->grand_total, 2), '{tracking}' => 'কুরিয়ার ট্র্যাকিং পেজে দেখুন']);

        return $this->send($order, $event, $message);
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (str_starts_with($digits, '8801') && strlen($digits) === 13) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '01') && strlen($digits) === 11) {
            return '+88'.$digits;
        }
        if (str_starts_with($digits, '1') && strlen($digits) === 10) {
            return '+880'.$digits;
        }

        return $digits === '' ? $phone : '+'.$digits;
    }
}
