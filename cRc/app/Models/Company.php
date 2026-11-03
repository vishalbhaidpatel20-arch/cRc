<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $primaryKey = 'company_id';

    public $timestamps = false;

    protected $fillable = ['company_code', 'name', 'industry', 'email', 'phone', 'city', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'company_id', 'company_id');
    }

    /** Free-text search over name, code, city, industry. */
    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn ($q) => $q
            ->where('name', 'like', $like)
            ->orWhere('company_code', 'like', $like)
            ->orWhere('city', 'like', $like)
            ->orWhere('industry', 'like', $like));
    }
}
