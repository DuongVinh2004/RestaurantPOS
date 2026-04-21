<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Http\Controllers\Staff;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\Conversations\Application\Services\StaffConversationFileAccessService;
use App\Modules\Conversations\Application\Services\StaffConversationInboxService;
use App\Modules\Conversations\Application\Services\StaffConversationWorkflowService;
use App\Modules\Conversations\Http\Requests\Staff\AddConversationInternalNoteRequest;
use App\Modules\Conversations\Http\Requests\Staff\AssignConversationRequest;
use App\Modules\Conversations\Http\Requests\Staff\LinkConversationRequest;
use App\Modules\Conversations\Http\Requests\Staff\ListStaffConversationsRequest;
use App\Modules\Conversations\Http\Requests\Staff\SendConversationOutboundReplyRequest;
use App\Modules\Conversations\Http\Requests\Staff\ShowStaffConversationRequest;
use App\Modules\Conversations\Http\Requests\Staff\TakeOverConversationRequest;
use App\Modules\Conversations\Http\Requests\Staff\UnassignConversationRequest;
use App\Modules\Conversations\Http\Requests\Staff\UpdateConversationWorkflowStateRequest;
use App\Modules\Conversations\Http\Resources\StaffConversationAssignmentResource;
use App\Modules\Conversations\Http\Resources\StaffConversationDetailResource;
use App\Modules\Conversations\Http\Resources\StaffConversationEventResource;
use App\Modules\Conversations\Http\Resources\StaffConversationMessageResource;
use App\Modules\Conversations\Http\Resources\StaffConversationSummaryResource;
use App\Support\Listing\ListingMetaFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationInboxController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffConversationInboxService $inboxService,
        private readonly StaffConversationWorkflowService $workflowService,
        private readonly StaffConversationFileAccessService $fileAccessService,
    ) {}

    public function index(ListStaffConversationsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $staffActorUserId = $this->resolveStaffActorUserId($request);
        $result = $this->inboxService->paginate($validated, $staffActorUserId);
        $paginator = $result['paginator'];
        $filterKeys = [
            'status',
            'workflow_state',
            'inbox_view',
            'channel',
            'assigned_agent_user_id',
            'assignment_state',
            'branch_id',
            'reservation_id',
            'waiting_list_id',
            'user_id',
            'created_from',
            'created_to',
            'q',
        ];
        $filters = [
            'status' => $validated['status'] ?? null,
            'workflow_state' => $validated['workflow_state'] ?? null,
            'inbox_view' => (string) ($validated['inbox_view'] ?? 'all'),
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
        ];
        $sortBy = (string) ($validated['sort_by'] ?? 'latest_activity');
        $sortDir = (string) ($validated['sort_dir'] ?? 'desc');
        $legacyAliases = [];
        foreach ($filterKeys as $filterKey) {
            $legacyAliases[$filterKey] = 'filter['.$filterKey.']';
        }
        $legacyAliases['sort_by'] = 'sort';
        $legacyAliases['sort_dir'] = 'sort';

        return response()->json([
            'data' => StaffConversationSummaryResource::collection($paginator->getCollection()),
            'meta' => ListingMetaFactory::paginated($paginator, $filters, [
                'supported' => true,
                'value' => ($sortDir === 'desc' ? '-' : '').$sortBy,
                'by' => $sortBy,
                'dir' => $sortDir,
            ], ListingMetaFactory::contract(
                $filterKeys,
                ['latest_activity', 'created_at', 'message_count'],
                '-latest_activity',
                true,
                100,
                $legacyAliases,
            ), [
                'action' => 'staff_conversation_inbox_index',
                'summary' => $result['summary'],
            ]),
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

    public function updateWorkflowState(string $conversation_id, UpdateConversationWorkflowStateRequest $request): JsonResponse
    {
        $staffActorUserId = $this->resolveStaffActorUserId($request);
        $result = $this->workflowService->updateWorkflowState($conversation_id, $request->validated(), $staffActorUserId);

        return $this->mutationResponse($request, $result);
    }

    public function sendOutboundReply(string $conversation_id, SendConversationOutboundReplyRequest $request): JsonResponse
    {
        $staffActorUserId = $this->resolveStaffActorUserId($request);
        $result = $this->workflowService->sendOutboundReply($conversation_id, $request->validated(), $staffActorUserId);

        return $this->mutationResponse($request, $result, 201);
    }

    public function accessFile(string $conversation_id, int $file_id, Request $request)
    {
        $staffActorUserId = $this->resolveStaffActorUserId($request);
        $downloadUrl = $this->fileAccessService->resolveFileDownloadUrl($conversation_id, $file_id, $staffActorUserId);

        return redirect()->away($downloadUrl);
    }

    public function accessMessageAttachment(string $conversation_id, int $message_id, Request $request)
    {
        $staffActorUserId = $this->resolveStaffActorUserId($request);
        $downloadUrl = $this->fileAccessService->resolveLegacyAttachmentDownloadUrl($conversation_id, $message_id, $staffActorUserId);

        return redirect()->away($downloadUrl);
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
