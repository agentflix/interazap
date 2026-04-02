<?php

declare(strict_types=1);

namespace Domain\Chat\Enums;

/**
 * Media type for chat messages.
 */
enum MediaType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case DOCUMENT = 'document';
    case STICKER = 'sticker';
    case VOICE = 'ptt';

    /**
     * Returns label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::IMAGE => 'Image',
            self::VIDEO => 'Video',
            self::AUDIO => 'Audio',
            self::DOCUMENT => 'Document',
            self::STICKER => 'Sticker',
            self::VOICE => 'Voice Message',
        };
    }
}
