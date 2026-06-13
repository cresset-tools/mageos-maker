/* ============================================================
   MageOS Maker — client-side derivation engine.

   A faithful port of the PHP derivation (app/Services/Definitions.php,
   Selection.php, Configurator.php, and the old Livewire component) — enough to
   drive every interactive affordance instantly: which modules are on/off and
   *why*, removability greying, profile ripples, theme→checkout availability,
   add-on soft-defaults, version-gating, the modulargento unlock, requires/cascade.

   It is NOT authoritative for the build: the real composer.json + install tree
   always come back from POST /api/build (which re-derives from toSelection()).
   A bug here shows a wrong hint, never a wrong build.
   ============================================================ */

const D = window.MAKER;

/* ---------- version comparison (gating) ----------
   Mirrors Definitions::versionInRange() closely enough for UI gating: compares
   the numeric major.minor.patch triplet, ignoring -pN/stability suffixes. The
   server stays authoritative for the actual build. */
function triplet(v) {
  const m = String(v).match(/^(\d+)(?:\.(\d+))?(?:\.(\d+))?/);
  return m ? [+m[1] || 0, +m[2] || 0, +m[3] || 0] : [0, 0, 0];
}
function cmp(a, b) {
  const x = triplet(a), y = triplet(b);
  for (let i = 0; i < 3; i++) if (x[i] !== y[i]) return x[i] < y[i] ? -1 : 1;
  return 0;
}
function versionInRange(def, version) {
  const since = def.since ?? null, until = def.until ?? null;
  if ((since === null && until === null) || !version) return true;
  if (since !== null && cmp(version, since) < 0) return false;
  if (until !== null && cmp(version, until) >= 0) return false;
  return true;
}

function uniq(arr) { return [...new Set(arr)]; }
function diff(a, b) { const s = new Set(b); return a.filter((x) => !s.has(x)); }

