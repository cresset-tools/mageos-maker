@php
    $copyIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" aria-hidden="true" focusable="false"><path d="M480 400L288 400C279.2 400 272 392.8 272 384L272 128C272 119.2 279.2 112 288 112L421.5 112C425.7 112 429.8 113.7 432.8 116.7L491.3 175.2C494.3 178.2 496 182.3 496 186.5L496 384C496 392.8 488.8 400 480 400zM288 448L480 448C515.3 448 544 419.3 544 384L544 186.5C544 169.5 537.3 153.2 525.3 141.2L466.7 82.7C454.7 70.7 438.5 64 421.5 64L288 64C252.7 64 224 92.7 224 128L224 384C224 419.3 252.7 448 288 448zM160 192C124.7 192 96 220.7 96 256L96 512C96 547.3 124.7 576 160 576L352 576C387.3 576 416 547.3 416 512L416 496L368 496L368 512C368 520.8 360.8 528 352 528L160 528C151.2 528 144 520.8 144 512L144 256C144 247.2 151.2 240 160 240L176 240L176 192L160 192z"/></svg>';
@endphp

<x-layouts.app>
<script>window.MAKER = @json($maker);</script>

<div class="shell">

    <div class="appbar">
        <span class="brand"><span class="glyph">M</span>mageos-maker</span>
        <span class="sp"></span>
        <a class="ghost" href="/">Reset</a>
    </div>

    {{-- mobile chips rail (rendered by JS) --}}
    <div class="chips" id="chips"></div>

    <div class="work">
        {{-- desktop spine --}}
        <aside class="spine">
            <div class="sum-card">
                <div class="pf" id="spine-pf">Build</div>
                <div class="meta" id="spine-meta"></div>
            </div>
            <div class="steps" id="spine"></div>
        </aside>

        {{-- canvas --}}
        <main class="canvas" id="canvas">
            <div class="m-sum">
                <div class="pf" id="m-pf">Build</div>
                <div class="mmeta" id="m-meta"></div>
            </div>

            <section class="intro-sec">
                <h1>Build your own Mage-OS distribution</h1>
                <p>Tailor a Mage-OS project end to end — choose a release line and distribution, start from a profile, then turn individual modules, languages and architectural layers on or off. The generated <b>composer.json</b> and install tree update live as you go, and <b>Save</b> hands you a one-command <a href="https://bougie.tools" target="_blank" rel="noopener">bougie</a> install link for the exact build.</p>
            </section>

            <section class="csec" id="sec-version" data-step="version">
                <div class="kicker"><span class="num">01</span> Foundation</div>
                <div class="htop"><h2>Mage-OS version</h2><span class="curval" data-curval="version"></span></div>
                <div class="sub">The release line your project metapackage targets. The latest stable is recommended for new builds.</div>
                <div class="bd"><div class="rcardgrid two" id="version-grid"></div><div id="version-note"></div></div>
            </section>

            <section class="csec" id="sec-distribution" data-step="distribution" hidden>
                <div class="kicker"><span class="num">02</span> Foundation</div>
                <div class="htop"><h2>Distribution</h2><span class="curval" data-curval="distribution"></span></div>
                <div class="sub">How Mage-OS is packaged. The modular distribution splits the monolith into independently versioned Composer packages.</div>
                <div class="bd"><div class="rcardgrid two" id="distribution-grid"></div><div id="distribution-note"></div></div>
            </section>

            <section class="csec" id="sec-profile" data-step="profile">
                <div class="kicker"><span class="num">03</span> Foundation</div>
                <div class="htop"><h2>Profile</h2><span class="curval" data-curval="profile"></span></div>
                <div class="sub">A starting preset for your module set. Pick one and watch the Modules &amp; Checkout below update the moment you do.</div>
                <div class="bd">
                    <div class="rcardgrid two" id="profile-grid"></div>
                    <div class="tether" id="profile-tether" data-goto="modules">
                        <span class="ic"><svg viewBox="0 0 16 16" fill="none"><path d="M8 2v9m0 0l3.5-3.5M8 11L4.5 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <span class="tx"><span class="tt" id="tether-tt">Modules updated below</span><span class="td" id="tether-td"></span></span>
                        <span class="go">Jump <svg viewBox="0 0 16 16" fill="none"><path d="M8 3v9m0 0l4-4M8 12L4 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    </div>
                </div>
            </section>

            <section class="csec" id="sec-theme" data-step="theme">
                <div class="kicker"><span class="num">04</span> Storefront</div>
                <div class="htop"><h2>Theme &amp; Checkout</h2><span class="curval" data-curval="theme"></span></div>
                <div class="sub">Storefront rendering and the checkout experience. Checkout options sit next to the theme, so the dependency is never hidden.</div>
                <div class="bd"><div class="colpair" id="theme-checkout-host"></div></div>
            </section>

            <section class="csec" id="sec-addons" data-step="addons">
                <div class="kicker"><span class="num">05</span> Storefront</div>
                <div class="htop"><h2>Add-ons</h2><span class="curval" data-curval="addons"></span></div>
                <div class="sub">Optional third-party packages layered on top. Greyed-out items are forced by your profile-group choices.</div>
                <div class="bd"><div class="langgrid addon-grid" id="addon-host"></div></div>
            </section>

            <section class="csec" id="sec-modules" data-step="modules">
                <div class="kicker"><span class="num">06</span> Packages</div>
                <div class="htop"><h2>Modules</h2><span class="curval" data-curval="modules"></span></div>
                <div class="sub">Each module shows <b>why</b> it's on — <span class="origin req" style="font-size:9px">required</span>, <span class="origin profile" style="font-size:9px">via profile</span>, or <span class="origin you" style="font-size:9px">your choice</span>. Profile changes pulse the rows they touched.</div>
                <div class="bd">
                    <div class="modtools">
                        <div class="search"><svg class="ic" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="4.5" stroke="currentColor" stroke-width="1.4"/><path d="M11 11l3 3" stroke="currentColor" stroke-width="1.4"/></svg><input id="mod-search" class="input" placeholder="Filter modules…" autocomplete="off"></div>
                        <div class="filterchips">
                            <span class="fchip on" data-filter="all">All</span>
                            <span class="fchip" data-filter="enabled">On</span>
                            <span class="fchip" data-filter="required">Required</span>
                            <span class="fchip" data-filter="off">Off</span>
                        </div>
                    </div>
                    <div id="modgrid-host"></div>
                </div>
            </section>

            <section class="csec" id="sec-languages" data-step="languages">
                <div class="kicker"><span class="num">07</span> Packages</div>
                <div class="htop"><h2>Languages</h2><span class="curval" data-curval="languages"></span></div>
                <div class="sub">Locale packs bundled into the build. Each adds a <code style="font-family:var(--mono);font-size:11px">mage-os/language-*</code> package.</div>
                <div class="bd"><div class="langgrid" id="lang-grid"></div></div>
            </section>

            <section class="csec" id="sec-layers" data-step="layers">
                <div class="kicker"><span class="num">08</span> Packages</div>
                <div class="htop"><h2>Layers</h2><span class="curval" data-curval="layers"></span></div>
                <div class="sub">Optional architectural layers that change how the application is served.</div>
                <div class="bd"><div class="layerlist" id="layer-host"></div></div>
            </section>

            <div class="m-spacer"></div>
        </main>

        {{-- dock / bottom sheet --}}
        <aside class="dock scrollbox" id="dock">
            <div class="dock-grab"></div>
            <div class="dock-sum" id="dock-sum">
                <div class="ttl" id="dock-ttl">Build</div>
                <div class="dmeta" id="dock-meta"></div>
                <div class="big"><span class="num" id="dock-pkgs">{{ $packageCount }}</span><span class="unit">packages</span><span class="delta" id="dock-delta"></span><span class="spin" id="dock-spin"></span></div>
            </div>
            <div class="changelog" id="changelog"></div>

            <div class="dock-bougie" id="dock-bougie">
                <div class="h"><svg viewBox="0 0 16 16" fill="none"><path d="M8 1.5l1.8 4.2 4.7.4-3.6 3 1.1 4.5L8 11.2 3.9 13.6 5 9.1 1.4 6.1l4.7-.4z" stroke="#4338ca" stroke-width="1.2" stroke-linejoin="round"/></svg> Try it with bougie</div>
                <div class="bg-pitch" id="bg-pitch" @if ($savedId) style="display:none" @endif>
                    <p><b>Save your configuration</b> to get a personal one-command bougie install link for <b>this exact build</b> — no clone, no copy-paste.</p>
                    <button class="save-btn" id="bougie-save"><svg viewBox="0 0 16 16" fill="none"><path d="M3 2.5h7.5L13.5 5.5V13a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5V3a.5.5 0 0 1 .5-.5z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M5 2.5v3h5v-3M5 13v-3.5h6V13" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg> Save &amp; get install command</button>
                    <details class="bougie-default" style="margin-top:11px">
                        <summary class="alt">▶ Or run the default Mage-OS starter now</summary>
                        <div class="cmd-row" style="margin-top:8px"><pre class="cmd"><code># Install bougie if you don't have it yet
