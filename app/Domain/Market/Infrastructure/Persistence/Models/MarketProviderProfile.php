<?php

namespace App\Domain\Market\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketProviderProfile extends Model
{
    protected $table = 'market_provider_profiles';

    protected $fillable = [
        'provider_id',
        'type',
        'publication_status',
        'summary',
        'description',
        'seo_title',
        'seo_description',
        'legal_name',
        'country_code',
        'founded_year',
        'kyc_required',
        'fee_url',
        'support_url',
        'terms_url',
        'android_app_url',
        'ios_app_url',
        'facts',
        'sources',
        'last_reviewed_at',
        'published_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'description' => 'array',
        'seo_title' => 'array',
        'seo_description' => 'array',
        'kyc_required' => 'boolean',
        'facts' => 'array',
        'sources' => 'array',
        'last_reviewed_at' => 'date',
        'published_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(MarketProvider::class, 'provider_id');
    }
}
