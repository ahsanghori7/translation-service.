<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    /** Read and write: languages, tags, translations. */
    case Editor = 'editor';

    /** Read-only: search, show, export. */
    case Viewer = 'viewer';

    public function canWrite(): bool
    {
        return $this === self::Editor;
    }
}
