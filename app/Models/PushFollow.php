<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One followed team or competition in a push subscriber's snapshot.
 * Replaced wholesale on every sync; ids are strings end to end (the
 * normalizer and the favorites store both cast).
 */
class PushFollow extends Model
{
    /** @var list<string> */
    protected $fillable = ['type', 'followed_id'];

    public $timestamps = false;

    /**
     * @return BelongsTo<PushSubscriber, $this>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(PushSubscriber::class, 'push_subscriber_id');
    }
}
