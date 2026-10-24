<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    protected $fillable = ['name', 'owner_id', 'cover_path', 'cover_position_x', 'cover_position_y'];

    protected function casts(): array
    {
        return ['cover_position_x' => 'integer', 'cover_position_y' => 'integer'];
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path
            ? route('workspaces.cover', [$this, 'v' => md5($this->cover_path)])
            : null;
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->using(Membership::class)->withPivot('permissions')->withTimestamps();
    }

    public function allows(User $user, Permission $permission): bool
    {
        if ($this->owner_id === $user->id) {
            return true;
        }

        $member = $this->members()->whereKey($user->id)->first();

        return $member && in_array($permission->value, $member->pivot->permissions ?? [], true);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }
}
