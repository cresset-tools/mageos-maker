{{-- Tabbed Workspace: a three-column configurator (section rail · active section ·
     always-on output). All config state lives in the Livewire component; Alpine
     owns the pure view state (active section, output tab, module filter/search,
     category folds) so it survives Livewire DOM morphs. --}}
<div x-data="{ section: 'home', otab: 'composer' }">
@php
    // Shared copy-button icon, used by the bougie callout and the Hyvä steps.
    $copyIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" aria-hidden="true" focusable="false"><path d="M480 400L288 400C279.2 400 272 392.8 272 384L272 128C272 119.2 279.2 112 288 112L421.5 112C425.7 112 429.8 113.7 432.8 116.7L491.3 175.2C494.3 178.2 496 182.3 496 186.5L496 384C496 392.8 488.8 400 480 400zM288 448L480 448C515.3 448 544 419.3 544 384L544 186.5C544 169.5 537.3 153.2 525.3 141.2L466.7 82.7C454.7 70.7 438.5 64 421.5 64L288 64C252.7 64 224 92.7 224 128L224 384C224 419.3 252.7 448 288 448zM160 192C124.7 192 96 220.7 96 256L96 512C96 547.3 124.7 576 160 576L352 576C387.3 576 416 547.3 416 512L416 496L368 496L368 512C368 520.8 360.8 528 352 528L160 528C151.2 528 144 520.8 144 512L144 256C144 247.2 151.2 240 160 240L176 240L176 192L160 192z"/></svg>';

    // ---- Summary / nav-count derivations (server-rendered; refresh on each live update) ----
    $moduleSetNames = array_keys($setDefs);
    $enabledModuleCount = count(array_intersect($enabledSets, $moduleSetNames));
    $totalModuleCount = count($moduleSetNames);

    $languageSetNames = array_keys($languageDefs);
    $enabledLanguageCount = count(array_intersect($enabledSets, $languageSetNames));

    $addonsInUse = array_unique(array_merge($enabledAddons, $this->forcedAddons));
    $addonsAvailable = array_keys($addonDefs);
    $enabledAddonCount = count(array_intersect($addonsInUse, $addonsAvailable));

    $enabledLayerCount = count($enabledStockLayers) + count(array_filter(
        array_keys($layerDefs),
        fn ($n) => ($layerDefs[$n]['stock'] ?? true) === false && in_array($n, $this->forcedLayers, true),
    ));

    $distLabel = $distribution === 'modulargento' ? 'Modular' : 'Standard';
    $profileLabel = $profileDefs[$profile]['label'] ?? ($profile ?? '—');
    $themeOpt = collect($profileGroupDefs['theme']['options'] ?? [])->firstWhere('name', $profileGroups['theme'] ?? null);
    $checkoutOpt = collect($profileGroupDefs['checkout']['options'] ?? [])->firstWhere('name', $profileGroups['checkout'] ?? null);
    $themeLabel = $themeOpt['label'] ?? '—';
    $checkoutLabel = $checkoutOpt['label'] ?? '—';

    $pkgCount = $this->installTree['count'] ?? 0;
@endphp

