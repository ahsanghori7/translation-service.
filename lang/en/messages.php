<?php

declare(strict_types=1);

/*
 * Every user-facing API message, in one place. Call with
 * __('messages.<group>.<key>', [...]) and keep placeholders as :name.
 */

return [

    'auth' => [
        'unauthenticated' => 'Unauthenticated.',
        'invalid_credentials' => 'Invalid credentials.',
        'editor_required' => 'This action requires the editor role.',
    ],

    'locale' => [
        'not_found' => 'Locale :locale was not found.',
        'inactive' => 'Locale :locale is inactive.',
    ],

    'tag' => [
        'duplicate' => 'Tag :slug already exists.',
        'unknown' => 'Unknown tags: :tags.',
    ],

    'translation' => [
        'duplicate' => 'A translation for this key and locale already exists.',
    ],

    'http' => [
        'resource_not_found' => ':resource :id was not found.',
        'endpoint_not_found' => 'The requested endpoint does not exist.',
        'method_not_allowed' => 'The :method method is not allowed here. Allowed: :allowed.',
    ],

    'validation' => [
        'key_format' => 'The key may contain letters, numbers, dashes, underscores, and dots.',
        'locale_format' => 'The locale must look like en or pt-br.',
        'language_update_empty' => 'Provide a name or is_active to update.',
        'translation_update_empty' => 'Provide content, description, or tags to update.',
    ],

];
