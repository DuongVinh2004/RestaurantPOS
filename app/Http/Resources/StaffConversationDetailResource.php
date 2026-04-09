<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffConversationDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'conversation' => (new StaffConversationSummaryResource($this['conversation']))->resolve($request),
            'messages' => StaffConversationMessageResource::collection($this['messages'])->resolve($request),
            'events' => StaffConversationEventResource::collection($this['events'])->resolve($request),
            'analyses' => StaffConversationAnalysisResource::collection($this['analyses'])->resolve($request),
            'ai_assist' => (new StaffConversationAiAssistResource($this['ai_assist'] ?? []))->resolve($request),
            'assignment_history' => StaffConversationAssignmentResource::collection($this['assignment_history'])->resolve($request),
            'capabilities' => $this['capabilities'],
        ];
    }
}
