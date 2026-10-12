<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\NewTranslation;
use App\Data\TranslationUpdate;
use App\Models\Translation;

interface ManagesTranslations
{
    public function create(NewTranslation $data): Translation;

    public function update(Translation $translation, TranslationUpdate $data): Translation;

    public function delete(Translation $translation): void;
}
