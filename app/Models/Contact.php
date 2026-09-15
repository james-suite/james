<?php

namespace App\Models;

use App\Traits\HasInitials;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Override;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Contact extends Model implements HasMedia
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'relationship_category',
        'birthdate',
        'phones',
        'emails',
        'notes',
    ];

    use HasFactory, HasInitials, InteractsWithMedia, Searchable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'phones' => 'array',
            'emails' => 'array',
        ];
    }

    /**
     * Get the contact's avatar URL.
     */
    protected function avatar(): Attribute
    {
        return Attribute::get(function (): ?string {
            $media = $this->getFirstMedia('avatar');

            if (! $media || ! is_file($media->getPath())) {
                return null;
            }

            return route('contacts.avatar', $this).'?v='.$media->updated_at->timestamp;
        });
    }

    /**
     * Register the media collections for the model.
     */
    #[Override]
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'])
            ->useDisk('avatars');
    }

    /**
     * Convert and save the avatar.
     */
    public function saveAvatar(UploadedFile $file): void
    {
        Image::load($file->getPathname())
            ->format('webp')
            ->fit(Fit::Crop, 200, 200)
            ->save();

        $this->addMedia($file)
            ->usingFileName(Str::random(40).'.webp')
            ->toMediaCollection('avatar');
    }

    /**
     * Get the groups the contact belongs to.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ContactGroup::class);
    }

    /**
     * Get the settlements for the contact.
     */
    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    /**
     * Get the settlement archive record for the contact.
     */
    public function settlementArchive()
    {
        return $this->hasOne(ContactSettlementArchive::class);
    }

    /**
     * Scope a query to only include contacts that are not archived in the settlements module.
     */
    public function scopeNotSettlementArchived(Builder $query): Builder
    {
        return $query->whereDoesntHave('settlementArchive');
    }

    /**
     * Get the distinct relationship categories in use.
     *
     * @param  Builder  $query
     * @return Collection<int, string>
     */
    public function scopeRelationshipCategories($query): Collection
    {
        return $query->whereNotNull('relationship_category')
            ->distinct()
            ->orderBy('relationship_category', 'asc')
            ->pluck('relationship_category');
    }

    protected static array $recordEvents = ['created', 'updated', 'deleted', 'restored', 'forceDeleted'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('contact');
    }
}