export const engine = {
  D,
  /** Live UI state (the "positive" mirrors, like the old Livewire props). */
  s: {
    version: '', profile: null, distribution: 'standard',
    enabledSets: [], enabledStockLayers: [], enabledAddons: [],
    profileGroups: {}, enabledSubtoggles: [], enabledOptionSubtoggles: [],
    optionVariants: {},
  },
  /** Module names the user manually toggled since the last profile apply (origin pill). */
  overrides: new Set(),
  /** enabledSets snapshot under the current profile (origin pill baseline). */
  baseline: new Set(),

  /* ---------- lookups ---------- */
  set(name) { return D.sets[name]; },
  layer(name) { return D.layers[name]; },
  profileGroupDef(name) { return D.profileGroups.find((g) => g.name === name); },
  optionDef(group, option) {
    const g = this.profileGroupDef(group);
    return g && (g.options || []).find((o) => o.name === option);
  },
  modulargentoAvailable() {
    return D.modulargentoVersion !== '' && this.s.version === D.modulargentoVersion;
  },

  /* ---------- version gating ---------- */
  setsForVersion() {
    const out = {};
    for (const [name, s] of Object.entries(D.sets)) {
      if (versionInRange(s, this.s.version)) out[name] = s;
    }
    return out;
  },
  isSetAvailable(name) {
    return !!D.sets[name] && versionInRange(D.sets[name], this.s.version);
  },
  isAddonAvailable(name) {
    return !!D.addons[name] && versionInRange(D.addons[name], this.s.version);
  },
  setNamesForVersion() { return Object.keys(this.setsForVersion()); },
  stockLayerNames() {
    return Object.keys(D.layers).filter((n) => D.layers[n].stock !== false);
  },

  /* ---------- removability (distribution-aware) ---------- */
  isSetRemovable(name) {
    const s = D.sets[name];
    if (!s) return true;
    return this.s.distribution === 'modulargento'
      ? s.removable_modulargento !== false
      : s.removable !== false;
  },
  isLayerRemovable(name) {
    const l = D.layers[name];
    if (!l) return true;
    return this.s.distribution === 'modulargento'
      ? l.removable_modulargento !== false
      : l.removable !== false;
  },

  /* ---------- requires accessors ---------- */
  setRequiredSet(name) { return D.sets[name]?.requires?.set ?? null; },
  setRequiredLayer(name) { return D.sets[name]?.requires?.layer ?? null; },
  subtoggleRequiredSet(set, sub) {
    return (D.sets[set]?.subtoggles || []).find((x) => x.name === sub)?.requires?.set ?? null;
  },
  allSubtoggleKeys() {
    const keys = [];
    for (const [name, s] of Object.entries(D.sets)) {
      for (const sub of s.subtoggles || []) keys.push(`${name}.${sub.name}`);
    }
    return keys;
  },

  /* ---------- profile-group resolution (Definitions/Configurator) ---------- */
  optionMeetsRequires(group, option) {
    const opt = this.optionDef(group, option);
    if (!opt) return false;
    const req = opt.requires?.profileGroups || {};
    return Object.entries(req).every(([g, v]) => (this.s.profileGroups[g] ?? null) === v);
  },
  optionPreferAlternative(group, option) {
    const opt = this.optionDef(group, option);
    const pref = opt?.preferAlternative;
    if (!pref || !pref.use) return null;
    const when = pref.when?.profileGroups || {};
    if (!Object.keys(when).length) return null;
    if (!Object.entries(when).every(([g, v]) => (this.s.profileGroups[g] ?? null) === v)) return null;
    return { use: pref.use, ...(pref.reason ? { reason: pref.reason } : {}) };
  },
  optionVariantsOf(group, option) { return this.optionDef(group, option)?.variants || []; },
  optionActiveVariant(group, option) {
    const variants = this.optionVariantsOf(group, option);
    if (!variants.length) return null;
    const meets = (v) => Object.entries(v.requires?.profileGroups || {})
      .every(([g, x]) => (this.s.profileGroups[g] ?? null) === x);
    const picked = this.s.optionVariants[`${group}.${option}`] ?? null;
    const pv = variants.find((v) => v.name === picked);
    if (pv && meets(pv)) return picked;
    const def = variants.find((v) => v.default && meets(v));
    if (def) return def.name;
    const first = variants.find((v) => meets(v));
    return first ? first.name : null;
  },
  defaultProfileGroupOption(group) {
    return (this.profileGroupDef(group)?.options || []).find((o) => o.default)?.name ?? null;
  },

  /**
   * Mirrors Configurator::resolveProfileGroups() + activeOptionSubtoggleAddons():
   * what the current profile-group picks force/soft-default/disable.
   */
  resolveProfileGroups() {
    const r = { forceAddons: [], forceLayers: [], defaultAddons: [], defaultLayers: [], disableSets: [], disableLayers: [] };
    for (const [group, optionName] of Object.entries(this.s.profileGroups)) {
      let opt = this.optionDef(group, optionName);
      if (!opt) continue;
      if (!this.optionMeetsRequires(group, optionName)) {
        const def = this.defaultProfileGroupOption(group);
        if (def === null || def === optionName) continue;
        opt = this.optionDef(group, def) || {};
      }
      if (opt.variants && opt.variants.length) {
        const vn = this.optionActiveVariant(group, optionName);
        if (vn === null) continue;
        opt = opt.variants.find((v) => v.name === vn) || {};
      }
      r.defaultAddons.push(...(opt.enables?.addons || []));
      r.defaultLayers.push(...(opt.enables?.layers || []));
      r.forceAddons.push(...(opt.forces?.addons || []));
      r.forceLayers.push(...(opt.forces?.layers || []));
      r.disableSets.push(...(opt.disables?.sets || []));
      r.disableLayers.push(...(opt.disables?.layers || []));
    }
    for (const k of Object.keys(r)) r[k] = uniq(r[k]);
    return r;
  },
  activeOptionSubtoggleAddons() {
    const out = [];
    for (const key of this.s.enabledOptionSubtoggles) {
      const parts = key.split('.');
      let group, option, variant, sub;
      if (parts.length === 3) [group, option, sub] = parts;
      else if (parts.length === 4) [group, option, variant, sub] = parts;
      else continue;
      if ((this.s.profileGroups[group] ?? null) !== option) continue;
      if (!this.optionMeetsRequires(group, option)) continue;
      const opt = this.optionDef(group, option) || {};
      if (opt.variants && opt.variants.length) {
        if (!variant) continue;
        if (this.optionActiveVariant(group, option) !== variant) continue;
        const vd = opt.variants.find((v) => v.name === variant) || {};
        const sd = (vd.subtoggles || []).find((x) => x.name === sub);
        out.push(...(sd?.addons || []));
      } else {
        if (variant) continue;
        const sd = (opt.subtoggles || []).find((x) => x.name === sub);
        out.push(...(sd?.addons || []));
      }
    }
    return out;
  },
  forcedAddons() { return uniq([...this.resolveProfileGroups().forceAddons, ...this.activeOptionSubtoggleAddons()]); },
  defaultedAddons() { return uniq(this.resolveProfileGroups().defaultAddons); },
  forcedLayers() { return uniq(this.resolveProfileGroups().forceLayers); },

  /** Effective add-ons for Hyvä detection (forced ∪ enabled ∪ subtoggle). */
  effectiveAddons() {
    return uniq([...this.forcedAddons(), ...this.s.enabledAddons, ...this.activeOptionSubtoggleAddons()]);
  },
  usesHyva() {
    return this.effectiveAddons().some((n) => D.addons[n]?.hyva && this.isAddonAvailable(n));
  },

  /* ---------- module effective state + origin pill ---------- */
  moduleOn(name) { return this.s.enabledSets.includes(name); },
  origin(name) {
    if (!this.isSetRemovable(name)) return 'req';
    if (this.overrides.has(name)) return 'you';
    return this.moduleOn(name) ? 'profile' : null;
  },
  /** Whether a module's requires.set / requires.layer dependency is satisfied. */
  setRequiresMet(name) {
    const rs = this.setRequiredSet(name);
    if (rs !== null && !this.s.enabledSets.includes(rs)) return false;
    const rl = this.setRequiredLayer(name);
    if (rl !== null && !this.s.enabledStockLayers.includes(rl)) return false;
    return true;
  },

  /* ---------- cascade enforcement (UI) ---------- */
  enforceSetRequires() {
    let changed = true;
    while (changed) {
      const before = this.s.enabledSets.length;
      this.s.enabledSets = this.s.enabledSets.filter((set) => {
        const rs = this.setRequiredSet(set);
        if (rs !== null && !this.s.enabledSets.includes(rs)) return false;
        const rl = this.setRequiredLayer(set);
        if (rl !== null && !this.s.enabledStockLayers.includes(rl)) return false;
        return true;
      });
      changed = this.s.enabledSets.length !== before;
    }
  },
  enforceSubtoggleRequires() {
    this.s.enabledSubtoggles = this.s.enabledSubtoggles.filter((key) => {
      const [set, sub] = key.split('.');
      if (!sub) return true;
      const needed = this.subtoggleRequiredSet(set, sub);
      return needed === null || this.s.enabledSets.includes(needed);
    });
  },
  forceNonRemovableSetsOn() {
    const locked = this.setNamesForVersion().filter((n) => !this.isSetRemovable(n));
    this.s.enabledSets = uniq([...this.s.enabledSets, ...locked]);
  },
  forceNonRemovableLayersOn() {
    const locked = this.stockLayerNames().filter((n) => !this.isLayerRemovable(n));
    this.s.enabledStockLayers = uniq([...this.s.enabledStockLayers, ...locked]);
  },

  /* ---------- hydration ---------- */
  /** Build a default Selection-shaped object (mirrors Selection::default + profile). */
  defaultSelection() {
    const pg = {};
    for (const g of D.profileGroups) {
      const def = this.defaultProfileGroupOption(g.name);
      if (def !== null) pg[g.name] = def;
    }
    let sel = {
      version: this.s.version, profile: D.defaultProfile, distribution: 'standard',
      disabledSets: [], disabledLayers: [], enabledLayers: [], enabledAddons: [],
      profileGroups: pg, disabledSubtoggles: [], enabledOptionSubtoggles: [], optionVariants: {},
    };
    if (sel.profile && D.profiles[sel.profile]) sel = this.mergeProfile(sel, D.profiles[sel.profile].selection || {});
    return sel;
  },
  /** Mirrors Selection::applyProfile() — union/merge the profile's selection block. */
  mergeProfile(sel, block) {
    const u = (a, b) => uniq([...(a || []), ...(b || [])]);
    return {
      ...sel,
      profile: sel.profile,
      disabledSets: u(sel.disabledSets, block.disabledSets),
      disabledLayers: u(sel.disabledLayers, block.disabledLayers),
      enabledLayers: u(sel.enabledLayers, block.enabledLayers),
      enabledAddons: u(sel.enabledAddons, block.enabledAddons),
      profileGroups: { ...sel.profileGroups, ...(block.profileGroups || {}) },
      disabledSubtoggles: u(sel.disabledSubtoggles, block.disabledSubtoggles),
      enabledOptionSubtoggles: u(sel.enabledOptionSubtoggles, block.enabledOptionSubtoggles),
      optionVariants: { ...sel.optionVariants, ...(block.optionVariants || {}) },
      distribution: block.distribution || sel.distribution,
    };
  },
  /** Mirrors Configurator/Livewire hydrateFromSelection(): Selection → UI mirrors. */
  hydrate(sel) {
    this.s.version = sel.version;
    this.s.profile = sel.profile ?? null;
    this.s.distribution = (sel.distribution === 'modulargento' && this.modulargentoAvailable())
      ? 'modulargento' : 'standard';

    const allSets = this.setNamesForVersion();
    const effDisabled = (sel.disabledSets || []).filter((n) => this.isSetRemovable(n));
    this.s.enabledSets = diff(allSets, effDisabled);

    const stock = this.stockLayerNames();
    const effDisabledLayers = (sel.disabledLayers || []).filter((n) => this.isLayerRemovable(n));
    this.s.enabledStockLayers = diff(stock, effDisabledLayers);

    this.s.profileGroups = { ...(sel.profileGroups || {}) };
    this.s.enabledSubtoggles = diff(this.allSubtoggleKeys(), sel.disabledSubtoggles || []);
    this.s.enabledOptionSubtoggles = [...(sel.enabledOptionSubtoggles || [])];
    this.s.optionVariants = { ...(sel.optionVariants || {}) };
    this.s.enabledAddons = uniq([...(sel.enabledAddons || []), ...this.defaultedAddons(), ...this.activeOptionSubtoggleAddons()]);

    this.forceNonRemovableSetsOn();
    this.forceNonRemovableLayersOn();
    this.enforceSubtoggleRequires();
    this.baseline = new Set(this.s.enabledSets);
    this.overrides = new Set();
  },
  init() { this.s.version = D.initial.selection.version; this.hydrate(D.initial.selection); },

  /* ---------- mutations (called by the controller) ---------- */
  /** Re-derive enabledSets from the active profile (distribution/version change). */
  reseedFromProfile() {
    if (this.s.profile && D.profiles[this.s.profile]) {
      let sel = this.defaultSelection();
      sel = this.mergeProfile(sel, D.profiles[this.s.profile].selection || {});
      const allSets = this.setNamesForVersion();
      const effDisabled = (sel.disabledSets || []).filter((n) => this.isSetRemovable(n));
      this.s.enabledSets = diff(allSets, effDisabled);
    }
    this.forceNonRemovableSetsOn();
    this.forceNonRemovableLayersOn();
    this.enforceSetRequires();
    this.enforceSubtoggleRequires();
    this.baseline = new Set(this.s.enabledSets);
    this.overrides = new Set();
  },
  setVersion(v) {
    const prevAvailable = this.setNamesForVersion();
    this.s.version = v;
    const nowAvailable = this.setNamesForVersion();
    // Newly-available version-gated sets default on.
    this.s.enabledSets = uniq([...this.s.enabledSets, ...diff(nowAvailable, prevAvailable)]);
    if (this.s.distribution === 'modulargento' && !this.modulargentoAvailable()) {
      this.s.distribution = 'standard';
      this.forceNonRemovableSetsOn();
      this.forceNonRemovableLayersOn();
    }
    this.enforceSetRequires();
  },
  setDistribution(d) {
    if (d === 'modulargento' && !this.modulargentoAvailable()) d = 'standard';
    this.s.distribution = d;
    this.reseedFromProfile();
  },
  /** Apply a profile; returns the module diff + theme change for ripple/tether. */
  applyProfile(name) {
    if (name === this.s.profile) return null;
    const beforeOn = new Set(this.s.enabledSets.filter((n) => this.set(n)?.category === 'module'));
    let sel = this.defaultSelection();
    sel.profile = name;
    sel.distribution = this.s.distribution; // carry distribution across reseed
    sel = this.mergeProfile(sel, D.profiles[name]?.selection || {});
    const prevTheme = this.s.profileGroups.theme;
    this.hydrate(sel);
    const afterOn = new Set(this.s.enabledSets.filter((n) => this.set(n)?.category === 'module'));

    const changed = [];
    let on = 0, off = 0;
    const all = uniq([...beforeOn, ...afterOn, ...this.setNamesForVersion().filter((n) => this.set(n)?.category === 'module')]);
    for (const n of all) {
      const a = beforeOn.has(n), b = afterOn.has(n);
      if (a !== b) { changed.push(n); if (b) on++; else off++; }
    }
    return { changed, on, off, themeChanged: this.s.profileGroups.theme !== prevTheme };
  },
  /** Pick a profile-group option; snap invalid options + clear stale variant picks. */
  setProfileGroup(group, option) {
    this.s.profileGroups = { ...this.s.profileGroups, [group]: option };
    // Clear variant picks so the new context re-snaps to its default.
    this.s.optionVariants = {};
    const notice = this.autoSnapInvalidOptions();
    this.reapplySoftDefaults();
    return notice;
  },
  setOptionVariant(group, option, variant) {
    this.s.optionVariants = { ...this.s.optionVariants, [`${group}.${option}`]: variant };
    this.reapplySoftDefaults();
  },
  autoSnapInvalidOptions() {
    const msgs = [];
    for (const [group, option] of Object.entries(this.s.profileGroups)) {
      if (this.optionMeetsRequires(group, option)) continue;
      const def = this.defaultProfileGroupOption(group);
      if (def === null || def === option) continue;
      const gl = this.profileGroupDef(group)?.label || group;
      const ol = this.optionDef(group, option)?.label || option;
      msgs.push(`${gl} reset to default — ${ol} is no longer compatible with the current selection.`);
      this.s.profileGroups[group] = def;
    }
    return msgs.length ? msgs.join(' ') : null;
  },
  reapplySoftDefaults() {
    // Keep forced add-ons in; merge soft defaults; drop nothing the user kept.
    const forced = this.forcedAddons();
    const defaulted = this.defaultedAddons();
    this.s.enabledAddons = uniq([...this.s.enabledAddons, ...defaulted, ...forced]);
  },
  toggleModule(name) {
    if (!this.isSetRemovable(name)) return;
    const on = !this.s.enabledSets.includes(name);
    this.s.enabledSets = on ? uniq([...this.s.enabledSets, name]) : this.s.enabledSets.filter((x) => x !== name);
    this.overrides.add(name);
    this.enforceSetRequires();
    this.enforceSubtoggleRequires();
  },
  toggleLayer(name) {
    if (!this.isLayerRemovable(name) || this.layer(name)?.stock === false) return;
    const on = !this.s.enabledStockLayers.includes(name);
    this.s.enabledStockLayers = on ? uniq([...this.s.enabledStockLayers, name]) : this.s.enabledStockLayers.filter((x) => x !== name);
    this.enforceSetRequires();
  },
  toggleAddon(name) {
    if (this.forcedAddons().includes(name)) return;
    this.s.enabledAddons = this.s.enabledAddons.includes(name)
      ? this.s.enabledAddons.filter((x) => x !== name)
      : uniq([...this.s.enabledAddons, name]);
  },
  toggleSubtoggle(key) {
    this.s.enabledSubtoggles = this.s.enabledSubtoggles.includes(key)
      ? this.s.enabledSubtoggles.filter((x) => x !== key)
      : uniq([...this.s.enabledSubtoggles, key]);
  },
  toggleOptionSubtoggle(key) {
    this.s.enabledOptionSubtoggles = this.s.enabledOptionSubtoggles.includes(key)
      ? this.s.enabledOptionSubtoggles.filter((x) => x !== key)
      : uniq([...this.s.enabledOptionSubtoggles, key]);
  },

  /* ---------- counts ---------- */
  moduleOnCount() {
    return this.setNamesForVersion().filter((n) => this.set(n)?.category === 'module' && this.moduleOn(n)).length;
  },
  languageOnCount() {
    return this.setNamesForVersion().filter((n) => this.set(n)?.category === 'language' && this.moduleOn(n)).length;
  },
  layerOnCount() {
    const stockOn = this.s.enabledStockLayers.length;
    const forced = this.forcedLayers().filter((n) => this.layer(n)?.stock === false).length;
    return stockOn + forced;
  },

  /* ---------- canonical Selection for POST /api/build + /save ---------- */
  toSelection() {
    const allSets = this.setNamesForVersion();
    const nonRemovableSets = allSets.filter((n) => !this.isSetRemovable(n));
    const disabledSets = diff(diff(allSets, this.s.enabledSets), nonRemovableSets);

    const stock = this.stockLayerNames();
    const nonRemovableLayers = stock.filter((n) => !this.isLayerRemovable(n));
    const disabledLayers = diff(diff(stock, this.s.enabledStockLayers), nonRemovableLayers);

    return {
      version: this.s.version,
      profile: this.s.profile,
      distribution: this.s.distribution,
      disabledSets,
      disabledLayers,
      enabledLayers: [],
      enabledAddons: this.s.enabledAddons,
      profileGroups: this.s.profileGroups,
      disabledSubtoggles: diff(this.allSubtoggleKeys(), this.s.enabledSubtoggles),
      enabledOptionSubtoggles: this.s.enabledOptionSubtoggles,
      optionVariants: this.s.optionVariants,
    };
  },
};
