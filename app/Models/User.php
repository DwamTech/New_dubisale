<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Listing;
use App\Models\UserConversation;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'email_verified_at',
        'referral_code',
        'lat',
        'lng',
        'status',
        'receive_external_notif',
        'address',
        'country_code',
        'otp',
        'otp_verified_at',
        'role',
        'fcm_token',
        'guest_uuid',
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'otp_verified' => 'boolean',
            'receive_external_notif' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ListingReport::class);
    }

    /**
     * Get all messages sent by this user.
     */
    public function sentMessages(): MorphMany
    {
        return $this->morphMany(UserConversation::class, 'sender');
    }

    /**
     * Get all messages received by this user.
     */
    public function receivedMessages(): MorphMany
    {
        return $this->morphMany(UserConversation::class, 'receiver');
    }

    /**
     * Get all conversations this user is part of (sent or received).
     */
    public function conversations()
    {
        return UserConversation::forUser($this->id, self::class)
            ->select('conversation_id')
            ->distinct();
    }

    /*
    |--------------------------------------------------------------------------
    | Chat Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get unread messages count for this user.
     */
    public function unreadMessagesCount(): int
    {
        return $this->receivedMessages()->unread()->count();
    }

    /**
     * Get unread messages for this user.
     */
    public function unreadMessages()
    {
        return $this->receivedMessages()->unread()->orderBy('created_at', 'desc');
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}

