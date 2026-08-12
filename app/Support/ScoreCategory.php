<?php

namespace App\Support;

enum ScoreCategory: string
{
    case Excellent = 'excellent';
    case VeryGood = 'very_good';
    case Good = 'good';
    case Moderate = 'moderate';
    case Low = 'low';

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 90 => self::Excellent,
            $score >= 80 => self::VeryGood,
            $score >= 70 => self::Good,
            $score >= 60 => self::Moderate,
            default => self::Low,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Excellent => 'Excellent',
            self::VeryGood => 'Very Good',
            self::Good => 'Good',
            self::Moderate => 'Moderate',
            self::Low => 'Low',
        };
    }
}
