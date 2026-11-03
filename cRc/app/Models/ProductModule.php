<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A module of the product a client uses. Names are entered by an admin, never hard-coded. */
class ProductModule extends Model
{
    protected $table = 'product_modules';

    protected $primaryKey = 'module_id';

    public $timestamps = false;

    protected $fillable = ['name', 'description'];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_modules', 'module_id', 'contact_id');
    }
}
