<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    public const PRODUCT_ROLES = ['end_user', 'admin', 'decision_maker', 'technical', 'finance'];

    public const CHANNELS = ['call', 'whatsapp', 'email', 'meeting'];

    protected $primaryKey = 'contact_id';

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'full_name', 'email', 'phone', 'whatsapp_no', 'position', 'department',
        'business_name', 'product_role', 'preferred_channel', 'is_primary', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'last_contacted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(ContactProfile::class, 'contact_id', 'contact_id');
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(ProductModule::class, 'contact_modules', 'contact_id', 'module_id')->orderBy('product_modules.name');
    }

    /** Two-letter avatar text, e.g. "Rohan Shah" gives "RS". */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->full_name)) ?: [];
        $letters = array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_filter($parts));

        return implode('', array_slice($letters, 0, 2)) ?: '?';
    }

    /** Free-text search over name, email, phones, position and company name. */
    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn ($q) => $q
            ->where('contacts.full_name', 'like', $like)
            ->orWhere('contacts.email', 'like', $like)
            ->orWhere('contacts.phone', 'like', $like)
            ->orWhere('contacts.whatsapp_no', 'like', $like)
            ->orWhere('contacts.position', 'like', $like)
            ->orWhere('contacts.business_name', 'like', $like)
            ->orWhereHas('company', fn ($c) => $c->where('name', 'like', $like)));
    }
}
