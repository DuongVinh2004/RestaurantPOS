<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccessSessionResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : [];
        $payload = [];

        foreach ([
            'auth_mode',
            'token_type',
            'auth_header',
            'access_token',
            'access_session_id',
            'session_id',
            'expires_at_utc',
            'revoked_at_utc',
            'user',
        ] as $key) {
            if (array_key_exists($key, $data)) {
                $payload[$key] = $data[$key];
            }
        }

        return $payload;
    }
}
