<?php

return [

    'catalog_url' => env('MAGEOS_CATALOG_URL', 'https://repo.mage-os.org/packages.json'),

    // Used as the `repositories` entry in the generated composer.json.
    'repository_url' => env('MAGEOS_REPOSITORY_URL', 'https://repo.mage-os.org/'),

    // The package whose versions are exposed in the version dropdown.
    'edition_package' => 'mage-os/project-community-edition',

    // The "fully modular" (modulargento) distribution — a flavor of the Mage-OS
    // release named in `version` below, served from its own Composer repo. When
    // the user picks it in the configurator (offered only when the selected
    // version equals `version`), every otherwise-locked set becomes removable
    // because the modulargento packages are decoupled. See the Configurator and
    // Definitions::isSetRemovable() for how the backing swaps.
    'modulargento' => [
        'repository_url' => env('MAGEOS_MODULARGENTO_REPOSITORY_URL', 'https://modulargento.cresset.tools/'),
        'edition_package' => 'modulargento/project-community-edition',
        // The Mage-OS release this flavor tracks; the distribution toggle only
        // appears when the selected version matches.
        'version' => env('MAGEOS_MODULARGENTO_VERSION', '3.0.0'),
        // Pinned for bougie's PHP runtime; emitted as a `require.php` constraint.
        'php_constraint' => env('MAGEOS_MODULARGENTO_PHP', '~8.3.0||~8.4.0||~8.5.0'),
        // The real published project-community-edition composer.json (runtime
        // keys stripped), used verbatim as the base so the generated project
        // carries every key a Mage-OS project does (require-dev, autoload-dev,
        // license, …). Refresh this file when bumping `version` above.
        'project_template_path' => base_path('resources/modulargento/project-community-edition.json'),
    ],

    // Filesystem paths (under storage/app/private when using the local disk)
    'cache_dir' => 'mageos-catalog',
    'graphs_dir' => 'graphs',
    'packagist_cache_dir' => 'packagist-cache',

    // Where YAML definitions live (relative to base_path()).
    'definitions_path' => 'definitions',

    // Fallback shipped with the tool, used when the catalog cache is empty.
    'fallback_version' => '2.2.2',

    // Credentials for the Hyvä private composer repo, used by
    // mageos:catalog:update to look up the latest hyva-themes/* versions:
    //   - hyva_project: the URL slug from your Hyvä account dashboard
    //     (https://hyva-themes.repo.packagist.com/<slug>/)
    //   - hyva_license_key: the HTTP basic-auth password (paired with the
    //     literal username "token") — i.e. the value you'd put in auth.json.
    'hyva_project' => env('MAGEOS_HYVA_PROJECT'),
    'hyva_license_key' => env('MAGEOS_HYVA_LICENSE_KEY'),
];
