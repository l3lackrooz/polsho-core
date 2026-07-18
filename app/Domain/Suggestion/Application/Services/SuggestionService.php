<?php

namespace App\Domain\Suggestion\Application\Services;

use App\Domain\Suggestion\Infrastructure\Persistence\Models\Suggestion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SuggestionService
{
    /** Requests submitted by a single user, newest first. */
    public function listForUser(int $userId, int $perPage = 25): LengthAwarePaginator
    {
        return Suggestion::query()->where('user_id', $userId)->latest()->paginate($perPage);
    }

    /**
     * Backoffice queue with optional status/type/search filters.
     *
     * @param array{status?: ?string, type?: ?string, search?: ?string} $filters
     */
    public function listForAdmin(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return Suggestion::query()
            ->with('user')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('subject', 'like', "%{$search}%")
                        ->orWhere('exchange', 'like', "%{$search}%")
                        ->orWhere('note', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function create(?int $userId, array $data): Suggestion
    {
        // Only the fields relevant to the chosen type are persisted, so a
        // client that over-posts (e.g. a website on an instrument request)
        // can't leave stray values behind.
        $type = $data['type'];

        return Suggestion::create([
            'user_id' => $userId,
            'type' => $type,
            'subject' => $data['subject'],
            'market_kind' => $type === 'add_instrument' ? ($data['market_kind'] ?? null) : null,
            'exchange' => $type === 'add_exchange' ? null : ($data['exchange'] ?? null),
            'website' => $type === 'add_exchange' ? ($data['website'] ?? null) : null,
            'note' => $data['note'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'status' => 'under_review',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function review(Suggestion $suggestion, array $data): Suggestion
    {
        $suggestion->update($data);

        return $suggestion->refresh();
    }

    public function delete(Suggestion $suggestion): void
    {
        $suggestion->delete();
    }
}
