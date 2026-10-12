<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\RevisionStore;
use Illuminate\Support\Facades\DB;

final class DatabaseRevisionStore implements RevisionStore
{
    public function current(): int
    {
        return (int) DB::table('translation_revisions')->where('id', 1)->value('version');
    }

    public function bump(): int
    {
        $current = (int) DB::table('translation_revisions')
            ->where('id', 1)
            ->lockForUpdate()
            ->value('version');
        $next = $current + 1;

        DB::table('translation_revisions')->where('id', 1)->update([
            'version' => $next,
            'updated_at' => now(),
        ]);

        return $next;
    }
}