curl -LsSf https://bougie.tools/install.sh | sh</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy install command">{!! $copyIcon !!}</button></div>
                        <div class="cmd-row" style="margin-top:7px"><pre class="cmd"><code># Stock Mage-OS, not the build above
bougie new bougie-store --starter mageos --start</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy start command">{!! $copyIcon !!}</button></div>
                    </details>
                </div>
                <div class="bg-result" id="bg-result" @unless ($savedId) style="display:none" @endunless>
                    <p>Run <b>this exact configuration</b> with bougie — no clone:</p>
                    <div class="cmd-row"><pre class="cmd"><code># Install bougie if you don't have it yet
curl -LsSf https://bougie.tools/install.sh | sh</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy install command">{!! $copyIcon !!}</button></div>
                    <div class="cmd-row" style="margin-top:7px"><pre class="cmd"><code>bougie new bougie-store --starter <span id="bougie-starter">{{ $starterArg }}</span> --start</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy start command">{!! $copyIcon !!}</button></div>
                    <small class="bougie-note">Shareable link to this build: <code id="bougie-share">{{ $savedId ? $starterArg : '' }}</code></small>
                    <div class="alt" id="bougie-edit">↺ Edit configuration</div>
                </div>
            </div>

            <div class="dock-tabs" id="dock-tabs">
                <div class="dock-tab active" data-otab="composer">composer.json</div>
                <div class="dock-tab" data-otab="tree">Install tree<span class="n" id="tree-count">{{ $tree['count'] }}</span></div>
            </div>

            <div class="dock-foot">
                <span class="countchip"><b>require</b> <span id="require-count">{{ $requireCount }}</span></span>
                <span class="countchip"><b>replace</b> <span id="replace-count">{{ $replaceCount }}</span></span>
                <span class="sp"></span>
                <button class="btn btn-ghost btn-sm" id="dock-save">Save</button>
                <button class="btn btn-primary btn-sm" onclick="copyComposer(this)">Copy</button>
            </div>

            <div class="dock-body" id="dock-body">
                <div class="hyva-setup" id="hyva-setup" @unless ($usesHyva) hidden @endunless>
                    <div class="hyva-setup-h"><svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 1.5l1.8 4.2 4.7.4-3.6 3 1.1 4.5L8 11.2 3.9 13.6 5 9.1 1.4 6.1l4.7-.4z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg> Hyvä setup</div>
                    <div class="hyva-pane">
                        <p class="hy-intro">The Hyvä theme is free but requires a Packagist token. Register at <a href="https://www.hyva.io/" target="_blank" rel="noopener">hyva.io</a> for a free token + project name, then run these <b>before</b> <code>composer install</code>.</p>
                        <div class="hy-fields">
                            <label>Hyvä token<input class="input" id="hy-token" placeholder="YOUR_HYVA_TOKEN" autocomplete="off"></label>
                            <label>Project name<input class="input" id="hy-project" placeholder="yourProjectName" autocomplete="off"></label>
                        </div>
                        <ol class="hy-steps">
                            <li><div class="hy-st">Configure composer auth</div><div class="cmd-row"><pre class="cmd"><code>composer config --auth http-basic.hyva-themes.repo.packagist.com token <span class="hy-var" data-var="token">YOUR_HYVA_TOKEN</span></code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div></li>
                            <li><div class="hy-st">Add the Hyvä private repository</div><div class="cmd-row"><pre class="cmd"><code>composer config repositories.hyva-private composer https://hyva-themes.repo.packagist.com/<span class="hy-var" data-var="project">yourProjectName</span>/</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div></li>
                            <li><div class="hy-st">Install dependencies</div><div class="cmd-row"><pre class="cmd"><code>composer install</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div></li>
                            <li><div class="hy-st">Activate the theme in Magento</div><div class="cmd-row"><pre class="cmd"><code>bin/magento setup:upgrade
