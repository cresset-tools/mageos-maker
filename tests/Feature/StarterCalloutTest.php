<?php

namespace Tests\Feature;

use App\Livewire\Configurator;
use Tests\TestCase;

/**
 * The "try it with bougie" callout shown above the composer.json dump
 * builds its `bougie new --starter <arg>` command from
 * Configurator::starterArg(): the shareable /c/{id} URL once a config is
 * saved, the `mageos` alias (default starter) otherwise.
 */
class StarterCalloutTest extends TestCase
{
    public function test_starter_arg_is_default_alias_when_unsaved(): void
    {
        $c = new Configurator();
        $c->savedId = null;

        $this->assertSame('mageos', $c->starterArg());
    }

    public function test_starter_arg_is_share_url_when_saved(): void
    {
        $c = new Configurator();
        $c->savedId = '019e7f69-7bb2-7253-8705-85f4068b8d99';

        $this->assertSame(
            url('/c/019e7f69-7bb2-7253-8705-85f4068b8d99'),
            $c->starterArg(),
        );
        $this->assertStringContainsString('/c/019e7f69-', $c->starterArg());
    }
}
