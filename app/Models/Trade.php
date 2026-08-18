<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trade extends Model
{
    protected $fillable = [
        'initiator_id',
        'receiver_id',
        'initiator_collection_id',
        'receiver_collection_id',
        'cash_difference',
        'status',
        'initiator_shipped_at',
        'receiver_shipped_at',
        'initiator_tracking',
        'receiver_tracking',
        'completed_at',
        'dispute_reason',
    ];

    protected $casts = [
        'cash_difference' => 'integer',
        'initiator_shipped_at' => 'datetime',
        'receiver_shipped_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // relasi ke initiator (pembuat offer)
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    // relasi ke receiver (penerima offer)
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    // relasi ke item yang ditawarkan initiator
    public function initiatorCollection(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'initiator_collection_id');
    }

    // relasi ke item yang diminta dari receiver
    public function receiverCollection(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'receiver_collection_id');
    }

    // cek apakah user adalah initiator
    public function isInitiator(int $userId): bool
    {
        return $this->initiator_id === $userId;
    }

    // cek apakah user adalah receiver
    public function isReceiver(int $userId): bool
    {
        return $this->receiver_id === $userId;
    }

    // cek apakah user terlibat dalam trade
    public function involvesUser(int $userId): bool
    {
        return $this->isInitiator($userId) || $this->isReceiver($userId);
    }

    // state machine: transisi status yang diizinkan
    protected array $allowedTransitions = [
        'pending' => ['negotiating', 'agreed', 'cancelled'],
        'negotiating' => ['pending', 'agreed', 'cancelled'],
        'agreed' => ['shipped_initiator', 'shipped_receiver', 'cancelled', 'disputed'],
        'shipped_initiator' => ['shipped_receiver', 'completed', 'disputed'],
        'shipped_receiver' => ['shipped_initiator', 'completed', 'disputed'],
        'completed' => [],
        'cancelled' => [],
        'disputed' => ['completed', 'cancelled'],
    ];

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions[$this->status] ?? [], true);
    }

    public function transitionTo(string $newStatus): bool
    {
        if (! $this->canTransitionTo($newStatus)) {
            return false;
        }

        $this->status = $newStatus;

        // side effects per status
        match ($newStatus) {
            'completed' => $this->completed_at = now(),
            'shipped_initiator' => $this->initiator_shipped_at = now(),
            'shipped_receiver' => $this->receiver_shipped_at = now(),
        };

        $this->save();
        return true;
    }

    // helper: cek apakah kedua pihak sudah kirim
    public function bothShipped(): bool
    {
        return $this->initiator_shipped_at && $this->receiver_shipped_at;
    }

    // helper: cek apakah trade bisa di-complete (kedua pihak sudah konfirmasi terima)
    // asumsi: completed otomatis saat bothShipped + 24 jam, atau manual confirm
    public function canComplete(): bool
    {
        return $this->status === 'agreed' && $this->bothShipped();
    }
}
