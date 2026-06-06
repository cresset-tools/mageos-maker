<?php

namespace Tests\Feature;

use App\Livewire\Configurator;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The configurator surfaces a Standard / Fully-modular (modulargento)
 * distribution toggle on the version the flavor tracks. Picking modulargento
 * unlocks the sets that are forced-on in stock Mage-OS; switching back re-locks
 * them so a stock module is never silently dropped.
 */
class ModulargentoFlavorTest extends TestCase
{
    private function mgVersion(): string
    {
        return (string) config('mageos.modulargento.version', '3.0.0');
    }

    public function test_toggle_is_offered_only_on_the_tracked_version(): void
    {
        Livewire::test(Configurator::class)
            ->set('version', $this->mgVersion())
            ->assertSet('distribution', 'standard')
            ->assertSee('Fully modular');
    }

    public function test_picking_modulargento_unlocks_a_locked_set(): void
    {
        $component = Livewire::test(Configurator::class)
            ->set('version', $this->mgVersion());

        // Stock: gift-message is forced on — removing it from the checked list
        // doesn't put it in disabledSets.
        $component->set('enabledSets', array_values(array_diff(
            $component->get('enabledSets'),
            ['gift-message'],
        )));
        $this->assertNotContains('gift-message', $component->instance()->selection()->disabledSets);

        // Switch to the fully-modular flavor: gift-message becomes removable, so
        // the same unchecked state now yields a real removal.
        $component->set('distribution', 'modulargento');
        $component->set('enabledSets', array_values(array_diff(
            $component->get('enabledSets'),
            ['gift-message'],
        )));

        $sel = $component->instance()->selection();
        $this->assertSame('modulargento', $sel->distribution);
        $this->assertContains('gift-message', $sel->disabledSets);

        // And it surfaces in the generated composer.json as a modulargento/* replace.
        $this->assertArrayHasKey('modulargento/module-gift-message', $component->get('composer')['replace'] ?? []);
    }

    public function test_switching_back_to_standard_relocks_the_set(): void
    {
        $component = Livewire::test(Configurator::class)
            ->set('version', $this->mgVersion())
            ->set('distribution', 'modulargento');

        // Remove a now-removable locked set.
        $component->set('enabledSets', array_values(array_diff(
            $component->get('enabledSets'),
            ['gift-message'],
        )));
        $this->assertContains('gift-message', $component->instance()->selection()->disabledSets);

        // Back to standard: the set is force-re-enabled and no longer disabled.
        $component->set('distribution', 'standard');
        $this->assertContains('gift-message', $component->get('enabledSets'));
        $this->assertNotContains('gift-message', $component->instance()->selection()->disabledSets);
    }

    public function test_leaving_the_tracked_version_resets_to_standard(): void
    {
        $component = Livewire::test(Configurator::class)
            ->set('version', $this->mgVersion())
            ->set('distribution', 'modulargento')
            ->assertSet('distribution', 'modulargento');

        // A version that the flavor doesn't track snaps the distribution back.
        $component->set('version', '2.4.8')
            ->assertSet('distribution', 'standard')
            ->assertDontSee('Fully modular');
    }
}
