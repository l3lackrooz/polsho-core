<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AppAnnouncement extends Model
{
    protected $fillable = [
        'platform',
        'presentation',
        'type',
        'title',
        'title_translations',
        'message',
        'message_translations',
        'action_label',
        'action_label_translations',
        'action_url',
        'is_dismissible',
        'is_active',
        'publish_push',
        'push_status',
        'push_sent_at',
        'priority',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_dismissible' => 'boolean',
            'is_active' => 'boolean',
            'publish_push' => 'boolean',
            'push_sent_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'title_translations' => 'array',
            'message_translations' => 'array',
            'action_label_translations' => 'array',
        ];
    }

    /** @param Builder<AppAnnouncement> $query */
    public function scopeActiveFor(Builder $query, string $platform): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $query) use ($platform): void {
                $query->whereNull('platform')->orWhere('platform', $platform);
            })
            ->where(function (Builder $query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }
}
