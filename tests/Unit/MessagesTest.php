<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Arr;
use Tests\TestCase;

class MessagesTest extends TestCase
{
    public function test_every_message_key_resolves_and_placeholders_are_filled(): void
    {
        $messages = require lang_path('en/messages.php');

        foreach (array_keys(Arr::dot($messages)) as $key) {
            $this->assertNotSame('messages.'.$key, __('messages.'.$key), "Missing translation for messages.{$key}");
        }

        $this->assertSame('Locale fr was not found.', __('messages.locale.not_found', ['locale' => 'fr']));
        $this->assertSame('Unknown tags: web, app.', __('messages.tag.unknown', ['tags' => 'web, app']));
    }

    public function test_app_code_calls_only_keys_that_exist(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        $used = [];
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                preg_match_all("/__\\('(messages\\.[a-z_.]+)'/", (string) file_get_contents($file->getPathname()), $m);
                array_push($used, ...$m[1]);
            }
        }

        $this->assertNotEmpty($used);
        foreach (array_unique($used) as $key) {
            $this->assertNotSame($key, __($key), "app/ calls undefined message key {$key}");
        }
    }
}
