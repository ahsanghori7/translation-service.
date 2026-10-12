<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\ExportScope;
use Illuminate\Http\Request;

interface CdnCachePolicy
{
    /**
     * @return array<string, string>
     */
    public function headers(ExportScope $scope, string $structure = 'flat'): array;

    public function publicUrl(Request $request): ?string;
}
