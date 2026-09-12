<?php

namespace App\Support;

/**
 * Visibilitas sebuah aktivitas di permukaan sosial.
 *
 * Default aplikasi adalah `Private`: aktivitas tidak pernah menjadi publik
 * tanpa tindakan eksplisit pemiliknya. Data kesehatan (HRV, tidur, stress,
 * body battery, recovery) tidak terikat pada enum ini karena tidak pernah
 * dirender di permukaan sosial sama sekali.
 */
enum ActivityVisibility: string
{
    case Public = 'public';
    case Followers = 'followers';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Semua orang',
            self::Followers => 'Pengikut saja',
            self::Private => 'Hanya saya',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Public => 'Siapa pun yang membuka RAGA dapat melihat aktivitas ini.',
            self::Followers => 'Hanya pengikut Anda yang dapat melihat aktivitas ini.',
            self::Private => 'Tidak ada orang lain yang dapat melihat aktivitas ini.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Public => '🌍',
            self::Followers => '👥',
            self::Private => '🔒',
        };
    }

    /**
     * Apakah aktivitas boleh dilihat oleh pengunjung lain?
     *
     * @param  bool  $viewerFollowsOwner  Apakah penonton mengikuti pemilik aktivitas.
     */
    public function isVisibleTo(bool $isOwner, bool $viewerFollowsOwner): bool
    {
        return match ($this) {
            self::Public => true,
            self::Followers => $isOwner || $viewerFollowsOwner,
            self::Private => $isOwner,
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
