<?php

namespace App\Domain\Suggestion\Application\Presenters;

use App\Domain\Suggestion\Infrastructure\Persistence\Models\Suggestion;

/**
 * Shapes a suggestion for API consumers (mobile app and backoffice) without
 * leaking the persistence model.
 */
class SuggestionPresenter
{
    /** @return array<string, mixed> */
    public function present(Suggestion $suggestion): array
    {
        return [
            'id' => $suggestion->id,
            'type' => $suggestion->type,
            'subject' => $suggestion->subject,
            'market_kind' => $suggestion->market_kind,
            'exchange' => $suggestion->exchange,
            'website' => $suggestion->website,
            'note' => $suggestion->note,
            'status' => $suggestion->status,
            'admin_note' => $suggestion->admin_note,
            'created_at' => $suggestion->created_at->toISOString(),
            'updated_at' => $suggestion->updated_at->toISOString(),
        ];
    }

    /**
     * Backoffice needs the requester alongside the request; the app never sees
     * this variant.
     *
     * @return array<string, mixed>
     */
    public function presentForAdmin(Suggestion $suggestion): array
    {
        $user = $suggestion->user;

        return [
            ...$this->present($suggestion),
            'user' => $user === null ? null : [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'metadata' => $suggestion->metadata,
        ];
    }
}
