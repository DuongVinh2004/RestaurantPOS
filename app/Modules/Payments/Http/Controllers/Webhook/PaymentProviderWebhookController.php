<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Application\Workflows\PaymentWebhookIngestionWorkflow;
use App\Support\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentProviderWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentWebhookIngestionWorkflow $service,
    ) {}

    public function handle(string $providerCode, Request $request): JsonResponse
    {
        $headers = [];
        foreach ($request->headers->all() as $key => $values) {
            $headers[strtolower((string) $key)] = is_array($values) ? (string) ($values[0] ?? '') : (string) $values;
        }

        try {
            $result = $this->service->ingest($providerCode, (string) $request->getContent(), $headers);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $status = array_key_exists('signature', $errors) ? 401 : 422;
            $errorCode = $status === 401 ? 'invalid_signature' : 'validation_error';

            return ApiErrorResponse::json(
                $request,
                $status,
                $errorCode,
                $status === 401
                    ? 'Webhook signature verification failed.'
                    : 'Webhook payload is invalid.',
                ['errors' => $errors],
            );
        }

        return response()->json([
            'data' => $result,
        ], 202);
    }
}
