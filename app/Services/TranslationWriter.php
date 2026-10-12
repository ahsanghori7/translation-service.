<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CdnPurger;
use App\Contracts\LanguageRepository;
use App\Contracts\ManagesTranslations;
use App\Contracts\RevisionStore;
use App\Contracts\TagRepository;
use App\Contracts\TranslationRepository;
use App\Data\NewTranslation;
use App\Data\TranslationUpdate;
use App\Exceptions\DuplicateTranslationException;
use App\Exceptions\InactiveLocaleException;
use App\Exceptions\LocaleNotFoundException;
use App\Models\Translation;
use App\Models\TranslationKey;
use App\Support\TranslationKeyRules;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class TranslationWriter implements ManagesTranslations
{
    public function __construct(
        private readonly LanguageRepository $languages,
        private readonly TagRepository $tags,
        private readonly TranslationRepository $translations,
        private readonly RevisionStore $revisions,
        private readonly CdnPurger $purger,
        private readonly TranslationKeyRules $keys,
    ) {
    }

    public function create(NewTranslation $data): Translation
    {
        $translation = DB::transaction(function () use ($data): Translation {
            $language = $this->languages->findByCode($data->locale);
            if ($language === null) {
                throw new LocaleNotFoundException($data->locale);
            }

            if (! $language->is_active) {
                throw new InactiveLocaleException($data->locale);
            }

            $key = $this->lockOrCreateKey($data);

            if ($data->description !== null && $key->description !== $data->description) {
                $key->description = $data->description;
                $key->save();
            }

            $existing = $this->translations->findForKeyAndLanguage($data->key, (int) $language->id);
            if ($existing !== null) {
                throw new DuplicateTranslationException();
            }

            if ($data->tags !== []) {
                $this->tags->attachToKey((int) $key->id, $this->tags->idsForSlugs($data->tags));
            }

            try {
                $translation = Translation::query()->create([
                    'translation_key_id' => $key->id,
                    'language_id' => $language->id,
                    'content' => $data->content,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new DuplicateTranslationException();
            }

            $this->revisions->bump();

            return $translation->load(['translationKey.tags', 'language']);
        });

        $this->purger->purge();

        return $translation;
    }

    /**
     * Returns the key row locked for this transaction, creating it if needed.
     * Concurrent creates of the same new key both reach the insert; the loser
     * hits the unique index and re-reads the winner's row with a locking
     * (current) read, which sees it even under REPEATABLE READ.
     */
    private function lockOrCreateKey(NewTranslation $data): TranslationKey
    {
        $locked = fn (): ?TranslationKey => TranslationKey::query()
            ->where('message_key', $data->key)
            ->lockForUpdate()
            ->first();

        $key = $locked();
        if ($key !== null) {
            return $key;
        }

        try {
            return TranslationKey::query()->withSavepointIfNeeded(
                fn (): TranslationKey => TranslationKey::query()->create([
                    'message_key' => $data->key,
                    'key_group' => $this->keys->group($data->key),
                    'description' => $data->description,
                ]),
            );
        } catch (UniqueConstraintViolationException) {
            return $locked() ?? throw new DuplicateTranslationException();
        }
    }

    public function update(Translation $translation, TranslationUpdate $data): Translation
    {
        $exportChanged = DB::transaction(function () use ($translation, $data): bool {
            $key = $translation->translationKey()->firstOrFail();

            if ($data->descriptionProvided) {
                $key->description = $data->description;
                $key->save();
            }

            if ($data->content !== null) {
                $translation->content = $data->content;
            }

            $contentChanged = $translation->isDirty('content');
            $translation->save();

            $tagsChanged = false;
            if ($data->tags !== null) {
                $result = $key->tags()->sync($this->tags->idsForSlugs($data->tags));
                $tagsChanged = $result['attached'] !== [] || $result['detached'] !== [];
            }

            if ($contentChanged || $tagsChanged) {
                $this->revisions->bump();

                return true;
            }

            return false;
        });

        if ($exportChanged) {
            $this->purger->purge();
        }

        return $translation->load(['translationKey.tags', 'language']);
    }

    public function delete(Translation $translation): void
    {
        DB::transaction(function () use ($translation): void {
            $keyId = $translation->translation_key_id;
            $translation->delete();

            $key = TranslationKey::query()->find($keyId);
            if ($key !== null && ! $key->translations()->exists()) {
                $key->delete();
            }

            $this->revisions->bump();
        });

        $this->purger->purge();
    }
}
