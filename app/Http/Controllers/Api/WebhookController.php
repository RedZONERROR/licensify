<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    /**
     * Handle Stripe webhooks
     */
    public function stripe(Request $request): JsonResponse
    {
        try {
            $signature = $request->header('Stripe-Signature');
            $webhookData = $request->all();

            Log::info('Stripe webhook received', [
                'event_type' => $webhookData['type'] ?? 'unknown',
                'id' => $webhookData['id'] ?? 'unknown',
            ]);

            $result = $this->paymentService->processWebhook('stripe', $webhookData, $signature);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Stripe webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle PayPal webhooks
     */
    public function paypal(Request $request): JsonResponse
    {
        try {
            $signature = $request->header('PAYPAL-TRANSMISSION-SIG');
            $webhookData = $request->all();

            Log::info('PayPal webhook received', [
                'event_type' => $webhookData['event_type'] ?? 'unknown',
                'id' => $webhookData['id'] ?? 'unknown',
            ]);

            $result = $this->paymentService->processWebhook('paypal', $webhookData, $signature);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('PayPal webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle Razorpay webhooks
     */
    public function razorpay(Request $request): JsonResponse
    {
        try {
            $signature = $request->header('X-Razorpay-Signature');
            $webhookData = $request->all();

            Log::info('Razorpay webhook received', [
                'event_type' => $webhookData['event'] ?? 'unknown',
                'id' => $webhookData['payload']['payment']['entity']['id'] ?? 'unknown',
            ]);

            $result = $this->paymentService->processWebhook('razorpay', $webhookData, $signature);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Razorpay webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Generic webhook handler for testing
     */
    public function test(Request $request): JsonResponse
    {
        Log::info('Test webhook received', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Test webhook received',
            'data' => [
                'timestamp' => now()->toISOString(),
                'headers' => $request->headers->all(),
                'body' => $request->all(),
            ],
        ]);
    }
}