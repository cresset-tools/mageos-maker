<?php

namespace Tests\Unit;

use App\Services\Configurator;
use PHPUnit\Framework\TestCase;

class HyvaRequirementTest extends TestCase
{
    public function test_detects_a_required_hyva_package(): void
    {
        $composer = ['require' => [
            'php' => '>=8.3',
            'mage-os/product-community-edition' => '^3.0',
            'hyva-themes/magento2-default-theme' => '^1.3',
        ]];

        $this->assertTrue(Configurator::requiresHyva($composer));
    }

    public function test_no_hyva_package_means_no_requirement(): void
    {
        $composer = ['require' => [
            'php' => '>=8.3',
            'mage-os/product-community-edition' => '^3.0',
        ]];

        $this->assertFalse(Configurator::requiresHyva($composer));
    }

    public function test_a_hyva_repository_url_alone_is_not_a_requirement(): void
    {
        // The licensed repo URL contains "hyva" but, without a required
        // hyva-themes/* package, the project does not actually need Hyvä.
        $composer = [
            'require' => ['mage-os/product-community-edition' => '^3.0'],
            'repositories' => [
                ['type' => 'composer', 'url' => 'https://hyva-themes.repo.packagist.com/slug/'],
            ],
        ];

        $this->assertFalse(Configurator::requiresHyva($composer));
    }

    public function test_missing_require_key_is_safe(): void
    {
        $this->assertFalse(Configurator::requiresHyva([]));
    }

    public function test_detects_the_hyva_build_of_loki_checkout(): void
    {
        $composer = ['require' => [
            'mage-os/product-community-edition' => '^3.0',
            'loki-checkout/magento2-hyva' => '^1.0',
        ]];

        $this->assertTrue(Configurator::requiresLokiCheckoutHyva($composer));
    }

    public function test_luma_build_of_loki_checkout_is_not_the_hyva_panel(): void
    {
        // The Luma build is a different package with different setup steps, so
        // it must not trip the on-screen Hyvä-flavoured Loki setup panel.
        $composer = ['require' => [
            'mage-os/product-community-edition' => '^3.0',
            'loki-checkout/magento2-luma' => '^1.0',
        ]];

        $this->assertFalse(Configurator::requiresLokiCheckoutHyva($composer));
        $this->assertFalse(Configurator::requiresLokiCheckoutHyva([]));
    }
}