bin/magento config:set design/theme/theme_id frontend/Hyva/default
bin/magento cache:flush</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div></li>
                            <li><div class="hy-st">Disable the legacy Magento captcha</div><div class="cmd-row"><pre class="cmd"><code>bin/magento config:set customer/captcha/enable 0</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div><div class="hy-note">Hyvä doesn't support the legacy captcha; swap in Google reCAPTCHA from the admin.</div></li>
                        </ol>
                    </div>
                </div>
                <div class="loki-setup" id="loki-setup" @unless ($usesLokiCheckout) hidden @endunless>
                    <div class="loki-setup-h"><svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 1.5l1.8 4.2 4.7.4-3.6 3 1.1 4.5L8 11.2 3.9 13.6 5 9.1 1.4 6.1l4.7-.4z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg> Loki Checkout setup</div>
                    <div class="hyva-pane">
                        <p class="hy-intro">Loki Checkout (Hyvä) needs <b>Alpine.js 3</b> and <b>Hyvä Themes 1.4+</b>. It installs with the composer build above; run these <b>after</b> <code>composer install</code> to finish setup. See the <a href="https://docs.loki-extensions.com/checkout/dev/getting-started/installation-hyva" target="_blank" rel="noopener">Loki docs</a>.</p>
                        <ol class="hy-steps">
                            <li><div class="hy-st">Enable the Loki &amp; Yireo modules</div><div class="cmd-row"><pre class="cmd"><code>bin/magento module:enable $(bin/magento module:status | grep -E 'Yireo|Loki')</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div></li>
                            <li><div class="hy-st">Register the modules with Hyvä</div><div class="cmd-row"><pre class="cmd"><code>bin/magento hyva:config:generate
