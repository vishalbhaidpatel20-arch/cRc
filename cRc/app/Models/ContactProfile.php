<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Optional 1-1 extras for a contact (contact_id is both primary and foreign key). */
class ContactProfile extends Model
{
    protected $table = 'contact_profiles';

    protected $primaryKey = 'contact_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['contact_id', 'alt_phone', 'birthday', 'language', 'preferred_time', 'notes'];

    protected function casts(): array
    {
        return ['birthday' => 'date'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id', 'contact_id');
    }
}
