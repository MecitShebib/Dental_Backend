<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomMessage;
use App\Models\MessageGroup;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Clinic-defined WhatsApp message groups and their ready-made messages
 * (Message Templates > Custom messages). Anyone in the clinic can list them
 * -- that's what the patient pages' "Send WhatsApp message" popup reads --
 * but only admins/accountants (the same people who can open the Message
 * Templates page) can create, edit or delete them. Company scoping comes
 * from the models' BelongsToCompany global scope.
 */
class CustomMessageController extends Controller
{
    /**
     * Groups of one specialty (?specialty=key, the app the user is in; a
     * doctor always gets their own) plus any pre-specialty groups (NULL).
     * No specialty given = every group.
     */
    public function index(Request $request)
    {
        $specialtyId = $this->specialtyId($request);

        $groups = MessageGroup::query()
            ->with('messages')
            ->when($specialtyId, fn ($query) => $query->where(fn ($inner) => $inner->where('specialty_id', $specialtyId)->orWhereNull('specialty_id')))
            ->orderBy('name')
            ->get();

        return $this->success($groups->map(fn (MessageGroup $group) => $this->groupPayload($group))->values());
    }

    public function storeGroup(Request $request)
    {
        $this->assertCanManage($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $group = MessageGroup::create([
            'company_id' => $request->user()->company_id,
            'specialty_id' => $this->specialtyId($request),
            'name' => $data['name'],
        ]);

        return $this->success($this->groupPayload($group->load('messages')), 'Group created.', 201);
    }

    public function updateGroup(Request $request, MessageGroup $group)
    {
        $this->assertCanManage($request);
        $group->update($request->validate(['name' => ['required', 'string', 'max:120']]));

        return $this->success($this->groupPayload($group->load('messages')), 'Group updated.');
    }

    public function destroyGroup(Request $request, MessageGroup $group)
    {
        $this->assertCanManage($request);
        // Delete message by message (not the FK cascade) so each one's
        // attachment file is removed from storage too.
        $group->messages()->get()->each->delete();
        $group->delete();

        return $this->success(null, 'Group deleted.');
    }

    public function storeMessage(Request $request, MessageGroup $group)
    {
        $this->assertCanManage($request);
        $data = $this->validatedMessage($request);

        $message = $group->messages()->create([
            'company_id' => $group->company_id,
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            ...$this->storeAttachment($request),
        ]);

        return $this->success($this->messagePayload($message), 'Message created.', 201);
    }

    public function updateMessage(Request $request, CustomMessage $message)
    {
        $this->assertCanManage($request);
        $data = $this->validatedMessage($request, $message);

        $attachment = [];
        if ($request->hasFile('attachment') || $request->boolean('remove_attachment')) {
            $message->deleteAttachmentFile();
            $attachment = ['attachment_path' => null, 'attachment_name' => null, 'attachment_mime' => null, ...$this->storeAttachment($request)];
        }

        $message->update(['title' => $data['title'], 'body' => $data['body'] ?? null, ...$attachment]);

        return $this->success($this->messagePayload($message), 'Message updated.');
    }

    public function destroyMessage(Request $request, CustomMessage $message)
    {
        $this->assertCanManage($request);
        $message->delete();

        return $this->success(null, 'Message deleted.');
    }

    protected function specialtyId(Request $request): ?int
    {
        $user = $request->user();
        if ($user->isDoctorOnly() && $user->specialty_id) {
            return (int) $user->specialty_id;
        }

        $key = $request->validate(['specialty' => ['nullable', 'string', 'exists:specialties,key']])['specialty'] ?? null;

        return $key ? Specialty::query()->where('key', $key)->value('id') : null;
    }

    protected function assertCanManage(Request $request): void
    {
        abort_unless($request->user()->hasAccountingAccess(), 403, 'Only clinic administrators can manage message templates.');
    }

    /**
     * A title plus text and/or an attachment (image or PDF). On update, an
     * attachment the message already has (and isn't being removed) counts.
     */
    protected function validatedMessage(Request $request, ?CustomMessage $existing = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:4000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,pdf', 'max:10240'],
            'remove_attachment' => ['nullable', 'boolean'],
        ]);

        $keepsAttachment = $existing?->attachment_path && ! $request->boolean('remove_attachment');
        if (blank($data['body'] ?? null) && ! $request->hasFile('attachment') && ! $keepsAttachment) {
            throw ValidationException::withMessages([
                'body' => ['Write the message text or attach an image/PDF.'],
            ]);
        }

        return $data;
    }

    protected function storeAttachment(Request $request): array
    {
        if (! $request->hasFile('attachment')) {
            return [];
        }

        $file = $request->file('attachment');

        return [
            'attachment_path' => $file->store('message-attachments', CustomMessage::ATTACHMENT_DISK),
            'attachment_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'attachment_mime' => $file->getMimeType(),
        ];
    }

    protected function groupPayload(MessageGroup $group): array
    {
        return [
            'id' => $group->id,
            'uuid' => $group->uuid,
            'name' => $group->name,
            'specialty_id' => $group->specialty_id,
            'messages' => $group->messages->map(fn (CustomMessage $message) => $this->messagePayload($message))->values(),
        ];
    }

    protected function messagePayload(CustomMessage $message): array
    {
        return [
            'id' => $message->id,
            'uuid' => $message->uuid,
            'message_group_id' => $message->message_group_id,
            'title' => $message->title,
            'body' => $message->body,
            'attachment_url' => $message->attachmentUrl(),
            'attachment_name' => $message->attachment_name,
            'attachment_type' => $message->attachmentType(),
        ];
    }
}