bin/magento setup:upgrade</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div><div class="hy-note">Writes <code>app/etc/hyva-themes.json</code> so the <code>LokiCheckout_*</code> modules register with the theme.</div></li>
                            <li><div class="hy-st">Rebuild your theme's Tailwind CSS</div><div class="cmd-row"><pre class="cmd"><code>cd app/design/frontend/&lt;Vendor&gt;/&lt;theme&gt;/web/tailwind
npm install &amp;&amp; npm run build</code></pre><button type="button" class="cmd-copy" onclick="copyCmd(this)" aria-label="Copy">{!! $copyIcon !!}</button></div></li>
                        </ol>
                        <div class="hy-note">Don't enable both Hyvä Checkout and Loki Checkout at the same time — pick one in the Checkout step.</div>
                    </div>
                </div>
                <div data-opane="composer" class="opane-composer show">
                    <pre class="composer"><code id="composer-out" class="composer-code language-json">{{ $composerJson }}</code></pre>
                </div>
                <div data-opane="tree">
                    <div id="install-tree-pane">
                        @include('partials.install-tree-pane', ['tree' => $tree])
                    </div>
                </div>
            </div>
        </aside>
    </div>

    {{-- mobile bottom bar --}}
    <div class="m-bar">
        <div class="pk"><div class="n"><span id="m-pkgs">{{ $packageCount }}</span><span class="delta" id="m-delta"></span></div><div class="l">packages</div></div>
        <span class="grow"></span>
        <button class="view-out" id="open-sheet"><svg viewBox="0 0 16 16" fill="none"><path d="M2 4.5h12M2 8h12M2 11.5h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> View output</button>
    </div>

    <div class="m-toast" id="m-toast"></div>
    <div class="sheet-scrim" id="sheet-scrim"></div>
</div>
</x-layouts.app>
