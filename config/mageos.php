<?php

return [

    'catalog_url' => env('MAGEOS_CATALOG_URL', 'https://repo.mage-os.org/packages.json'),

    // Used as the `repositories` entry in the generated composer.json.
    'repository_url' => env('MAGEOS_REPOSITORY_URL', 'https://repo.mage-os.org/'),

    // The package whose versions are exposed in the version dropdown.
    'edition_package' => 'mage-os/project-community-edition',

    // The minimal edition used as the base in ADDITIVE ("inverse") mode: start
    // from this lean set and `require` the features you add, instead of starting
    // from the full edition and `replace`-ing what you remove.
    'minimal_edition_package' => 'mage-os/project-minimal-edition',

    // Packages force-added to the minimal base's `require` in additive mode.
    // The published mage-os/product-minimal-edition 3.x drops laminas/laminas-view
    // while keeping laminas/laminas-i18n, whose view helpers reference it — so
    // `setup:di:compile` fatals without this. (Fixed upstream via the mirror-repo
    // allowlist; kept here so additive builds compile regardless of which minimal
    // metapackage version resolves.)
    'minimal_base_extra_require' => [
        'laminas/laminas-view' => '^2.20',
    ],

    // The "fully modular" (modulargento) distribution — a flavor of a Mage-OS
    // release, served from its own Composer repo. When the user picks it in the
    // configurator (offered only when the selected version is one of the keys in
    // `versions` below), every otherwise-locked set becomes removable because the
    // modulargento packages are decoupled. See the Configurator and
    // Definitions::isSetRemovable() for how the backing swaps.
    'modulargento' => [
        'repository_url' => env('MAGEOS_MODULARGENTO_REPOSITORY_URL', 'https://modulargento.cresset.tools/'),
        'edition_package' => 'modulargento/project-community-edition',
        // `mage-os/*` packages the modulargento repo serves UN-renamed: only the
        // lockstep monorepo (+ inventory + page-builder) packages get the
        // modulargento vendor; these standalone forks keep their original name,
        // so additive builds must `require` them as-is. Mirrors the mirror-repo
        // build's product-community-edition dependencies template
        // (cresset-tools/generate-mirror-repo-js).
        'standalone_packages' => [
            'mage-os/module-page-builder-widget',
            'mage-os/module-admin-activity-log',
            'mage-os/module-rma',
            'mage-os/module-automatic-translation',
            'mage-os/module-meta-robots-tag',
            'mage-os/module-theme-optimization',
            'mage-os/module-page-builder-template-import-export',
            'mage-os/module-inventory-reservations-grid',
            'mage-os/theme-adminhtml-m137',
        ],
        // Every published modulargento release, keyed by the Mage-OS version it
        // tracks. The distribution toggle appears whenever the selected version
        // is one of these keys; each entry carries that release's PHP constraint
        // and its published project-community-edition template.
        //
        // Each `project_template_path` is the real published
        // project-community-edition composer.json (runtime keys stripped), used
        // verbatim as the base so the generated project carries every key a
        // Mage-OS project does (require-dev, autoload-dev, license, …). Add a new
        // entry (and its template under resources/modulargento/<version>/) when a
        // new modulargento release ships. Both 3.0.0 and 3.1.0 target PHP 8.4:
        // their published packages inherit the Magento 2.4.9 base's
        // ~8.2.0||~8.3.0||~8.4.0 ceiling (e.g. modulargento/framework-graph-ql),
        // so the project must NOT advertise 8.5 — composer would refuse to
        // install it on 8.5.
        'versions' => [
            '3.0.0' => [
                'php_constraint' => env('MAGEOS_MODULARGENTO_PHP', '~8.4.0'),
                'project_template_path' => base_path('resources/modulargento/3.0.0/project-community-edition.json'),
            ],
            '3.1.0' => [
                'php_constraint' => env('MAGEOS_MODULARGENTO_PHP', '~8.4.0'),
                'project_template_path' => base_path('resources/modulargento/3.1.0/project-community-edition.json'),
                // Published by the mirror-repo build (2026-07-04) — unlocks the
                // additive ("inverse") mode for modulargento on this version.
                // 3.0.0 stays subtractive-only: the live repo serves one release
                // at a time and no 3.0.0 minimal edition was ever published.
                'minimal_edition_package' => 'modulargento/project-minimal-edition',
                'minimal_project_template_path' => base_path('resources/modulargento/3.1.0/project-minimal-edition.json'),
            ],
        ],
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
