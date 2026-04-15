<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class StaffConversationAnalysisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'analysis_id' => (int) $this->analysis_id,
            'conversation_id' => (string) $this->conversation_id,
            'analyzer_name' => $this->analyzer_name,
            'is_spam' => (bool) $this->is_spam,
            'quality_score' => $this->quality_score !== null ? (string) $this->quality_score : null,
            'extracted_info' => $this->extracted_info,
            'created_at' => $this->iso($this->created_at),
        ];
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        return Carbon::parse((string) $value)->utc()->toIso8601String();
    }
}
