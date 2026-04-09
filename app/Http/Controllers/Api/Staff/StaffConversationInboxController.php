<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\AddConversationInternalNoteRequest;
use App\Http\Requests\Staff\AssignConversationRequest;
use App\Http\Requests\Staff\LinkConversationRequest;
use App\Http\Requests\Staff\ListStaffConversationsRequest;
use App\Http\Requests\Staff\SendConversationOutboundReplyRequest;
use App\Http\Requests\Staff\ShowStaffConversationRequest;
use App\Http\Requests\Staff\TakeOverConversationRequest;
use App\Http\Requests\Staff\UnassignConversationRequest;
use App\Http\Resources\StaffConversationAssignmentResource;
use App\Http\Resources\StaffConversationDetailResource;
use App\Http\Resources\StaffConversationEventResource;
use App\Http\Resources\StaffConversationMessageResource;
use App\Http\Resources\StaffConversationSummaryResource;
use App\Services\Staff\StaffConversationInboxService;
use App\Services\Staff\StaffConversationWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffConversationInboxController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffConversationInboxService $inboxService,
        private readonly StaffConversationWorkflowService $workflowService,
    ) {}

    public function index(ListStaffConversationsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $staffActorUserId = $this->resolveStaffActorUserId($request);
        $result = $this->inboxService->paginate($validated, $staffActorUserId);
        $paginator = $result['paginator'];

        return response()->json([
            'data' => StaffConversationSummaryResource::collection($paginator->getCollection()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more_pages' => $paginator->hasMorePages(),
                'filters' => [
                    'status' => $validated['status'] ?? null,
                    'channel' => $validated['channel'] ?? null,
                    'assigned_agent_user_id' => $validated['assigned_agent_user_id'] ?? null,
                    'assignment_state' => (string) ($validated['assignment_state'] ?? 'all'),
                    'branch_id' => $validated['branch_id'] ?? null,
                    'reservation_id' => $validated['reservation_id'] ?? null,
                    'waiting_list_id' => $validated['waiting_list_id'] ?? null,
                    'user_id' => $validated['user_id'] ?? null,
                    'created_from' => $validated['created_from'] ?? null,
                    'created_to' => $validated['created_to'] ?? null,
                    'q' => $validated['q'] ?? null,
                ],
                'sort' => [
                    'by' => (string) ($validated['sort_by'] ?? 'latest_activity'),
                    'dir' => (string) ($validated['sort_dir'] ?? 'desc'),
                ],
                'summary' => $result['summary'],
            ],
        ]);
    }

    public function show(string $conversation_id, ShowStaffConversationRequest $request): JsonResponse
    {
        $staffActorUserId = $this->resolveStaffActorUserId($request);
        $detail = $this->inboxService->detail($conversation_id, $request->validated(), $staffActorUserId);

        return response()->json([
            'data' => (new StaffConversationDetailResource($detail))->resolve($request),
            'meta' => [
                'message_limit' => (int) $detail['message_limit'],
                'event_limit' => (int) $detail['event_limit'],
                'include_closed_assignments' => (bool) $detail['include_closed_assignments'],
                'returned_counts' => [
                    'messages' => count((array) $detail['messages']),
                    'events' => count((array) $detail['events']),
                    'analyses' => count((array) $detail['analyses']),
                    'assignment_history' => count((array) $detail['assignment_history']),
                ],
            ],
        ]);
    }

    public function assign(string $conversation_id, AssignConversationRequest $request): JsonResponse
    {
        $staffActorUserId = $this->resolveStaffActorUserId($request);
        $validated = $request->validated();
        $result = $this->workflowService->assign(
            $conversation_id,
            (int) $validated['agent_user_id'],
            $staffActorUserId,
            $validated['notes'] ?? null,
        );

        return $this->mutationResponse($request, $result);
    }

    public function takeOver(string $conversation_id, TakeOverConversationRequest $request): JsonResponse
    {
        $staffActorUserId = $this->resolveStaffActorUserId($request);
        $result = $this->workflowService->takeOver(
            $conversation_id,
            $staffActorUserId,
            $request->validated()['notes'] ?? null,
        );

        return $this->mutationResponse($request, $result);
    }

    public function unassign(string $conversation_id, UnassignConversationRequest $request): JsonResponse
    {
        $staffActorUserId = $this->resolveStaffActorUserId($request);
        $result = $this->workflowService->unassign(
            $conversation_id,
            $staffActorUserId,
            $request->validated()['notes'] ?? null,
        );

        return $this->mutationResponse($request, $result);
    }

    public function link(string $conversation_id, LinkConversationRequest $request): JsonResponse
    {
        $staffActorUserId = $this->resolveStaffActorUserId($request);
        $result = $this->workflowService->link($conversation_id, $request->validated(), $staffActorUserId);

        return $this->mutationResponse($request, $result);
    }

    public function unlinkReservation(string $conversation_id, Request $request): JsonResponse
    {
        $staffActorUserId = $this->resolveStaffActorUserId($request);
        $result = $this->workflowService->unlinkReservation($conversation_id, $staffActorUserId);

        return $this->mutationResponse($request, $result);
    }

    public function unlinkWaitingList(string $conversation_id, Request $request): JsonResponse
    {
        $staffActorUserId = $this->resolveStaffActorUserId($request);
        $result = $this->workflowService->unlinkWaitingList($conversation_id, $staffActorUserId);

        return $this->mutationResponse($request, $result);
    }

    public function addInternalNote(string $conversation_id, AddConversationInternalNoteRequest $request): JsonResponse
    {
        $staffActorUserId = $this->resolveStaffActorUserId($request);
        $result = $this->workflowService->addInternalNote($conversation_id, $request->validated(), $staffActorUserId);

        return $this->mutationResponse($request, $result, 201);
    }

    public function sendOutboundReply(string $conversation_id, SendConversationOutboundReplyRequest $request): JsonResponse
    {
        $staffActorUserId = $this->resolveStaffActorUserId($request);
        $result = $this->workflowService->sendOutboundReply($conversation_id, $request->validated(), $staffActorUserId);

        return $this->mutationResponse($request, $result, 201);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function mutationResponse(Request $request, array $result, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => [
                'action' => (string) $result['action'],
                'conversation' => (new StaffConversationSummaryResource($result['conversation']))->resolve($request),
                'assignment' => $result['assignment'] !== null
                    ? (new StaffConversationAssignmentResource($result['assignment']))->resolve($request)
                    : null,
                'event' => $result['event'] !== null
                    ? (new StaffConversationEventResource($result['event']))->resolve($request)
                    : null,
                'message' => $result['message'] !== null
                    ? (new StaffConversationMessageResource($result['message']))->resolve($request)
                    : null,
            ],
            'meta' => [
                'conversation_id' => (string) $result['conversation']->conversation_id,
            ],
        ], $status);
    }
}
