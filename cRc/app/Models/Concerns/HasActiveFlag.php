<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared code for every model with an `is_active` column (Company, Contact, MessageTemplate, User).
 *
 * WHY A TRAIT: these four models already extend different parents (Model, and Authenticatable for User), and PHP allows
 * only one parent class. A trait is a block of methods that PHP copies into each class that says `use HasActiveFlag;`,
 * so the same code is shared without inheritance. Inside the trait, `$this` is the model that uses it.
 *
 * What you get on each model:
 *   Company::active()->get()     only active rows      (a "local scope": method scopeActive, called as active())
 *   Company::inactive()->get()   only inactive rows
 *   $company->isActive()         true / false
 *   $company->toggleActive()     flips is_active and saves (the admin "Activate / Deactivate" button)
 *
 * The @method lines only help the editor: Laravel finds scopeActive() at runtime when you call active(), which an IDE
 * cannot see on its own.
 *
 * @method static Builder active()
 * @method static Builder inactive()
 */
trait HasActiveFlag
{
    /**
     * Query::active(). Laravel turns a method named scopeXxx into a query method xxx(). qualifyColumn() writes
     * "companies.is_active" (with the table name), so it still works inside joins where two tables have is_active.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_active'), true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_active'), false);
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /** Flips the flag and saves; returns the new state. forceFill: is_active is not mass-assignable on every model. */
    public function toggleActive(): bool
    {
        $this->forceFill(['is_active' => ! $this->isActive()])->save();

        return $this->isActive();
    }
}