<div class="shell">
    <div class="appbar">
        <span class="brand"><span class="glyph">M</span>mageos-maker</span>
        @if ($this->effectiveSavedId)
            <span class="appbar-saved">Saved <code>{{ $this->effectiveSavedId }}</code> · {{ $savedAt }}</span>
        @elseif ($savedId)
            <span class="appbar-saved">Modified — save again to share</span>
        @endif
        <span class="sp"></span>
        <a class="ghost" href="https://bougie.tools" target="_blank" rel="noopener">Docs</a>
        <a class="ghost" href="{{ route('configurator.index') }}" wire:navigate>Reset</a>
    </div>

    <div class="work">
        {{-- ============ LEFT RAIL ============ --}}
        <aside class="rail scrollbox">
            <div class="summary">
                <div class="pf">{{ $profileLabel }}</div>
                <div class="meta">v{{ $version }} · {{ $distLabel }} · {{ $themeLabel }} · {{ $checkoutLabel }}</div>
                <div class="row2">
                    <span class="countchip"><b>{{ $pkgCount }}</b> packages</span>
                    <span class="countchip"><b>{{ $enabledModuleCount }}</b> modules</span>
                </div>
            </div>

            <div class="navitem" :class="{ active: section === 'home' }" @click="section = 'home'">
                <svg class="ic" viewBox="0 0 16 16" fill="none"><path d="M2.5 7L8 2.5 13.5 7" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 6.5V13h8V6.5" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>
                <span class="nm">Home</span>
            </div>
            <div class="navitem" :class="{ active: section === 'version' }" @click="section = 'version'">
                <svg class="ic" viewBox="0 0 16 16" fill="none"><path d="M8 1.5l5.5 3v7L8 14.5l-5.5-3v-7z" stroke="currentColor" stroke-width="1.3"/></svg>
                <span class="nm">Version</span><span class="ct">{{ $version }}</span>
            </div>
            @if ($modulargentoAvailable)
                <div class="navitem" :class="{ active: section === 'distribution' }" @click="section = 'distribution'">
                    <svg class="ic" viewBox="0 0 16 16" fill="none"><path d="M8 2l6 3v6l-6 3-6-3V5z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M8 2v12M2 5l6 3 6-3" stroke="currentColor" stroke-width="1.1"/></svg>
                    <span class="nm">Distribution</span><span class="ct">{{ $distLabel }}</span>
                </div>
            @endif
            <div class="navitem" :class="{ active: section === 'profile' }" @click="section = 'profile'">
                <svg class="ic" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5.5" r="2.5" stroke="currentColor" stroke-width="1.3"/><path d="M3 13c0-2.5 2.2-4 5-4s5 1.5 5 4" stroke="currentColor" stroke-width="1.3"/></svg>
                <span class="nm">Profile</span><span class="ct">{{ $profileLabel }}</span>
            </div>
            <div class="navitem" :class="{ active: section === 'theme' }" @click="section = 'theme'">
                <svg class="ic" viewBox="0 0 16 16" fill="none"><rect x="2" y="3" width="12" height="10" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M2 6h12" stroke="currentColor" stroke-width="1.3"/></svg>
                <span class="nm">Theme &amp; Checkout</span><span class="ct">{{ $themeLabel }}</span>
            </div>
            <div class="navitem" :class="{ active: section === 'addons' }" @click="section = 'addons'">
                <svg class="ic" viewBox="0 0 16 16" fill="none"><path d="M2.5 3h11l-1.2 7H4z" stroke="currentColor" stroke-width="1.3"/><circle cx="5.5" cy="13" r="1" fill="currentColor"/><circle cx="11" cy="13" r="1" fill="currentColor"/></svg>
                <span class="nm">Add-ons</span><span class="ct">{{ $enabledAddonCount }}</span>
            </div>

            <div class="grouplbl section-label">Packages</div>
            <div class="navitem" :class="{ active: section === 'modules' }" @click="section = 'modules'">
                <svg class="ic" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.3"/><rect x="9" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.3"/><rect x="2" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.3"/><rect x="9" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.3"/></svg>
                <span class="nm">Modules</span><span class="ct">{{ $enabledModuleCount }} / {{ $totalModuleCount }}</span>
            </div>
            @if (count($languageDefs) > 0)
                <div class="navitem" :class="{ active: section === 'languages' }" @click="section = 'languages'">
                    <svg class="ic" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.3"/><path d="M2 8h12M8 2c1.8 1.6 1.8 10.4 0 12M8 2c-1.8 1.6-1.8 10.4 0 12" stroke="currentColor" stroke-width="1.1"/></svg>
                    <span class="nm">Languages</span><span class="ct">{{ $enabledLanguageCount }}</span>
                </div>
            @endif
            <div class="navitem" :class="{ active: section === 'layers' }" @click="section = 'layers'">
                <svg class="ic" viewBox="0 0 16 16" fill="none"><path d="M8 2l6 3-6 3-6-3z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M2 8l6 3 6-3M2 11l6 3 6-3" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>
                <span class="nm">Layers</span><span class="ct">{{ $enabledLayerCount }}</span>
            </div>
        </aside>

        {{-- ============ CENTER ============ --}}
        <main class="center scrollbox">

            @if ($autoSnapNotice)
                <div class="snap-banner" wire:key="snap-{{ md5($autoSnapNotice) }}">
                    <span>{{ $autoSnapNotice }}</span>
                    <button type="button" wire:click="$set('autoSnapNotice', null)" aria-label="Dismiss">×</button>
                </div>
            @endif

            {{-- HOME --}}
            <section class="sec" x-show="section === 'home'" x-cloak>
                <div class="sec-head">
                    <h1>Build your Mage-OS project</h1>
                    <div class="sub">mageos-maker composes a tailored Mage-OS distribution. Pick a version, profile and modules on the left — the generated <b>composer.json</b> and the one-command bougie install update live on the right.</div>
                </div>
                <div class="sec-body">
                    <div class="home-stats">
                        <div class="stat"><div class="k">Version</div><div class="v">{{ $version }}</div></div>
                        <div class="stat"><div class="k">Distribution</div><div class="v">{{ $distLabel }}</div></div>
                        <div class="stat"><div class="k">Profile</div><div class="v">{{ $profileLabel }}</div></div>
                        <div class="stat"><div class="k">Packages</div><div class="v">{{ $pkgCount }}</div></div>
                        <div class="stat"><div class="k">Modules</div><div class="v">{{ $enabledModuleCount }} / {{ $totalModuleCount }}</div></div>
                    </div>

                    <div class="home-lead">Configure your build</div>
                    <div class="rcardgrid two">
                        <div class="rcard home-nav" @click="section = 'version'">
                            <span class="big-ic"><svg width="17" height="17" viewBox="0 0 16 16" fill="none"><path d="M8 1.5l5.5 3v7L8 14.5l-5.5-3v-7z" stroke="currentColor" stroke-width="1.3"/></svg></span>
                            <div><div class="rt">Version</div><div class="rd">Choose the Mage-OS release line your metapackage targets.</div></div>
                        </div>
                        @if ($modulargentoAvailable)
                            <div class="rcard home-nav" @click="section = 'distribution'">
                                <span class="big-ic"><svg width="17" height="17" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="9" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="2" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="9" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/></svg></span>
                                <div><div class="rt">Distribution</div><div class="rd">Standard Mage-OS or the fully-modular modulargento build.</div></div>
                            </div>
                        @endif
                        <div class="rcard home-nav" @click="section = 'profile'">
                            <span class="big-ic"><svg width="17" height="17" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5.5" r="2.5" stroke="currentColor" stroke-width="1.3"/><path d="M3 13c0-2.5 2.2-4 5-4s5 1.5 5 4" stroke="currentColor" stroke-width="1.3"/></svg></span>
                            <div><div class="rt">Profile</div><div class="rd">Start from a preset — Full, Headless or Lite — then tweak.</div></div>
                        </div>
                        <div class="rcard home-nav" @click="section = 'theme'">
                            <span class="big-ic"><svg width="17" height="17" viewBox="0 0 16 16" fill="none"><rect x="2" y="3" width="12" height="10" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M2 6h12" stroke="currentColor" stroke-width="1.3"/></svg></span>
                            <div><div class="rt">Theme &amp; Checkout</div><div class="rd">Pick the storefront theme and checkout experience.</div></div>
                        </div>
                        <div class="rcard home-nav" @click="section = 'modules'">
                            <span class="big-ic"><svg width="17" height="17" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.3"/><rect x="9" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.3"/><rect x="2" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.3"/><rect x="9" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.3"/></svg></span>
                            <div><div class="rt">Modules</div><div class="rd">Toggle individual modules, grouped by category.</div></div>
                        </div>
                        <div class="rcard home-nav" @click="section = 'layers'">
                            <span class="big-ic"><svg width="17" height="17" viewBox="0 0 16 16" fill="none"><path d="M8 2l6 3-6 3-6-3z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M2 8l6 3 6-3M2 11l6 3 6-3" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg></span>
                            <div><div class="rt">Layers</div><div class="rd">Cross-cutting concerns like GraphQL and message queues.</div></div>
                        </div>
                    </div>

                    <div class="infonote">
                        <svg class="ic" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3"/><path d="M8 7.5v3.5M8 5.2v.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                        <p>Ready to go? Copy the generated <b>composer.json</b> from the panel on the right, or run <code>bougie init</code> with the one-liner above it — no clone required. Hit <b>Save &amp; share</b> for a link to this exact configuration.</p>
                    </div>
                </div>
            </section>

            {{-- VERSION --}}
            <section class="sec" x-show="section === 'version'" x-cloak>
                <div class="sec-head">
                    <h1>Mage-OS version</h1>
                    <div class="sub">The release line your project metapackage targets. The latest stable is recommended for new builds.</div>
                </div>
                <div class="sec-body">
                    @php $locked = $distribution === 'modulargento'; @endphp
                    <div class="rcardgrid">
                        @foreach (array_reverse($versions) as $v)
                            @php
                                $isSel = $v === $version;
                                $disabled = $locked && ! $isSel;
                            @endphp
                            <div class="rcard {{ $isSel ? 'sel' : '' }} {{ $disabled ? 'disabled' : '' }}"
                                 @if (! $disabled && ! $isSel) wire:click="$set('version', '{{ $v }}')" @endif>
                                <span class="rdot"></span>
                                <div>
                                    <div class="rt">
                                        {{ $v }}
                                        @if ($v === $latestStable)
                                            <span class="badge green">latest stable</span>
                                        @elseif (str_contains($v, '-p'))
                                            <span class="badge gray">security</span>
                                        @endif
                                    </div>
                                    <div class="rd">Mage-OS {{ $v }} release line.</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if ($locked)
                        <div class="infonote">
                            <svg class="ic" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3"/><path d="M8 7.5v3.5M8 5.2v.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                            <p>Locked to {{ $version }} — the fully-modular distribution is only published for this version. Switch to Standard in <b>Distribution</b> to change it.</p>
                        </div>
                    @endif
                </div>
            </section>

            {{-- DISTRIBUTION --}}
            @if ($modulargentoAvailable)
                <section class="sec" x-show="section === 'distribution'" x-cloak>
                    <div class="sec-head">
                        <h1>Distribution</h1>
                        <div class="sub">How Mage-OS is packaged. The modular distribution splits the monolith into independently versioned Composer packages so you can upgrade module-by-module.</div>
                    </div>
                    <div class="sec-body">
                        <div class="rcardgrid two">
                            <div class="rcard {{ $distribution === 'standard' ? 'sel' : '' }}"
                                 @if ($distribution !== 'standard') wire:click="$set('distribution', 'standard')" @endif>
                                <span class="big-ic"><svg width="17" height="17" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.4"/></svg></span>
                                <div>
                                    <div class="rt">Standard Mage-OS</div>
                                    <div class="rd">The canonical <code>mage-os/*</code> metapackages. Some sets are locked on.</div>
                                </div>
                            </div>
                            <div class="rcard {{ $distribution === 'modulargento' ? 'sel' : '' }}"
                                 @if ($distribution !== 'modulargento') wire:click="$set('distribution', 'modulargento')" @endif>
                                <span class="big-ic"><svg width="17" height="17" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="9" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="2" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="9" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/></svg></span>
                                <div>
                                    <div class="rt">Fully modular <span class="badge auto">modulargento</span></div>
                                    <div class="rd">Every module as an independently versioned package — decoupled, so every set below is removable.</div>
                                </div>
                            </div>
                        </div>
                        <div class="infonote">
                            <svg class="ic" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3"/><path d="M8 7.5v3.5M8 5.2v.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                            <p>Modular adds the <code>modulargento.cresset.tools</code> Composer repository and swaps the root package to <code>modulargento/project-community-edition</code>. See the generated <b>composer.json</b> on the right.</p>
                        </div>
                    </div>
                </section>
            @endif

            {{-- PROFILE --}}
            <section class="sec" x-show="section === 'profile'" x-cloak>
                <div class="sec-head">
                    <h1>Profile</h1>
                    <div class="sub">A starting preset that sets sensible module defaults. Picking one reseeds your selections — you can override any individual module afterwards.</div>
                </div>
                <div class="sec-body">
                    <div class="rcardgrid">
                        @foreach ($profileDefs as $name => $profileDef)
                            <div class="rcard {{ $profile === $name ? 'sel' : '' }}"
                                 @if ($profile !== $name) wire:click="setProfile('{{ $name }}')" @endif>
                                <span class="rdot"></span>
                                <div>
                                    <div class="rt">{{ $profileDef['label'] }}</div>
                                    <div class="rd">{{ $profileDef['description'] ?? '' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- THEME & CHECKOUT --}}
            <section class="sec" x-show="section === 'theme'" x-cloak>
                <div class="sec-head">
                    <h1>Theme &amp; Checkout</h1>
                    <div class="sub">Storefront rendering and the checkout experience. Some checkouts depend on a matching theme.</div>
                </div>
                <div class="sec-body">
                    <div class="colpair">
                        @php $defsHelper = app(\App\Services\Definitions::class); @endphp
                        @foreach ($profileGroupDefs as $groupName => $group)
                            <div>
                                <div class="col-lbl">{{ $group['label'] }}</div>
                                <div class="rcardgrid">
                                    @foreach ($group['options'] as $opt)
                                        @php
                                            $available = $defsHelper->optionMeetsRequires($groupName, $opt['name'], $profileGroups);
                                            $prefer = $defsHelper->optionPreferAlternative($groupName, $opt['name'], $profileGroups);
                                            $isPicked = ($profileGroups[$groupName] ?? null) === $opt['name'];
                                            $hint = '';
                                            if (! $available) {
                                                $reqs = [];
                                                foreach (($opt['requires']['profileGroups'] ?? []) as $g => $needed) {
                                                    $reqOpt = collect($profileGroupDefs[$g]['options'] ?? [])->firstWhere('name', $needed);
                                                    $reqs[] = ($profileGroupDefs[$g]['label'] ?? $g).' = '.($reqOpt['label'] ?? $needed);
                                                }
                                                $hint = 'Needs '.implode(', ', $reqs).'.';
                                            } elseif ($prefer !== null) {
                                                $altOpt = collect($group['options'] ?? [])->firstWhere('name', $prefer['use']);
                                                $altLabel = $altOpt['label'] ?? $prefer['use'];
                                                $hint = isset($prefer['reason'])
                                                    ? "Prefer {$altLabel} — {$prefer['reason']}."
                                                    : "Prefer {$altLabel}.";
                                            }
                                        @endphp
                                        <div class="rcard {{ $isPicked ? 'sel' : '' }} {{ $available ? '' : 'disabled' }}"
                                             @if ($available && ! $isPicked) wire:click="$set('profileGroups.{{ $groupName }}', '{{ $opt['name'] }}')" @endif>
                                            <span class="rdot"></span>
                                            <div style="flex:1;">
                                                <div class="rt">{{ $opt['label'] }}</div>
                                                @if ($hint !== '')
                                                    <div class="rd">{{ $hint }}</div>
                                                @endif

                                                {{-- Variants / subtoggles for the picked option. --}}
                                                @if ($isPicked && $available && ! empty($opt['variants']))
                                                    @php $activeVariant = $defsHelper->optionActiveVariant($groupName, $opt['name'], $profileGroups, $optionVariants); @endphp
                                                    <div class="subopts" @click.stop>
                                                        @foreach ($opt['variants'] as $variant)
                                                            @php
                                                                $vAvailable = true;
                                                                foreach (($variant['requires']['profileGroups'] ?? []) as $g => $needed) {
                                                                    if (($profileGroups[$g] ?? null) !== $needed) { $vAvailable = false; break; }
                                                                }
                                                            @endphp
                                                            <label class="chk mini opt-radio {{ $vAvailable ? '' : 'is-disabled' }}"
                                                                   wire:key="variant-{{ $groupName }}-{{ $opt['name'] }}-{{ $variant['name'] }}-active{{ $activeVariant }}">
                                                                <span class="dot {{ $activeVariant === $variant['name'] ? 'on' : '' }}"></span>
                                                                <input type="radio" class="vh"
                                                                    name="variant-{{ $groupName }}-{{ $opt['name'] }}"
                                                                    wire:click="setOptionVariant('{{ $groupName }}', '{{ $opt['name'] }}', '{{ $variant['name'] }}')"
                                                                    @if ($activeVariant === $variant['name']) checked @endif
                                                                    @disabled(! $vAvailable)>
                                                                <span class="label">{{ $variant['label'] }}</span>
                                                            </label>
                                                            @if ($activeVariant === $variant['name'] && $vAvailable && ! empty($variant['subtoggles']))
                                                                <div class="subopts" style="margin-left:14px;">
                                                                    @foreach ($variant['subtoggles'] as $sub)
                                                                        <label class="chk mini"
                                                                               :class="{ on: $wire.enabledOptionSubtoggles.includes('{{ $groupName }}.{{ $opt['name'] }}.{{ $variant['name'] }}.{{ $sub['name'] }}') }">
                                                                            <span class="box"></span>
                                                                            <input type="checkbox" class="vh"
                                                                                wire:model.live="enabledOptionSubtoggles"
                                                                                value="{{ $groupName }}.{{ $opt['name'] }}.{{ $variant['name'] }}.{{ $sub['name'] }}">
                                                                            <span class="label">{{ $sub['label'] }}</span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @elseif ($isPicked && $available && ! empty($opt['subtoggles']))
                                                    <div class="subopts" @click.stop>
                                                        @foreach ($opt['subtoggles'] as $sub)
                                                            <label class="chk mini"
                                                                   :class="{ on: $wire.enabledOptionSubtoggles.includes('{{ $groupName }}.{{ $opt['name'] }}.{{ $sub['name'] }}') }">
                                                                <span class="box"></span>
                                                                <input type="checkbox" class="vh"
                                                                    wire:model.live="enabledOptionSubtoggles"
                                                                    value="{{ $groupName }}.{{ $opt['name'] }}.{{ $sub['name'] }}">
                                                                <span class="label">{{ $sub['label'] }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- ADD-ONS --}}
            <section class="sec" x-show="section === 'addons'" x-cloak>
                <div class="sec-head">
                    <h1>Add-ons</h1>
                    <div class="sub">Optional third-party packages layered on top of your build. Greyed-out items are forced by your current profile-group choices.</div>
                </div>
                <div class="sec-body">
                    <div class="langgrid addon-grid" id="addon-list">
                        @foreach ($addonDefs as $name => $addon)
                            @php $isForced = in_array($name, $this->forcedAddons, true); @endphp
                            <label class="langcard addon-card {{ $isForced ? 'forced' : '' }}"
                                   wire:key="addon-{{ $name }}-{{ $isForced ? 'forced' : 'free' }}"
                                   :class="{ on: {{ $isForced ? 'true' : "\$wire.enabledAddons.includes('$name')" }} }">
                                @if ($isForced)
                                    <input type="checkbox" class="vh" disabled checked>
                                @else
                                    <input type="checkbox" class="vh" wire:model.live="enabledAddons" value="{{ $name }}">
                                @endif
                                <div class="grow">
                                    <div class="ln">{{ $addon['label'] }} @if ($isForced)<span class="badge req">required</span>@endif</div>
                                    <div class="lc addon-desc">{{ $addon['description'] ?? '' }}</div>
                                </div>
                                <span class="tick"></span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- MODULES --}}
            <section class="sec" x-show="section === 'modules'" x-cloak
                x-data="{
                    q: '', filter: 'all', folded: {},
                    en(name){ return $wire.enabledSets.includes(name); },
                    rowVisible(name, required, hay){
                        if (this.filter === 'enabled' && !this.en(name)) return false;
                        if (this.filter === 'off' && this.en(name)) return false;
                        if (this.filter === 'required' && !required) return false;
                        const q = this.q.trim().toLowerCase();
                        if (q && hay.indexOf(q) === -1) return false;
                        return true;
                    },
                    catVisible(mods){ return mods.some(m => this.rowVisible(m.name, m.required, m.hay)); },
                    onCount(mods){ return mods.filter(m => this.en(m.name)).length; },
                    anyVisible: true,
                }"
                x-init="$watch('q', () => {}); $watch('filter', () => {})">
                <div class="sec-head">
                    <h1>Modules</h1>
                    <div class="sub">Toggle individual Mage-OS modules. Greyed-out items are <b>required</b> by your distribution &amp; can't be removed.</div>
                    <div class="toolbar">
                        <div class="search">
                            <svg class="ic" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="4.5" stroke="currentColor" stroke-width="1.4"/><path d="M11 11l3 3" stroke="currentColor" stroke-width="1.4"/></svg>
                            <input class="input" placeholder="Filter modules…" autocomplete="off" x-model="q">
                        </div>
                        <div class="filterchips">
                            <span class="fchip" :class="{ on: filter === 'all' }" @click="filter = 'all'">All</span>
                            <span class="fchip" :class="{ on: filter === 'enabled' }" @click="filter = 'enabled'">Enabled</span>
                            <span class="fchip" :class="{ on: filter === 'required' }" @click="filter = 'required'">Required</span>
                            <span class="fchip" :class="{ on: filter === 'off' }" @click="filter = 'off'">Off</span>
                        </div>
                    </div>
                </div>
                <div class="sec-body">
                    @foreach ($moduleGroups as $gi => $mg)
                        @php
                            $cid = 'cat'.$gi;
                            // Compact JS descriptor list for this category (filter + counts).
                            $jsMods = [];
                            foreach ($mg['sets'] as $sName => $sDef) {
                                $removable = $setRemovable[$sName] ?? true;
                                $hay = strtolower(trim(
                                    $sName.' '.($sDef['label'] ?? '').' '.($sDef['description'] ?? '').' '
                                    .collect($sDef['subtoggles'] ?? [])->map(fn ($s) => ($s['label'] ?? '').' '.($s['description'] ?? ''))->implode(' ')
                                ));
                                $jsMods[] = ['name' => $sName, 'required' => ! $removable, 'hay' => $hay];
                            }
                        @endphp
                        <div class="catblock" wire:key="cat-{{ $cid }}"
                             x-data="{ mods: @js($jsMods), cid: '{{ $cid }}' }"
                             :class="{ folded: folded[cid] }"
                             x-show="catVisible(mods)">
                            <div class="cathead" @click="folded[cid] = !folded[cid]">
                                <span class="ct-name">{{ $mg['label'] }}</span>
                                <span class="ct-line"></span>
                                <span class="ct-count"><b x-text="onCount(mods)">{{ collect($mg['sets'])->keys()->intersect($enabledSets)->count() }}</b> / {{ count($mg['sets']) }} on</span>
                                <svg class="ct-fold" viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                            <div class="modgrid">
                                @foreach ($mg['sets'] as $name => $set)
                                    @php
                                        $removable = $setRemovable[$name] ?? true;
                                        $parentEnabled = in_array($name, $enabledSets, true);
                                        $setReqSet = $set['requires']['set'] ?? null;
                                        $setReqMet = $setReqSet === null || in_array($setReqSet, $enabledSets, true);
                                        $setReqLabel = $setReqSet ? ($setDefs[$setReqSet]['label'] ?? $setReqSet) : null;
                                        $setDisabled = ! $removable || ! $setReqMet;
                                        $hay = strtolower(trim(
                                            $name.' '.($set['label'] ?? '').' '.($set['description'] ?? '').' '
                                            .collect($set['subtoggles'] ?? [])->map(fn ($s) => ($s['label'] ?? '').' '.($s['description'] ?? ''))->implode(' ')
                                        ));
                                    @endphp
                                    @php $onExpr = $removable ? '$wire.enabledSets.includes('.\Illuminate\Support\Js::from($name).')' : 'true'; @endphp
                                    <div class="modcard" wire:key="mod-{{ $name }}-{{ $setReqMet ? 'ok' : 'needs' }}"
                                         x-show="rowVisible(@js($name), @js(! $removable), @js($hay))">
                                        <label class="chk {{ $setDisabled ? 'disabled' : '' }}"
                                               :class="{ on: {{ $onExpr }} }">
                                            <input type="checkbox" class="vh" wire:model.live="enabledSets" value="{{ $name }}" @disabled($setDisabled)>
                                            <span class="box"></span>
                                            <span class="mtext">
                                                <span class="label">{{ $set['label'] }}
                                                    @if ($setReqSet && ! $setReqMet)
                                                        <span class="badge gray" title="Requires the {{ $setReqLabel }} set — it can't function without it.">needs {{ $setReqLabel }}</span>
                                                    @endif
                                                    @unless ($removable)
                                                        <span class="badge req" title="Hard cross-module dependencies in stock Mage-OS — can't be removed without breaking di:compile or setup:install.">required</span>
                                                    @endunless
                                                </span>
                                                <span class="desc">{{ $set['description'] ?? '' }}</span>
                                                @if (! empty($set['subtoggles']))
                                                    <span class="subopts" @click.stop>
                                                        @foreach ($set['subtoggles'] as $sub)
                                                            @php
                                                                $reqSet = $sub['requires']['set'] ?? null;
                                                                $reqMet = $reqSet === null || in_array($reqSet, $enabledSets, true);
                                                                $subDisabled = ! $parentEnabled || ! $reqMet;
                                                                $reqLabel = $reqSet ? ($setDefs[$reqSet]['label'] ?? $reqSet) : null;
                                                            @endphp
                                                            <label class="chk mini {{ $subDisabled ? 'is-disabled' : '' }}"
                                                                   wire:key="sub-{{ $name }}-{{ $sub['name'] }}-{{ $subDisabled ? 'off' : 'on' }}"
                                                                   :class="{ on: $wire.enabledSubtoggles.includes('{{ $name }}.{{ $sub['name'] }}') }">
                                                                <span class="box"></span>
                                                                <input type="checkbox" class="vh"
                                                                    wire:model.live="enabledSubtoggles"
                                                                    value="{{ $name }}.{{ $sub['name'] }}"
                                                                    @disabled($subDisabled)>
                                                                <span class="label">{{ $sub['label'] }}
                                                                    @if ($reqSet && ! $reqMet)
                                                                        <span class="badge gray" title="Requires the {{ $reqLabel }} module">needs {{ $reqLabel }}</span>
                                                                    @endif
                                                                </span>
                                                            </label>
                                                        @endforeach
                                                    </span>
                                                @endif
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    @php
                        $allJsMods = [];
                        foreach ($moduleGroups as $mg) {
                            foreach ($mg['sets'] as $sName => $sDef) {
                                $removable = $setRemovable[$sName] ?? true;
                                $hay = strtolower(trim(
                                    $sName.' '.($sDef['label'] ?? '').' '.($sDef['description'] ?? '').' '
                                    .collect($sDef['subtoggles'] ?? [])->map(fn ($s) => ($s['label'] ?? '').' '.($s['description'] ?? ''))->implode(' ')
                                ));
                                $allJsMods[] = ['name' => $sName, 'required' => ! $removable, 'hay' => $hay];
                            }
                        }
                    @endphp
                    <p class="empty-note" x-show="!@js($allJsMods).some(m => rowVisible(m.name, m.required, m.hay))" x-cloak>No modules match.</p>
                </div>
            </section>

            {{-- LANGUAGES --}}
            @if (count($languageDefs) > 0)
                <section class="sec" x-show="section === 'languages'" x-cloak>
                    <div class="sec-head">
                        <h1>Languages</h1>
                        <div class="sub">Locale packs bundled into the build. Each adds a <code>mage-os/language-*</code> package; disabled languages are added to <code>replace</code>.</div>
                    </div>
                    <div class="sec-body">
                        <div class="langgrid">
                            @foreach ($languageDefs as $name => $lang)
                                @php
                                    $loc = \Illuminate\Support\Str::after($name, 'language-');
                                    $parts = explode('_', $loc);
                                    $code = $parts[0];
                                    $locale = collect($parts)->map(fn ($p, $i) => $i === 0 ? $p : (strlen($p) === 2 ? strtoupper($p) : ucfirst($p)))->implode('_');
                                    $lname = trim(\Illuminate\Support\Str::before($lang['label'] ?? $name, '('));
                                @endphp
                                <label class="langcard" wire:key="lang-{{ $name }}"
                                       :class="{ on: $wire.enabledSets.includes(@js($name)) }">
                                    <input type="checkbox" class="vh" wire:model.live="enabledSets" value="{{ $name }}">
                                    <span class="lcode">{{ $code }}</span>
                                    <div><div class="ln">{{ $lname }}</div><div class="lc">{{ $locale }}</div></div>
                                    <span class="grow"></span>
                                    <span class="tick"></span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            {{-- LAYERS --}}
            <section class="sec" x-show="section === 'layers'" x-cloak>
                <div class="sec-head">
                    <h1>Layers</h1>
                    <div class="sub">Cross-cutting architectural concerns. Stock layers are on by default; non-stock layers are managed by your profile-group choices.</div>
                </div>
                <div class="sec-body">
                    <div class="layerlist" id="layer-list">
                        @foreach ($layerDefs as $name => $layer)
                            @php
                                $isStock = ($layer['stock'] ?? true) !== false;
                                $isForced = in_array($name, $this->forcedLayers, true);
                            @endphp
                            @if ($isStock)
                                @php $removable = $layerRemovable[$name] ?? true; @endphp
                                <label class="layerrow {{ $removable ? '' : 'forced' }}" wire:key="layer-{{ $name }}">
                                    <div class="grow">
                                        <div class="lt">{{ $layer['label'] }}
                                            @unless ($removable)<span class="badge req" title="Wired into stock Mage-OS bootstrap — can't be removed without breaking the install.">required</span>@endunless
                                        </div>
                                        <div class="ld">{{ $layer['description'] ?? '' }}</div>
                                    </div>
                                    <input type="checkbox" class="vh" wire:model.live="enabledStockLayers" value="{{ $name }}" @disabled(! $removable)>
                                    @php $swExpr = $removable ? '$wire.enabledStockLayers.includes('.\Illuminate\Support\Js::from($name).')' : 'true'; @endphp
                                    <span class="switch {{ $removable ? '' : 'switch-locked' }}" :class="{ on: {{ $swExpr }} }"></span>
                                </label>
                            @else
                                {{-- Non-stock layer: profile-group-managed, never user-toggled. --}}
                                <div class="layerrow forced" wire:key="layer-{{ $name }}">
                                    <div class="grow">
                                        <div class="lt">{{ $layer['label'] }}
                                            @if ($isForced)<span class="badge req">required</span>@else<span class="badge auto">auto</span>@endif
                                        </div>
                                        <div class="ld">{{ $layer['description'] ?? '' }}</div>
                                    </div>
                                    <span class="switch {{ $isForced ? 'on' : '' }} switch-locked"></span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </section>

        </main>

        {{-- ============ RIGHT OUTPUT ============ --}}
        <aside class="out">
            <div class="out-bougie">
                <div class="h"><svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M8 1.5l1.8 4.2 4.7.4-3.6 3 1.1 4.5L8 11.2 3.9 13.6 5 9.1 1.4 6.1l4.7-.4z" stroke="#4338ca" stroke-width="1.2" stroke-linejoin="round"/></svg> Try it with bougie</div>
                @if ($this->effectiveSavedId)
                    <p>Run <b>this exact configuration</b> with <a href="https://bougie.tools" target="_blank" rel="noopener">bougie</a> — one command, no clone:</p>
                    <div class="cmd-row"><pre class="cmd"><code># Install bougie if you don't have it yet
curl -LsSf https://bougie.tools/install.sh | sh
# Start it up!
bougie init --starter {{ $this->starterArg }} --start</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy command">{!! $copyIcon !!}</button></div>
                    <small class="bougie-note">Shareable link to this build: <code>{{ $this->starterArg }}</code></small>
                @else
                    <p>
                        @if ($savedId)
                            You've changed the configuration since it was last saved. <b>Save again</b> to refresh the one-command install link for this build.
                        @else
                            <b>Save your configuration</b> to get a personal one-command bougie install link for <b>this exact build</b> — no clone, no copy-paste.
                        @endif
                    </p>
                    <button type="button" class="btn btn-primary btn-sm bougie-save" wire:click="save">
                        <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M3.5 2.5h7L13 5v8.5H3.5z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M5.5 2.5v3.5h4V2.5M5.5 13.5v-3.5h5v3.5" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg>
                        {{ $savedId ? 'Save again & refresh link' : 'Save & get install command' }}
                    </button>
                    <details class="bougie-default">
                        <summary>Or run the default Mage-OS starter now</summary>
                        <p class="bougie-default-note">Installs stock Mage-OS, not the configuration above.</p>
                        <div class="cmd-row"><pre class="cmd"><code># Install bougie if you don't have it yet
curl -LsSf https://bougie.tools/install.sh | sh
# Start it up!
bougie init --starter mageos --start</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy command">{!! $copyIcon !!}</button></div>
                    </details>
                @endif
            </div>

            <div class="out-tabs">
                <div class="out-tab" :class="{ active: otab === 'composer' }" @click="otab = 'composer'">composer.json</div>
                <div class="out-tab" :class="{ active: otab === 'tree' }" @click="otab = 'tree'">Install tree<span class="n">{{ $this->installTree['count'] }}</span></div>
                @if ($this->usesHyva)
                    {{-- Tab appears (and auto-activates) the moment a Hyvä build is selected. x-init
                         fires once when Livewire morphs this element in, so it won't hijack the tab
                         again after the user clicks away. --}}
                    <div class="out-tab out-tab-hyva" :class="{ active: otab === 'hyva' }" @click="otab = 'hyva'" x-init="otab = 'hyva'">★ Hyvä setup</div>
                @else
                    {{-- When Hyvä is switched back off, drop the user off the now-gone tab. --}}
                    <template x-if="otab === 'hyva'"><span x-init="otab = 'composer'"></span></template>
                @endif
            </div>

            <div class="out-body scrollbox">
                <div class="opane" x-show="otab === 'composer'">
                    <pre class="composer" wire:ignore><code id="composer-out" class="language-json composer-code">{{ $this->composerJson }}</code></pre>
                </div>
                <div class="opane" x-show="otab === 'tree'" x-cloak>
                    @php $tree = $this->installTree; @endphp
                    @if ($tree['missing'])
                        <p class="warn">
                            @if ($tree['fallbackVersion'])
                                No baked graph for {{ $tree['version'] }} yet — showing {{ $tree['fallbackVersion'] }}.
                            @else
                                No baked graph available. Run <code>php artisan mageos:catalog:update</code>.
                            @endif
                        </p>
                    @endif
                    @if ($tree['count'] === 0 && ! $tree['missing'])
                        <p class="tree-empty">No packages — nothing to show.</p>
                    @else
                        <input type="text" id="install-tree-filter" placeholder="Filter packages…" autocomplete="off"
                            class="input tree-filter" oninput="filterInstallTree(this.value)">
                        <div class="install-tree-types">
                            @foreach ($tree['byType'] as $type => $n)
                                <span>{{ $type }}: {{ $n }}</span>
                            @endforeach
                            <span class="tree-actions">
                                <a href="#" onclick="installTreeToggleAll(true);return false;">expand all</a> ·
                                <a href="#" onclick="installTreeToggleAll(false);return false;">collapse</a>
                            </span>
                        </div>
                        <div id="install-tree-root" class="install-tree-root">
                            @include('livewire.partials.install-tree-node', ['nodes' => $tree['tree'], 'depth' => 0])
                        </div>
                    @endif
                </div>
                @if ($this->usesHyva)
                    @php
                        $token = $hyvaToken !== '' ? $hyvaToken : 'YOUR_HYVA_TOKEN';
                        $project = $hyvaProject !== '' ? $hyvaProject : 'yourProjectName';
                    @endphp
                    <div class="opane hyva-pane" x-show="otab === 'hyva'" x-cloak>
                        <p class="hyva-intro">
                            The Hyvä Theme is free of charge but requires a packagist token.
                            Register at <a href="https://www.hyva.io/" target="_blank" rel="noopener">hyva.io</a>
                            to get your free token and project name, then run these commands in your project root <strong>before</strong>
                            <code>composer install</code>.
                            See the <a href="https://docs.hyva.io/hyva-themes/getting-started/index.html" target="_blank" rel="noopener">official docs</a>.
                        </p>

                        <div class="hyva-fields">
                            <label>
                                <span>Hyvä token</span>
                                <input type="text" wire:model.live.debounce.300ms="hyvaToken" placeholder="YOUR_HYVA_TOKEN" autocomplete="off">
                            </label>
                            <label>
                                <span>Project name</span>
                                <input type="text" wire:model.live.debounce.300ms="hyvaProject" placeholder="yourProjectName" autocomplete="off">
                            </label>
                        </div>

                        <ol class="hyva-steps">
                            <li>
                                <span class="step-label">Configure composer auth</span>
                                <div class="cmd-row"><pre class="cmd"><code>composer config --auth http-basic.hyva-themes.repo.packagist.com token {{ $token }}</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div>
                            </li>
                            @if ($hyvaProject === '')
                                <li>
                                    <span class="step-label">Add the Hyvä private repository</span>
                                    <div class="cmd-row"><pre class="cmd"><code>composer config repositories.hyva-private composer https://hyva-themes.repo.packagist.com/{{ $project }}/</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div>
                                    <small>Skip this once you've filled in the project name above — the repo will be baked into the generated <code>composer.json</code>.</small>
                                </li>
                            @endif
                            <li>
                                <span class="step-label">Install dependencies</span>
                                <div class="cmd-row"><pre class="cmd"><code>composer install</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div>
                            </li>
                            <li>
                                <span class="step-label">Activate the theme in Magento</span>
                                <div class="cmd-row"><pre class="cmd"><code>bin/magento setup:upgrade
bin/magento config:set design/theme/theme_id frontend/Hyva/default
bin/magento cache:flush</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div>
                                <small>Or pick <code>Hyva/default</code> from <em>Content → Design → Configuration</em> in the admin.</small>
                            </li>
                            <li>
                                <span class="step-label">Disable the legacy Magento captcha</span>
                                <div class="cmd-row"><pre class="cmd"><code>bin/magento config:set customer/captcha/enable 0</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div>
                                <small>Hyvä doesn't support the legacy captcha; storefront forms break with it on. Swap in Google ReCaptcha (V2/V3) from the admin if you still want bot protection.</small>
                            </li>
                        </ol>
                    </div>
                @endif
            </div>

            <div class="out-foot">
                <span class="countchip"><b>require</b> {{ $this->requireCount }}</span>
                <span class="countchip"><b>replace</b> {{ $this->replaceCount }}</span>
                <span class="sp"></span>
                <button class="btn btn-ghost btn-sm" wire:click="save">Save &amp; share</button>
                <button class="btn btn-primary btn-sm" onclick="copyComposer()">Copy</button>
            </div>
        </aside>
    </div>
</div>

{{-- ============================================================
     MOBILE — single-column accordion rendering of the same
     configurator. Hidden above the phone breakpoint (see the
     .device rules in the layout); shares the root Alpine state
     (otab) and binds to the same Livewire properties as the
     desktop shell, so the two stay perfectly in sync.
     ============================================================ --}}
<div class="device">
    <div class="appbar">
        <span class="brand"><span class="glyph">M</span>mageos-maker</span>
        @if ($this->effectiveSavedId)
            <span class="status">Saved <code>{{ $this->effectiveSavedId }}</code></span>
        @elseif ($savedId)
            <span class="status">Modified</span>
        @endif
        <span class="sp"></span>
        <a class="ghost" href="{{ route('configurator.index') }}" wire:navigate>Reset</a>
    </div>

    <div class="m-summary">
        <div class="pf">{{ $profileLabel }}</div>
        <div class="meta">v{{ $version }} · {{ $distLabel }} · {{ $themeLabel }} · {{ $checkoutLabel }}</div>
        <div class="row2">
            <div class="stat"><div class="n">{{ $pkgCount }}</div><div class="l">packages</div></div>
            <div class="stat"><div class="n">{{ $enabledModuleCount }}</div><div class="l">modules</div></div>
            <div class="stat"><div class="n">{{ $enabledLanguageCount }}</div><div class="l">languages</div></div>
        </div>
    </div>

    {{-- ===== CONFIGURATION ===== --}}
    <div class="m-grouplbl">Configuration</div>

    {{-- Version --}}
    <div class="acc" x-data="{ open: false }" :class="{ open }">
        <div class="acc-head" @click="open = !open">
            <svg class="ic" viewBox="0 0 16 16" fill="none"><path d="M8 1.5l5.5 3v7L8 14.5l-5.5-3v-7z" stroke="currentColor" stroke-width="1.3"/></svg>
            <span class="nm">Version</span><span class="val">{{ $version }}</span>
            <svg class="chev" viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="acc-body">
            @php $mLocked = $distribution === 'modulargento'; @endphp
            <div class="rcardgrid">
                @foreach (array_reverse($versions) as $v)
                    @php $isSel = $v === $version; $disabled = $mLocked && ! $isSel; @endphp
                    <div class="rcard {{ $isSel ? 'sel' : '' }} {{ $disabled ? 'disabled' : '' }}"
                         @if (! $disabled && ! $isSel) wire:click="$set('version', '{{ $v }}')" @endif>
                        <span class="rdot"></span>
                        <div>
                            <div class="rt">{{ $v }}
                                @if ($v === $latestStable)<span class="badge green">latest stable</span>
                                @elseif (str_contains($v, '-p'))<span class="badge gray">security</span>@endif
                            </div>
                            <div class="rd">Mage-OS {{ $v }} release line.</div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($mLocked)
                <div class="infonote"><svg class="ic" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3"/><path d="M8 7.5v3.5M8 5.2v.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg><p>Locked to {{ $version }} — the fully-modular distribution is only published for this version. Switch to Standard in Distribution to change it.</p></div>
            @endif
        </div>
    </div>

    {{-- Distribution --}}
    @if ($modulargentoAvailable)
        <div class="acc" x-data="{ open: false }" :class="{ open }">
            <div class="acc-head" @click="open = !open">
                <svg class="ic" viewBox="0 0 16 16" fill="none"><path d="M8 2l6 3v6l-6 3-6-3V5z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M8 2v12M2 5l6 3 6-3" stroke="currentColor" stroke-width="1.1"/></svg>
                <span class="nm">Distribution</span><span class="val">{{ $distLabel }}</span>
                <svg class="chev" viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="acc-body">
                <div class="rcardgrid">
                    <div class="rcard {{ $distribution === 'standard' ? 'sel' : '' }}"
                         @if ($distribution !== 'standard') wire:click="$set('distribution', 'standard')" @endif>
                        <span class="rdot"></span>
                        <div><div class="rt">Standard Mage-OS</div><div class="rd">The canonical <code>mage-os/*</code> metapackages. Some sets are locked on.</div></div>
                    </div>
                    <div class="rcard {{ $distribution === 'modulargento' ? 'sel' : '' }}"
                         @if ($distribution !== 'modulargento') wire:click="$set('distribution', 'modulargento')" @endif>
                        <span class="rdot"></span>
                        <div><div class="rt">Fully modular <span class="badge auto">modulargento</span></div><div class="rd">Every module as an independently versioned package — decoupled, so every set below is removable.</div></div>
                    </div>
                </div>
                <div class="infonote"><svg class="ic" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3"/><path d="M8 7.5v3.5M8 5.2v.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg><p>Modular adds the <code>modulargento.cresset.tools</code> Composer repository and swaps the root package to <code>modulargento/project-community-edition</code>.</p></div>
            </div>
        </div>
    @endif

    {{-- Profile --}}
    <div class="acc" x-data="{ open: false }" :class="{ open }">
        <div class="acc-head" @click="open = !open">
            <svg class="ic" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5.5" r="2.5" stroke="currentColor" stroke-width="1.3"/><path d="M3 13c0-2.5 2.2-4 5-4s5 1.5 5 4" stroke="currentColor" stroke-width="1.3"/></svg>
            <span class="nm">Profile</span><span class="val">{{ $profileLabel }}</span>
            <svg class="chev" viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="acc-body">
            <div class="rcardgrid">
                @foreach ($profileDefs as $name => $profileDef)
                    <div class="rcard {{ $profile === $name ? 'sel' : '' }}"
                         @if ($profile !== $name) wire:click="setProfile('{{ $name }}')" @endif>
                        <span class="rdot"></span>
                        <div><div class="rt">{{ $profileDef['label'] }}</div><div class="rd">{{ $profileDef['description'] ?? '' }}</div></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Theme & Checkout --}}
    <div class="acc" x-data="{ open: false }" :class="{ open }">
        <div class="acc-head" @click="open = !open">
            <svg class="ic" viewBox="0 0 16 16" fill="none"><rect x="2" y="3" width="12" height="10" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M2 6h12" stroke="currentColor" stroke-width="1.3"/></svg>
            <span class="nm">Theme &amp; Checkout</span><span class="val">{{ $themeLabel }} · {{ $checkoutLabel }}</span>
            <svg class="chev" viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="acc-body">
            @php $mDefs = app(\App\Services\Definitions::class); @endphp
            @foreach ($profileGroupDefs as $groupName => $group)
                <div class="col-lbl">{{ $group['label'] }}</div>
                <div class="rcardgrid">
                    @foreach ($group['options'] as $opt)
                        @php
                            $available = $mDefs->optionMeetsRequires($groupName, $opt['name'], $profileGroups);
                            $isPicked = ($profileGroups[$groupName] ?? null) === $opt['name'];
                            $hint = '';
                            if (! $available) {
                                $reqs = [];
                                foreach (($opt['requires']['profileGroups'] ?? []) as $g => $needed) {
                                    $reqOpt = collect($profileGroupDefs[$g]['options'] ?? [])->firstWhere('name', $needed);
                                    $reqs[] = ($profileGroupDefs[$g]['label'] ?? $g).' = '.($reqOpt['label'] ?? $needed);
                                }
                                $hint = 'Needs '.implode(', ', $reqs).'.';
                            }
                        @endphp
                        <div class="rcard {{ $isPicked ? 'sel' : '' }} {{ $available ? '' : 'disabled' }}"
                             @if ($available && ! $isPicked) wire:click="$set('profileGroups.{{ $groupName }}', '{{ $opt['name'] }}')" @endif>
                            <span class="rdot"></span>
                            <div><div class="rt">{{ $opt['label'] }}</div>@if ($hint !== '')<div class="rd">{{ $hint }}</div>@endif</div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    {{-- Add-ons --}}
    <div class="acc" x-data="{ open: false }" :class="{ open }">
        <div class="acc-head" @click="open = !open">
            <svg class="ic" viewBox="0 0 16 16" fill="none"><path d="M2.5 3h11l-1.2 7H4z" stroke="currentColor" stroke-width="1.3"/><circle cx="5.5" cy="13" r="1" fill="currentColor"/><circle cx="11" cy="13" r="1" fill="currentColor"/></svg>
            <span class="nm">Add-ons</span><span class="val">{{ $enabledAddonCount ?: 'None' }}</span>
            <svg class="chev" viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="acc-body">
            <div class="rcardgrid">
                @foreach ($addonDefs as $name => $addon)
                    @php $isForced = in_array($name, $this->forcedAddons, true); @endphp
                    <label class="langcard addon-card {{ $isForced ? 'forced' : '' }}"
                           wire:key="m-addon-{{ $name }}-{{ $isForced ? 'forced' : 'free' }}"
                           :class="{ on: {{ $isForced ? 'true' : "\$wire.enabledAddons.includes('$name')" }} }">
                        @if ($isForced)
                            <input type="checkbox" class="vh" disabled checked>
                        @else
                            <input type="checkbox" class="vh" wire:model.live="enabledAddons" value="{{ $name }}">
                        @endif
                        <div class="grow"><div class="ln">{{ $addon['label'] }} @if ($isForced)<span class="badge req">required</span>@endif</div><div class="lc">{{ $addon['description'] ?? '' }}</div></div>
                        <span class="tick"></span>
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ===== PACKAGES ===== --}}
    <div class="m-grouplbl">Packages</div>

    {{-- Modules (open by default) --}}
    <div class="acc open" x-data="{
            open: true, q: '', filter: 'all', folded: {},
            en(name){ return $wire.enabledSets.includes(name); },
            rowVisible(name, required, hay){
                if (this.filter === 'enabled' && !this.en(name)) return false;
                if (this.filter === 'off' && this.en(name)) return false;
                if (this.filter === 'required' && !required) return false;
                const q = this.q.trim().toLowerCase();
                if (q && hay.indexOf(q) === -1) return false;
                return true;
            },
            catVisible(mods){ return mods.some(m => this.rowVisible(m.name, m.required, m.hay)); },
            onCount(mods){ return mods.filter(m => this.en(m.name)).length; },
        }" :class="{ open }">
        <div class="acc-head" @click="open = !open">
            <svg class="ic" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.3"/><rect x="9" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.3"/><rect x="2" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.3"/><rect x="9" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.3"/></svg>
            <span class="nm">Modules</span><span class="val">{{ $enabledModuleCount }} / {{ $totalModuleCount }}</span>
            <svg class="chev" viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="acc-body" style="padding:0">
            <div class="m-tools">
                <div class="search"><svg class="ic" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="4.5" stroke="currentColor" stroke-width="1.4"/><path d="M11 11l3 3" stroke="currentColor" stroke-width="1.4"/></svg><input class="input" placeholder="Filter modules…" autocomplete="off" x-model="q"></div>
                <div class="filterchips">
                    <span class="fchip" :class="{ on: filter === 'all' }" @click="filter = 'all'">All</span>
                    <span class="fchip" :class="{ on: filter === 'enabled' }" @click="filter = 'enabled'">Enabled</span>
                    <span class="fchip" :class="{ on: filter === 'required' }" @click="filter = 'required'">Required</span>
                    <span class="fchip" :class="{ on: filter === 'off' }" @click="filter = 'off'">Off</span>
                </div>
            </div>
            <div class="m-modwrap">
                @foreach ($moduleGroups as $gi => $mg)
                    @php
                        $cid = 'cat'.$gi;
                        $jsMods = [];
                        foreach ($mg['sets'] as $sName => $sDef) {
                            $removable = $setRemovable[$sName] ?? true;
                            $hay = strtolower(trim(
                                $sName.' '.($sDef['label'] ?? '').' '.($sDef['description'] ?? '').' '
                                .collect($sDef['subtoggles'] ?? [])->map(fn ($s) => ($s['label'] ?? '').' '.($s['description'] ?? ''))->implode(' ')
                            ));
                            $jsMods[] = ['name' => $sName, 'required' => ! $removable, 'hay' => $hay];
                        }
                    @endphp
                    <div class="catblock" wire:key="m-cat-{{ $cid }}"
                         x-data="{ mods: @js($jsMods), cid: '{{ $cid }}' }"
                         :class="{ folded: folded[cid] }"
                         x-show="catVisible(mods)">
                        <div class="cathead" @click="folded[cid] = !folded[cid]">
                            <span class="ct-name">{{ $mg['label'] }}</span>
                            <span class="ct-line"></span>
                            <span class="ct-count"><b x-text="onCount(mods)">{{ collect($mg['sets'])->keys()->intersect($enabledSets)->count() }}</b> / {{ count($mg['sets']) }} on</span>
                            <svg class="ct-fold" viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <div class="modgrid">
                            @foreach ($mg['sets'] as $name => $set)
                                @php
                                    $removable = $setRemovable[$name] ?? true;
                                    $parentEnabled = in_array($name, $enabledSets, true);
                                    $setReqSet = $set['requires']['set'] ?? null;
                                    $setReqMet = $setReqSet === null || in_array($setReqSet, $enabledSets, true);
                                    $setReqLabel = $setReqSet ? ($setDefs[$setReqSet]['label'] ?? $setReqSet) : null;
                                    $setDisabled = ! $removable || ! $setReqMet;
                                    $hay = strtolower(trim(
                                        $name.' '.($set['label'] ?? '').' '.($set['description'] ?? '').' '
                                        .collect($set['subtoggles'] ?? [])->map(fn ($s) => ($s['label'] ?? '').' '.($s['description'] ?? ''))->implode(' ')
                                    ));
                                    $onExpr = $removable ? '$wire.enabledSets.includes('.\Illuminate\Support\Js::from($name).')' : 'true';
                                @endphp
                                <div class="modcard" wire:key="m-mod-{{ $name }}-{{ $setReqMet ? 'ok' : 'needs' }}"
                                     x-show="rowVisible(@js($name), @js(! $removable), @js($hay))">
                                    <label class="chk {{ $setDisabled ? 'disabled' : '' }}" :class="{ on: {{ $onExpr }} }">
                                        <input type="checkbox" class="vh" wire:model.live="enabledSets" value="{{ $name }}" @disabled($setDisabled)>
                                        <span class="box"></span>
                                        <span class="mtext">
                                            <span class="label">{{ $set['label'] }}
                                                @if ($setReqSet && ! $setReqMet)<span class="badge gray">needs {{ $setReqLabel }}</span>@endif
                                                @unless ($removable)<span class="badge req">required</span>@endunless
                                            </span>
                                            <span class="desc">{{ $set['description'] ?? '' }}</span>
                                            @if (! empty($set['subtoggles']))
                                                <span class="subopts" @click.stop>
                                                    @foreach ($set['subtoggles'] as $sub)
                                                        @php
                                                            $reqSet = $sub['requires']['set'] ?? null;
                                                            $reqMet = $reqSet === null || in_array($reqSet, $enabledSets, true);
                                                            $subDisabled = ! $parentEnabled || ! $reqMet;
                                                            $reqLabel = $reqSet ? ($setDefs[$reqSet]['label'] ?? $reqSet) : null;
                                                        @endphp
                                                        <label class="chk mini {{ $subDisabled ? 'is-disabled' : '' }}"
                                                               wire:key="m-sub-{{ $name }}-{{ $sub['name'] }}-{{ $subDisabled ? 'off' : 'on' }}"
                                                               :class="{ on: $wire.enabledSubtoggles.includes('{{ $name }}.{{ $sub['name'] }}') }">
                                                            <span class="box"></span>
                                                            <input type="checkbox" class="vh" wire:model.live="enabledSubtoggles" value="{{ $name }}.{{ $sub['name'] }}" @disabled($subDisabled)>
                                                            <span class="label">{{ $sub['label'] }}@if ($reqSet && ! $reqMet)<span class="badge gray">needs {{ $reqLabel }}</span>@endif</span>
                                                        </label>
                                                    @endforeach
                                                </span>
                                            @endif
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @php
                    $allJsMods = [];
                    foreach ($moduleGroups as $mg) {
                        foreach ($mg['sets'] as $sName => $sDef) {
                            $removable = $setRemovable[$sName] ?? true;
                            $hay = strtolower(trim(
                                $sName.' '.($sDef['label'] ?? '').' '.($sDef['description'] ?? '').' '
                                .collect($sDef['subtoggles'] ?? [])->map(fn ($s) => ($s['label'] ?? '').' '.($s['description'] ?? ''))->implode(' ')
                            ));
                            $allJsMods[] = ['name' => $sName, 'required' => ! $removable, 'hay' => $hay];
                        }
                    }
                @endphp
                <p class="empty-note" x-show="!@js($allJsMods).some(m => rowVisible(m.name, m.required, m.hay))" x-cloak>No modules match.</p>
            </div>
        </div>
    </div>

    {{-- Languages --}}
    @if (count($languageDefs) > 0)
        <div class="acc" x-data="{ open: false }" :class="{ open }">
            <div class="acc-head" @click="open = !open">
                <svg class="ic" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.3"/><path d="M2 8h12M8 2c1.8 1.6 1.8 10.4 0 12M8 2c-1.8 1.6-1.8 10.4 0 12" stroke="currentColor" stroke-width="1.1"/></svg>
                <span class="nm">Languages</span><span class="val">{{ $enabledLanguageCount }} locales</span>
                <svg class="chev" viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="acc-body">
                <div class="langgrid">
                    @foreach ($languageDefs as $name => $lang)
                        @php
                            $loc = \Illuminate\Support\Str::after($name, 'language-');
                            $parts = explode('_', $loc);
                            $code = $parts[0];
                            $locale = collect($parts)->map(fn ($p, $i) => $i === 0 ? $p : (strlen($p) === 2 ? strtoupper($p) : ucfirst($p)))->implode('_');
                            $lname = trim(\Illuminate\Support\Str::before($lang['label'] ?? $name, '('));
                        @endphp
                        <label class="langcard" wire:key="m-lang-{{ $name }}"
                               :class="{ on: $wire.enabledSets.includes(@js($name)) }">
                            <input type="checkbox" class="vh" wire:model.live="enabledSets" value="{{ $name }}">
                            <span class="lcode">{{ $code }}</span>
                            <div><div class="ln">{{ $lname }}</div><div class="lc">{{ $locale }}</div></div>
                            <span class="grow"></span><span class="tick"></span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Layers --}}
    <div class="acc" x-data="{ open: false }" :class="{ open }">
        <div class="acc-head" @click="open = !open">
            <svg class="ic" viewBox="0 0 16 16" fill="none"><path d="M8 2l6 3-6 3-6-3z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M2 8l6 3 6-3M2 11l6 3 6-3" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>
            <span class="nm">Layers</span><span class="val">{{ $enabledLayerCount }} on</span>
            <svg class="chev" viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="acc-body">
            <div class="layerlist">
                @foreach ($layerDefs as $name => $layer)
                    @php
                        $isStock = ($layer['stock'] ?? true) !== false;
                        $isForced = in_array($name, $this->forcedLayers, true);
                    @endphp
                    @if ($isStock)
                        @php $removable = $layerRemovable[$name] ?? true; @endphp
                        <label class="layerrow {{ $removable ? '' : 'forced' }}" wire:key="m-layer-{{ $name }}">
                            <div class="grow">
                                <div class="lt">{{ $layer['label'] }}@unless ($removable) <span class="badge req">required</span>@endunless</div>
                                <div class="ld">{{ $layer['description'] ?? '' }}</div>
                            </div>
                            <input type="checkbox" class="vh" wire:model.live="enabledStockLayers" value="{{ $name }}" @disabled(! $removable)>
                            @php $swExpr = $removable ? '$wire.enabledStockLayers.includes('.\Illuminate\Support\Js::from($name).')' : 'true'; @endphp
                            <span class="switch {{ $removable ? '' : 'switch-locked' }}" :class="{ on: {{ $swExpr }} }"></span>
                        </label>
                    @else
                        <div class="layerrow forced" wire:key="m-layer-{{ $name }}">
                            <div class="grow">
                                <div class="lt">{{ $layer['label'] }} @if ($isForced)<span class="badge req">required</span>@else<span class="badge auto">auto</span>@endif</div>
                                <div class="ld">{{ $layer['description'] ?? '' }}</div>
                            </div>
                            <span class="switch {{ $isForced ? 'on' : '' }} switch-locked"></span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    {{-- ===== OUTPUT ===== --}}
    <div class="m-output">
        <div class="out-bougie">
            <div class="h"><svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M8 1.5l1.8 4.2 4.7.4-3.6 3 1.1 4.5L8 11.2 3.9 13.6 5 9.1 1.4 6.1l4.7-.4z" stroke="#4338ca" stroke-width="1.2" stroke-linejoin="round"/></svg> Try it with bougie</div>
            @if ($this->effectiveSavedId)
                <p>Run <b>this exact configuration</b> with <a href="https://bougie.tools" target="_blank" rel="noopener">bougie</a> — one command, no clone:</p>
                <div class="cmd-row"><pre class="cmd"><code># Install bougie if you don't have it yet
curl -LsSf https://bougie.tools/install.sh | sh
# Start it up!
bougie init --starter {{ $this->starterArg }} --start</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy command">{!! $copyIcon !!}</button></div>
                <small class="bougie-note">Shareable link to this build: <code>{{ $this->starterArg }}</code></small>
            @else
                <p>
                    @if ($savedId)
                        You've changed the configuration since it was last saved. <b>Save again</b> to refresh the one-command install link.
                    @else
                        <b>Save your configuration</b> to get a personal one-command bougie install link for <b>this exact build</b>.
                    @endif
                </p>
                <button type="button" class="btn btn-primary btn-sm bougie-save" wire:click="save">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M3.5 2.5h7L13 5v8.5H3.5z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M5.5 2.5v3.5h4V2.5M5.5 13.5v-3.5h5v3.5" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg>
                    {{ $savedId ? 'Save again & refresh link' : 'Save & get install command' }}
                </button>
                <details class="bougie-default">
                    <summary>Or run the default Mage-OS starter now</summary>
                    <p class="bougie-default-note">Installs stock Mage-OS, not the configuration above.</p>
                    <div class="cmd-row"><pre class="cmd"><code># Install bougie if you don't have it yet
curl -LsSf https://bougie.tools/install.sh | sh
# Start it up!
bougie init --starter mageos --start</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy command">{!! $copyIcon !!}</button></div>
                </details>
            @endif
        </div>

        <div class="out-head"><h2>Generated output</h2></div>
        <div class="out-tabs">
            <div class="out-tab" :class="{ active: otab === 'composer' }" @click="otab = 'composer'">composer.json</div>
            <div class="out-tab" :class="{ active: otab === 'tree' }" @click="otab = 'tree'">Install tree<span class="n">{{ $this->installTree['count'] }}</span></div>
            @if ($this->usesHyva)
                <div class="out-tab out-tab-hyva" :class="{ active: otab === 'hyva' }" @click="otab = 'hyva'">★ Hyvä setup</div>
            @endif
        </div>

        <div class="out-panes">
            <div class="opane" x-show="otab === 'composer'">
                <pre class="composer" wire:ignore><code id="composer-out-m" class="language-json composer-code">{{ $this->composerJson }}</code></pre>
            </div>
            <div class="opane" x-show="otab === 'tree'" x-cloak>
                @php $mtree = $this->installTree; @endphp
                @if ($mtree['count'] === 0 && ! $mtree['missing'])
                    <p class="out-note">No packages — nothing to show.</p>
                @else
                    <div class="install-tree-types">
                        @foreach ($mtree['byType'] as $type => $n)<span>{{ $type }}: {{ $n }}</span>@endforeach
                    </div>
                    <div id="install-tree-root-m" class="install-tree-root">
                        @include('livewire.partials.install-tree-node', ['nodes' => $mtree['tree'], 'depth' => 0])
                    </div>
                @endif
            </div>
            @if ($this->usesHyva)
                @php
                    $mToken = $hyvaToken !== '' ? $hyvaToken : 'YOUR_HYVA_TOKEN';
                    $mProject = $hyvaProject !== '' ? $hyvaProject : 'yourProjectName';
                @endphp
                <div class="opane hyva-pane" x-show="otab === 'hyva'" x-cloak>
                    <p class="hyva-intro">
                        The Hyvä Theme is free of charge but requires a packagist token. Register at
                        <a href="https://www.hyva.io/" target="_blank" rel="noopener">hyva.io</a> for your token and project name,
                        then run these in your project root <strong>before</strong> <code>composer install</code>.
                    </p>
                    <div class="hyva-fields">
                        <label><span>Hyvä token</span><input type="text" class="input" wire:model.live.debounce.300ms="hyvaToken" placeholder="YOUR_HYVA_TOKEN" autocomplete="off"></label>
                        <label><span>Project name</span><input type="text" class="input" wire:model.live.debounce.300ms="hyvaProject" placeholder="yourProjectName" autocomplete="off"></label>
                    </div>
                    <ol class="hyva-steps">
                        <li>
                            <span class="step-label">Configure composer auth</span>
                            <div class="cmd-row"><pre class="cmd"><code>composer config --auth http-basic.hyva-themes.repo.packagist.com token {{ $mToken }}</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div>
                        </li>
                        @if ($hyvaProject === '')
                            <li>
                                <span class="step-label">Add the Hyvä private repository</span>
                                <div class="cmd-row"><pre class="cmd"><code>composer config repositories.hyva-private composer https://hyva-themes.repo.packagist.com/{{ $mProject }}/</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div>
                                <small>Fill in the project name above to bake the repo into the generated <code>composer.json</code>.</small>
                            </li>
                        @endif
                        <li>
                            <span class="step-label">Install dependencies</span>
                            <div class="cmd-row"><pre class="cmd"><code>composer install</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div>
                        </li>
                        <li>
                            <span class="step-label">Activate the theme in Magento</span>
                            <div class="cmd-row"><pre class="cmd"><code>bin/magento setup:upgrade
bin/magento config:set design/theme/theme_id frontend/Hyva/default
bin/magento cache:flush</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div>
                        </li>
                        <li>
                            <span class="step-label">Disable the legacy Magento captcha</span>
                            <div class="cmd-row"><pre class="cmd"><code>bin/magento config:set customer/captcha/enable 0</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div>
                            <small>Hyvä doesn't support the legacy captcha; storefront forms break with it on.</small>
                        </li>
                    </ol>
                </div>
            @endif
        </div>

        <p class="out-note">Tip: <code>composer create-project</code> against the generated <code>composer.json</code> reproduces this exact tree.</p>
    </div>

    {{-- sticky bottom action bar --}}
    <div class="m-actionbar">
        <div class="counts">
            <span class="countchip"><b>require</b> {{ $this->requireCount }}</span>
            <span class="countchip"><b>replace</b> {{ $this->replaceCount }}</span>
        </div>
        <button class="btn btn-ghost btn-sm" wire:click="save">Save</button>
        <button class="btn btn-primary btn-sm" onclick="copyComposer()">Copy</button>
    </div>
</div>

<script>
    function filterInstallTree(q) {
        q = q.trim().toLowerCase();
        const root = document.getElementById('install-tree-root');
        if (!root) return;
        function walk(el) {
            const name = (el.dataset.name || '').toLowerCase();
            let selfMatch = q === '' || name.includes(q);
            let descMatch = false;
            el.querySelectorAll(':scope > details, :scope > .leaf').forEach(child => {
                if (walk(child)) descMatch = true;
            });
            const visible = selfMatch || descMatch;
            el.classList.toggle('hidden', !visible);
            if (q !== '' && descMatch && el.tagName === 'DETAILS') el.open = true;
            return visible;
        }
        root.querySelectorAll(':scope > details, :scope > .leaf').forEach(walk);
    }
    function installTreeToggleAll(open) {
        document.querySelectorAll('#install-tree-root details').forEach(d => d.open = open);
    }
</script>
</div>
