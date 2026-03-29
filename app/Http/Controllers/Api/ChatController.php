<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Models\User;
use App\Models\UserConversation;
use App\Services\ChatService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ChatController extends Controller
{
    public function __construct(
        private ChatService $chatService,
        private NotificationService $notificationService
    ) {}

    /**
     * Send a message to another user.
     * POST /api/chat/send
     */
    public function send(SendMessageRequest $request): JsonResponse
    {
        $sender = $request->user();
        $receiver = User::findOrFail($request->validated('receiver_id'));
        $messageContent = $request->validated('message');
        $listingId = $request->validated('listing_id');
        $contentType = $request->validated('content_type') ?? UserConversation::CONTENT_TYPE_TEXT;

        // Prevent sending message to self
        if ($sender->id === $receiver->id) {
            return response()->json(['message' => __('api.cannot_message_self')], 422);
        }

        $attachmentPath = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $datePath = now()->format('Y/m');
            // Determine folder based on content type (image, video, etc.)
            $folder = in_array($contentType, ['image', 'video', 'audio']) ? $contentType . 's' : 'files';
            $dir = "chat/{$datePath}/{$folder}";
            
            try {
                // Ensure directory exists
                \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory($dir);
                
                $name = \Illuminate\Support\Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
                $attachmentPath = $file->storeAs($dir, $name, 'public');
                
                if (!$attachmentPath) {
                    throw new \Exception("File upload returned false");
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('chat_upload_failed', ['error' => $e->getMessage(), 'user_id' => $sender->id]);
                return response()->json(['message' => __('api.file_upload_failed')], 500);
            }
        }

        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($sender, $receiver, $messageContent, $listingId, $contentType, $attachmentPath) {
                // Determine the message text to save
                // If text/listing -> use message content
                // If media -> message content is optional caption
                $savedMessage = $messageContent;

                /** @var UserConversation $conversation */
                $conversation = $this->chatService->sendMessage(
                    $sender,
                    $receiver,
                    $savedMessage,
                    UserConversation::TYPE_PEER,
                    $listingId,
                    $contentType,
                    $attachmentPath // Pass attachment path
                );

                // Dispatch notification (Logic separated to avoid transaction coupling if using queues, but here it's sync check)
                // We'll move notification dispatch OUTSIDE transaction if possible, or keep it if light.
                // Keeping it inside for now as per original code structure, but simplified.
                $this->notifyReceiver($sender, $receiver, $conversation);

                return response()->json([
                    'message' => __('api.message_sent'),
                    'data' => [
                        'id' => $conversation->id,
                        'conversation_id' => $conversation->conversation_id,
                        'message' => $conversation->message,
                        'attachment' => $conversation->attachment ? asset('storage/' . $conversation->attachment) : null,
                        'content_type' => $conversation->content_type,
                        'created_at' => $conversation->created_at,
                    ],
                ], 201);
            });
        } catch (\Throwable $e) {
            // Cleanup uploaded file if DB transaction fails
            if ($attachmentPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($attachmentPath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($attachmentPath);
            }
            \Illuminate\Support\Facades\Log::error('chat_send_transaction_failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => __('api.message_send_error')], 500);
        }
    }

    private function notifyReceiver($sender, $receiver, $conversation) 
    {
        $cacheKey = "chat_notif_cooldown:{$sender->id}:{$receiver->id}";
        if (!Cache::has($cacheKey)) {
            $msgPreview = $conversation->content_type === 'text' ? "لديك رسالة جديدة من {$sender->name}" : "أرسل لك {$sender->name} مرفقاً";
            
            $this->notificationService->dispatch(
                $receiver->id,
                'رسالة جديدة',
                $msgPreview,
                'new_message',
                ['conversation_id' => $conversation->conversation_id, 'sender_id' => $sender->id]
            );
            Cache::put($cacheKey, true, now()->addMinutes(10));
        }
    }

    /**
     * Get conversation history with a specific user.
     * GET /api/chat/{user}
     */
    public function history(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        // Prevent viewing own conversation
        if ($currentUser->id === $user->id) {
            return response()->json(['message' => __('api.invalid_conversation')], 422);
        }

        $perPage = (int) $request->query('per_page', 50);
        $messages = $this->chatService->getHistoryBetweenUsers($currentUser, $user, $perPage);

        // Mark messages as read
        $conversationId = $this->chatService->getConversationId($currentUser, $user);
        $this->chatService->markConversationAsRead($conversationId, $currentUser);

        // Format messages to protect sensitive user data
        $formattedMessages = collect($messages->items())->map(function ($message) {
            return [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'sender' => $message->sender ? [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                ] : null,
                'receiver' => $message->receiver ? [
                    'id' => $message->receiver->id,
                    'name' => $message->receiver->name,
                ] : null,
                'message' => $message->message,
                'read_at' => $message->read_at,
                'type' => $message->type,
                'created_at' => $message->created_at,
            ];
        });

        return response()->json([
            'meta' => [
                'conversation_id' => $conversationId,
                'page' => $messages->currentPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'last_page' => $messages->lastPage(),
            ],
            'data' => $formattedMessages,
        ]);
    }

    /**
     * Get user's inbox (list of conversations).
     * GET /api/chat/inbox
     */
    public function inbox(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->query('type'); // Optional: filter by type

        $conversations = $this->chatService->getInbox($user, $type);

        return response()->json([
            'data' => $conversations,
            'unread_total' => $this->chatService->getTotalUnreadCount($user),
        ]);
    }

    /**
     * Send a message to support.
     * POST /api/chat/support
     */
    public function sendToSupport(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $sender = $request->user();
        $message = $request->input('message');

        $conversation = $this->chatService->sendSupportMessage($sender, $message);

        return response()->json([
            'message' => __('api.support_message_sent'),
            'data' => [
                'id' => $conversation->id,
                'conversation_id' => $conversation->conversation_id,
                'message' => $conversation->message,
                'created_at' => $conversation->created_at,
            ],
        ], 201);
    }

    /**
     * Get support conversation history.
     * GET /api/chat/support
     */
    public function supportHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversationId = $this->chatService->getConversationId($user, null, UserConversation::TYPE_SUPPORT);

        $perPage = (int) $request->query('per_page', 50);
        $messages = $this->chatService->getHistory($conversationId, $perPage);

        // Mark as read
        $this->chatService->markConversationAsRead($conversationId, $user);

        // Format messages to protect sensitive data
        $formattedMessages = collect($messages->items())->map(function ($message) use ($user) {
            // Check if message is from current user or from support team
            $isFromMe = $message->sender_id === $user->id && $message->sender_type === User::class;
            
            return [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'sender' => $isFromMe ? [
                    'id' => $user->id,
                    'name' => $user->name ?? 'أنت',
                    'is_support' => false,
                ] : [
                    'id' => $message->sender_id,
                    'name' => 'فريق الدعم',
                    'is_support' => true,
                ],
                'message' => $message->message,
                'read_at' => $message->read_at,
                'created_at' => $message->created_at,
            ];
        });

        return response()->json([
            'meta' => [
                'conversation_id' => $conversationId,
                'page' => $messages->currentPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'last_page' => $messages->lastPage(),
            ],
            'data' => $formattedMessages,
        ]);
    }

    /**
     * Mark a conversation as read.
     * PATCH /api/chat/{conversationId}/read
     */
    public function markAsRead(Request $request, string $conversationId): JsonResponse
    {
        $user = $request->user();
        $count = $this->chatService->markConversationAsRead($conversationId, $user);

        return response()->json([
            'message' => 'ok',
            'marked_count' => $count,
        ]);
    }

    /**
     * Get unread messages count.
     * GET /api/chat/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread_count' => $this->chatService->getTotalUnreadCount($user),
        ]);
    }

    /**
     * Get compact listing summary for chat display.
     * GET /api/chat/listing-summary/{category_slug}/{listing_id}
     * 
     * Returns minimal listing data to display as a card in chat.
     */
    public function getListingSummary(Request $request, string $categorySlug, int $listingId): JsonResponse
    {
        $sec = \App\Support\Section::fromSlug($categorySlug);
        
        $listing = \App\Models\Listing::query()
            ->where('id', $listingId)
            ->where('category_id', $sec->id())
            ->with(['governorate', 'city', 'attributes'])
            ->first();

        if (!$listing) {
            return response()->json(['success' => false, 'message' => __('api.listing_not_found')], 404);
        }

        // Get default image for categories that use default images
        $mainImageUrl = null;
        if (in_array($categorySlug, ['jobs', 'doctors', 'teachers'])) {
            $defPath = \Illuminate\Support\Facades\Cache::remember(
                "settings:{$categorySlug}_default_image",
                now()->addHours(6),
                fn() => \App\Models\SystemSetting::where('key', "{$categorySlug}_default_image")->value('value')
            );
            $mainImageUrl = $defPath ? asset('storage/' . $defPath) : null;
        } else {
            $mainImageUrl = $listing->main_image ? asset('storage/' . $listing->main_image) : null;
        }

        // Get title preference: Database column > Attribute > Description
        $title = $listing->title;
        if (!$title && $listing->relationLoaded('attributes')) {
            foreach ($listing->attributes as $attr) {
                if (in_array($attr->key, ['title', 'name', 'job_title'])) {
                    $title = $attr->value_string;
                    break;
                }
            }
        }
        if (!$title && $listing->description) {
            $title = \Illuminate\Support\Str::limit($listing->description, 50);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'type' => 'listing_card',
                'listing_id' => $listing->id,
                'category_slug' => $categorySlug,
                'category_name' => $sec->name,
                'title' => $title,
                'price' => $listing->price,
                'currency' => $listing->currency ?? 'ج.م',
                'price_formatted' => number_format($listing->price) . ' ' . ($listing->currency ?? 'ج.م'),
                'main_image_url' => $mainImageUrl,
                'governorate' => $listing->governorate?->name,
                'city' => $listing->city?->name,
                'status' => $listing->status,
                'published_at' => $listing->published_at,
            ],
        ]);
    }
}
