/* ============================================================
   MageOS Maker — Build Canvas controller (responsive).
   Desktop = 3-pane (sticky spine · canvas · sticky dock).
   Mobile  = single column (chips rail · canvas · bottom sheet).
   One window-scroll model drives both. All derivation is instant via
   maker-engine.js; the server is hit (debounced, with a spinner) only to
   regenerate composer.json + the install tree.
   ============================================================ */
import { engine as E } from './maker-engine.js';

const D = window.MAKER;
const $ = (id) => document.getElementById(id);
const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const CSRF = document.querySelector('meta[name=csrf-token]')?.content || '';

const CHECK_SVG = '<svg viewBox="0 0 12 12" fill="none"><path d="M2.5 6.2l2.2 2.3L9.5 3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

const FULLNAME = { version: 'Version', distribution: 'Distribution', profile: 'Profile', theme: 'Theme & Checkout', addons: 'Add-ons', modules: 'Modules', languages: 'Languages', layers: 'Layers' };
const SHORTNAME = { version: 'Version', distribution: 'Distribution', profile: 'Profile', theme: 'Theme', addons: 'Add-ons', modules: 'Modules', languages: 'Languages', layers: 'Layers' };

let current = 'version';
const changed = {};
let modFilter = 'all';
let modQuery = '';

const ui = {
  savedId: D.initial.savedId || null,
  savedSnapshot: D.initial.savedSnapshot || null,
  prevPkgs: parseInt($('dock-pkgs')?.textContent || '0', 10) || 0,
  hyvaToken: '', hyvaProject: '',
};

function order() {
  return ['version', E.modulargentoAvailable() ? 'distribution' : null, 'profile', 'theme', 'addons', 'modules', 'languages', 'layers'].filter(Boolean);
}
function isMobile() { return window.matchMedia('(max-width: 1023px)').matches; }
function profileLabel() { return D.profiles[E.s.profile]?.label || E.s.profile || 'Custom'; }
function optLabel(group) { const o = E.s.profileGroups[group]; return E.optionDef(group, o)?.label || o || ''; }

/* ---------- step values + summary ---------- */
function val(id) {
  switch (id) {
    case 'version': return E.s.version;
    case 'distribution': return E.s.distribution === 'modulargento' ? 'Modular' : 'Standard';
    case 'profile': return profileLabel();
    case 'theme': return optLabel('theme');
    case 'addons': { const n = E.effectiveAddons().filter((a) => E.isAddonAvailable(a)).length; return n ? `${n} added` : 'None'; }
    case 'modules': return `${E.moduleOnCount()} on`;
    case 'languages': return `${E.languageOnCount()} locales`;
    case 'layers': return `${E.layerOnCount()} on`;
  }
  return '';
}
function updateSummary() {
  const meta = `${E.s.version} · ${E.s.distribution === 'modulargento' ? 'Modular' : 'Standard'} · ${optLabel('theme')} · ${optLabel('checkout')}`;
  $('spine-meta').textContent = meta;
  $('m-meta').textContent = meta;
  $('dock-meta').textContent = meta;
  const pf = profileLabel();
  $('spine-pf').textContent = `Mage-OS ${pf}`;
  $('m-pf').textContent = `Mage-OS ${pf}`;
  $('dock-ttl').textContent = `Mage-OS ${pf}`;
  document.querySelectorAll('[data-curval]').forEach((el) => { el.textContent = val(el.getAttribute('data-curval')); });
}

/* ---------- spine + chips nav ---------- */
function buildNav() {
  const ORDER = order();
  const idx = ORDER.indexOf(current);
  const GROUPS = [
    { name: 'Foundation', items: ['version', 'distribution', 'profile'].filter((i) => ORDER.includes(i)) },
    { name: 'Storefront', items: ['theme', 'addons'] },
    { name: 'Packages', items: ['modules', 'languages', 'layers'] },
  ];
  $('spine').innerHTML = GROUPS.map((g) => '<div class="stepgrp"><div class="grpname">' + g.name + '</div>' + g.items.map((id) => {
    const pos = ORDER.indexOf(id), done = pos < idx, cur = id === current;
    return '<div class="step' + (done ? ' done' : '') + (cur ? ' current' : '') + (changed[id] ? ' changed' : '') + '" data-goto="' + id + '">' +
      '<span class="ping"></span><span class="dot">' + CHECK_SVG + '</span>' +
      '<span class="stbody"><span class="stname">' + FULLNAME[id] + '</span><span class="stval">' + esc(val(id)) + '</span></span></div>';
  }).join('') + '</div>').join('');

  $('chips').innerHTML = ORDER.map((id, i) => {
    const done = i < idx, cur = id === current;
    return '<div class="chip' + (done ? ' done' : '') + (cur ? ' current' : '') + (changed[id] ? ' changed' : '') + '" data-goto="' + id + '">' +
      '<span class="badge-dot"></span><span class="cdot">' + CHECK_SVG + '</span>' + SHORTNAME[id] + '</div>';
  }).join('');
  const ch = $('chips'); const c = ch.querySelector('[data-goto="' + current + '"]');
  if (c) ch.scrollLeft = Math.max(0, c.offsetLeft - 44);
}
function pulseSection(id) {
  changed[id] = true; buildNav();
  setTimeout(() => { delete changed[id]; buildNav(); }, 5000);
}

/* ---------- section: version ---------- */
function buildVersion() {
  const locked = E.s.distribution === 'modulargento';
  $('version-grid').innerHTML = [...D.versions].reverse().map((v) => {
    const sel = v === E.s.version, dis = locked && !sel;
    const badge = v === D.latestStable ? ' <span class="badge green">latest stable</span>'
      : (v.includes('-p') ? ' <span class="badge gray">security</span>' : '');
    return '<div class="rcard' + (sel ? ' sel' : '') + (dis ? ' disabled' : '') + '" data-version="' + esc(v) + '"><span class="rdot"></span><div><div class="rt">' + esc(v) + badge + '</div><div class="rd">Mage-OS ' + esc(v) + ' release line.</div></div></div>';
  }).join('');
  $('version-note').innerHTML = locked
    ? '<div class="infonote"><svg class="ic" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3"/><path d="M8 7.5v3.5M8 5.2v.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg><p>Locked to ' + esc(E.s.version) + ' — the fully-modular distribution is only published for this version. Switch to Standard in <b>Distribution</b> to change it.</p></div>'
    : '';
}

/* ---------- section: distribution ---------- */
function buildDistribution() {
  const avail = E.modulargentoAvailable();
  $('sec-distribution').hidden = !avail;
  if (!avail) return;
  const d = E.s.distribution;
  $('distribution-grid').innerHTML =
    '<div class="rcard' + (d === 'standard' ? ' sel' : '') + '" data-distribution="standard"><span class="big-ic"><svg width="17" height="17" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.4"/></svg></span><div><div class="rt">Standard Mage-OS</div><div class="rd">The canonical <code>mage-os/*</code> metapackages. Some sets are locked on.</div></div></div>' +
    '<div class="rcard' + (d === 'modulargento' ? ' sel' : '') + '" data-distribution="modulargento"><span class="big-ic"><svg width="17" height="17" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="9" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="2" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="9" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/></svg></span><div><div class="rt">Fully modular <span class="badge auto">modulargento</span></div><div class="rd">Every module independently versioned — decoupled, so every set below is removable.</div></div></div>';
}

/* ---------- section: profile ---------- */
function buildProfiles() {
  $('profile-grid').innerHTML = Object.values(D.profiles).map((p) => {
    const sel = p.name === E.s.profile;
    const hint = !p.default ? '<div class="affecthint"><svg viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>Updates Modules below</div>' : '';
    return '<div class="rcard' + (sel ? ' sel' : '') + '" data-profile="' + esc(p.name) + '"><span class="rdot"></span><div><div class="rt">' + esc(p.label) + '</div><div class="rd">' + esc(p.description) + '</div>' + hint + '</div></div>';
  }).join('');
}

/* ---------- section: theme & checkout (profile-groups, variants, subtoggles) ---------- */
function buildThemeCheckout() {
  $('theme-checkout-host').innerHTML = D.profileGroups.map((group) => {
    const gName = group.name;
    const opts = (group.options || []).map((opt) => {
      const available = E.optionMeetsRequires(gName, opt.name);
      const prefer = E.optionPreferAlternative(gName, opt.name);
      const picked = (E.s.profileGroups[gName] ?? null) === opt.name;
      let hint = '';
      if (!available) {
        const reqs = Object.entries(opt.requires?.profileGroups || {}).map(([g, needed]) => {
          const rl = E.profileGroupDef(g)?.label || g;
          const ol = E.optionDef(g, needed)?.label || needed;
          return rl + ' = ' + ol;
        });
        hint = 'Needs ' + reqs.join(', ') + '.';
      } else if (prefer) {
        const altLabel = E.optionDef(gName, prefer.use)?.label || prefer.use;
        hint = prefer.reason ? 'Prefer ' + altLabel + ' — ' + prefer.reason + '.' : 'Prefer ' + altLabel + '.';
      }
      let inner = '';
      if (picked && available && (opt.variants || []).length) {
        const activeVariant = E.optionActiveVariant(gName, opt.name);
        inner = '<div class="subopts">' + opt.variants.map((variant) => {
          const vAvail = Object.entries(variant.requires?.profileGroups || {}).every(([g, n]) => (E.s.profileGroups[g] ?? null) === n);
          const isActive = activeVariant === variant.name;
          let subs = '';
          if (isActive && vAvail && (variant.subtoggles || []).length) {
            subs = '<div class="subopts" style="margin-left:14px">' + variant.subtoggles.map((sub) => {
              const key = gName + '.' + opt.name + '.' + variant.name + '.' + sub.name;
              const on = E.s.enabledOptionSubtoggles.includes(key);
              return '<label class="chk mini' + (on ? ' on' : '') + '" data-optsub="' + esc(key) + '"><span class="box"></span><span class="label">' + esc(sub.label) + '</span></label>';
            }).join('') + '</div>';
          }
          return '<label class="chk mini opt-radio' + (vAvail ? '' : ' is-disabled') + '"' + (vAvail ? ' data-variant="' + esc(gName + '|' + opt.name + '|' + variant.name) + '"' : '') + '><span class="dot' + (isActive ? ' on' : '') + '"></span><span class="label">' + esc(variant.label) + '</span></label>' + subs;
        }).join('') + '</div>';
      } else if (picked && available && (opt.subtoggles || []).length) {
        inner = '<div class="subopts">' + opt.subtoggles.map((sub) => {
          const key = gName + '.' + opt.name + '.' + sub.name;
          const on = E.s.enabledOptionSubtoggles.includes(key);
          return '<label class="chk mini' + (on ? ' on' : '') + '" data-optsub="' + esc(key) + '"><span class="box"></span><span class="label">' + esc(sub.label) + '</span></label>';
        }).join('') + '</div>';
      }
      return '<div class="rcard' + (picked ? ' sel' : '') + (available ? '' : ' disabled') + '"' + (available && !picked ? ' data-option="' + esc(gName + '|' + opt.name) + '"' : '') + '><span class="rdot"></span><div style="flex:1"><div class="rt">' + esc(opt.label) + '</div>' + (hint ? '<div class="rd">' + esc(hint) + '</div>' : '') + inner + '</div></div>';
    }).join('');
    return '<div><div class="col-lbl">' + esc(group.label) + '</div><div class="rcardgrid">' + opts + '</div></div>';
  }).join('');
}

/* ---------- section: add-ons ---------- */
function buildAddons() {
  const forced = E.forcedAddons();
  const defaulted = E.defaultedAddons();
  $('addon-host').innerHTML = Object.values(D.addons).filter((a) => E.isAddonAvailable(a.name)).map((a) => {
    const isForced = forced.includes(a.name);
    const isAuto = !isForced && defaulted.includes(a.name);
    // Add-ons pulled in automatically by the theme/checkout (forced or
    // soft-defaulted) are locked: greyed out and not user-toggleable.
    const locked = isForced || isAuto;
    const on = locked || E.s.enabledAddons.includes(a.name);
    const badge = isForced ? ' <span class="badge req">required</span>' : (isAuto ? ' <span class="badge auto">auto</span>' : '');
    return '<label class="langcard addon-card' + (on ? ' on' : '') + (locked ? ' forced' : '') + '"' + (locked ? '' : ' data-addon="' + esc(a.name) + '"') + '><div class="grow"><div class="ln">' + esc(a.label) + badge + '</div><div class="lc addon-desc">' + esc(a.description) + '</div></div><span class="tick"></span></label>';
  }).join('');
}

/* ---------- section: modules ---------- */
function originPill(name) {
  const o = E.origin(name);
  if (o === 'req') return '<span class="origin req">required</span>';
  if (o === 'you') return '<span class="origin you">your choice</span>';
  if (o === 'profile') return '<span class="origin profile">via ' + esc(profileLabel()) + '</span>';
  return '';
}
function buildModules() {
  const sets = E.setsForVersion();
  const groups = {};
  D.moduleGroupOrder.forEach((g) => { groups[g.label] = []; });
  for (const [name, s] of Object.entries(sets)) {
    if (s.category !== 'module') continue;
    const g = s.group || 'Other';
    (groups[g] = groups[g] || []).push(name);
  }
  $('modgrid-host').innerHTML = Object.entries(groups).filter(([, names]) => names.length).map(([label, names]) => {
    const onCount = names.filter((n) => E.moduleOn(n)).length;
    const cards = names.map((name) => {
      const s = E.set(name);
      const on = E.moduleOn(name);
      const reqMet = E.setRequiresMet(name);
      const removable = E.isSetRemovable(name);
      const disabled = !removable || !reqMet;
      let needs = '';
      if (!reqMet) {
        const rl = E.setRequiredSet(name) ? (E.set(E.setRequiredSet(name))?.label || E.setRequiredSet(name))
          : (E.layer(E.setRequiredLayer(name))?.label || E.setRequiredLayer(name));
        needs = ' <span class="badge gray">needs ' + esc(rl) + '</span>';
      }
      const hay = (name + ' ' + s.label + ' ' + s.description).toLowerCase().replace(/[^a-z0-9 ]/g, '');
      let subs = '';
      if ((s.subtoggles || []).length) {
        subs = '<div class="subopts" data-stop="1">' + s.subtoggles.map((sub) => {
          const key = name + '.' + sub.name;
          const subReq = sub.requires?.set ?? null;
          const subOk = subReq === null || E.s.enabledSets.includes(subReq);
          const subDisabled = !on || !subOk;
          const subOn = E.s.enabledSubtoggles.includes(key);
          return '<label class="chk mini' + (subOn ? ' on' : '') + (subDisabled ? ' is-disabled' : '') + '"' + (subDisabled ? '' : ' data-subtoggle="' + esc(key) + '"') + '><span class="box"></span><span class="label">' + esc(sub.label) + '</span></label>';
        }).join('') + '</div>';
      }
      return '<div class="modcard" data-mod="' + esc(name) + '" data-name="' + esc(hay) + '" data-on="' + (on ? '1' : '0') + '" data-req="' + (!removable ? '1' : '0') + '">' +
        '<div class="chk ' + (on ? 'on' : '') + (disabled ? ' disabled' : '') + '"' + (disabled ? '' : ' data-modtoggle="' + esc(name) + '"') + '>' +
        '<div class="box"></div><div class="mtext"><div class="label">' + esc(s.label) + ' ' + originPill(name) + needs + '</div><div class="desc">' + esc(s.description) + '</div>' + subs + '</div></div></div>';
    }).join('');
    return '<div class="catblock" data-cat="' + esc(label) + '"><div class="cathead"><span class="ct-name">' + esc(label) + '</span><span class="ct-line"></span><span class="ct-count"><b data-on>' + onCount + '</b> / ' + names.length + ' on</span></div><div class="modgrid">' + cards + '</div></div>';
  }).join('');
  applyFilter();
}
function applyFilter() {
  document.querySelectorAll('#modgrid-host .catblock').forEach((block) => {
    let shown = 0;
    block.querySelectorAll('.modcard').forEach((card) => {
      const on = card.getAttribute('data-on') === '1';
      const req = card.getAttribute('data-req') === '1';
      let ok = true;
      if (modFilter === 'enabled') ok = on;
      else if (modFilter === 'required') ok = req;
      else if (modFilter === 'off') ok = !on;
      if (ok && modQuery) ok = card.getAttribute('data-name').indexOf(modQuery) !== -1;
      card.style.display = ok ? '' : 'none';
      if (ok) shown++;
    });
    block.style.display = shown ? '' : 'none';
  });
}

/* ---------- section: languages ---------- */
function buildLangs() {
  const avail = E.setsForVersion();
  $('lang-grid').innerHTML = Object.entries(D.languages).filter(([name]) => avail[name]).map(([name, l]) => {
    const on = E.moduleOn(name);
    return '<label class="langcard' + (on ? ' on' : '') + '" data-lang="' + esc(name) + '"><span class="lcode">' + esc(l.code) + '</span><div><div class="ln">' + esc(l.label) + '</div><div class="lc">' + esc(l.locale) + '</div></div><span class="grow"></span><span class="tick"></span></label>';
  }).join('');
}

/* ---------- section: layers ---------- */
function buildLayers() {
  const forced = E.forcedLayers();
  $('layer-host').innerHTML = Object.values(D.layers).map((l) => {
    if (l.stock !== false) {
      const removable = E.isLayerRemovable(l.name);
      const on = E.s.enabledStockLayers.includes(l.name);
      const badge = removable ? '' : ' <span class="badge req">required</span>';
      return '<label class="layerrow' + (removable ? '' : ' forced') + '"' + (removable ? ' data-layer="' + esc(l.name) + '"' : '') + '><div class="grow"><div class="lt">' + esc(l.label) + badge + '</div><div class="ld">' + esc(l.description) + '</div></div><span class="switch' + (on ? ' on' : '') + (removable ? '' : ' switch-locked') + '"></span></label>';
    }
    const isForced = forced.includes(l.name);
    const badge = isForced ? '<span class="badge req">required</span>' : '<span class="badge auto">auto</span>';
    return '<div class="layerrow forced"><div class="grow"><div class="lt">' + esc(l.label) + ' ' + badge + '</div><div class="ld">' + esc(l.description) + '</div></div><span class="switch ' + (isForced ? 'on ' : '') + 'switch-locked"></span></div>';
  }).join('');
}

/* ---------- master re-render ---------- */
function renderAll() {
  buildVersion(); buildDistribution(); buildProfiles(); buildThemeCheckout();
  buildAddons(); buildModules(); buildLangs(); buildLayers();
  updateSummary(); buildNav(); syncHyvaTab(); refreshBougie();
  fitScrollSpacer();
}

/* ---------- change narration (dock strip + mobile toast) ---------- */
let toastTimer = null;
function logChange(summaryHTML, jump) {
  const box = $('changelog');
  box.innerHTML = '<span class="cl-item"><span class="cl-ic"></span><span class="cl-tx">' + summaryHTML + '</span>' + (jump ? '<span class="cl-link" data-jump="' + jump + '">Show me</span>' : '') + '</span>';
  box.classList.add('show');
  clearTimeout(box._t); box._t = setTimeout(() => box.classList.remove('show'), 7000);
  const t = $('m-toast');
  t.innerHTML = '<span class="ic"></span><span class="tx">' + summaryHTML + '</span>' + (jump ? '<button class="act" data-jump="' + jump + '">Show me</button>' : '');
  t.classList.add('show');
  clearTimeout(toastTimer); toastTimer = setTimeout(() => t.classList.remove('show'), 7000);
}
function hideToast() { $('m-toast').classList.remove('show'); }

/* ---------- scroll + jump (window) ---------- */
function jumpTo(id) {
  if (id === 'hyva') { openSheet(); activateOutTab('hyva'); return; }
  const el = $('sec-' + id);
  if (!el || el.hidden) return;
  const offset = isMobile() ? 104 : 62;
  const target = window.scrollY + el.getBoundingClientRect().top - offset;
  window.scrollTo({ top: Math.max(0, target), behavior: 'smooth' });
}
function onScroll() {
  const ORDER = order();
  const offset = isMobile() ? 130 : 90;
  const y = window.scrollY + offset;
  let found = ORDER[0];
  ORDER.forEach((id) => {
    const el = $('sec-' + id);
    if (el && !el.hidden && (window.scrollY + el.getBoundingClientRect().top) <= y) found = id;
  });
  // Safety net for the very bottom (mainly mobile, where the spacer below is
  // not applied): pin the last section once the page is fully scrolled.
  if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2) {
    found = ORDER[ORDER.length - 1];
  }
  if (found !== current) { current = found; buildNav(); }
}

/**
 * Pad the canvas so the last (often short) section can scroll its top up to the
 * scrollspy offset line. Without this the page bottom is reached while an
 * earlier short section is still under the line, so the nav jumps straight to
 * the last section — skipping the one before it (Modules → Layers, skipping
 * Languages). Desktop only; the mobile layout has its own bottom spacer.
 */
function fitScrollSpacer() {
  const canvas = $('canvas');
  if (!canvas) return;
  if (isMobile()) { canvas.style.paddingBottom = ''; return; }
  const ORDER = order();
  const last = $('sec-' + ORDER[ORDER.length - 1]);
  if (!last) return;
  const need = Math.max(0, window.innerHeight - 90 - last.offsetHeight - 16);
  canvas.style.paddingBottom = need + 'px';
}

/* ---------- output tabs + Hyvä ---------- */
function activateOutTab(id) {
  document.querySelectorAll('[data-otab]').forEach((t) => t.classList.toggle('active', t.getAttribute('data-otab') === id));
  document.querySelectorAll('[data-opane]').forEach((p) => p.classList.toggle('show', p.getAttribute('data-opane') === id));
}
function syncHyvaTab(autoOpen) {
  const tab = $('hyva-tab');
  if (!tab) return;
  if (E.usesHyva()) { tab.style.display = ''; if (autoOpen && !isMobile()) activateOutTab('hyva'); }
  else { tab.style.display = 'none'; if (tab.classList.contains('active')) activateOutTab('composer'); }
}
function wireHyvaInputs() {
  const tok = $('hy-token'), proj = $('hy-project');
  const upd = () => {
    ui.hyvaToken = (tok?.value || '').trim();
    ui.hyvaProject = (proj?.value || '').trim();
    const tv = ui.hyvaToken || 'YOUR_HYVA_TOKEN';
    const pv = ui.hyvaProject || 'yourProjectName';
    document.querySelectorAll('.hy-var[data-var="token"]').forEach((e) => { e.textContent = tv; });
    document.querySelectorAll('.hy-var[data-var="project"]').forEach((e) => { e.textContent = pv; });
    scheduleBuild();
  };
  if (tok) tok.addEventListener('input', upd);
  if (proj) proj.addEventListener('input', upd);
}

/* ---------- bottom sheet ---------- */
function openSheet() { $('dock').classList.add('open'); $('sheet-scrim').classList.add('show'); }
function closeSheet() { $('dock').classList.remove('open'); $('sheet-scrim').classList.remove('show'); }

/* ---------- dirty tracking + bougie callout ---------- */
function snapshot() {
  const sortDeep = (v) => {
    if (Array.isArray(v)) return v.map(sortDeep).sort((a, b) => (JSON.stringify(a) < JSON.stringify(b) ? -1 : 1));
    if (v && typeof v === 'object') {
      const o = {};
      Object.keys(v).sort().forEach((k) => { o[k] = sortDeep(v[k]); });
      return o;
    }
    return v;
  };
  return JSON.stringify(sortDeep(E.toSelection()));
}
function effectiveSaved() {
  if (!ui.savedId || ui.savedSnapshot === null) return ui.savedId;
  return snapshot() === ui.savedSnapshot ? ui.savedId : null;
}
function refreshBougie() {
  const saved = effectiveSaved();
  $('bg-pitch').style.display = saved ? 'none' : '';
  $('bg-result').style.display = saved ? '' : 'none';
}

/* ---------- debounced server build ---------- */
let buildTimer = null;
let buildSeq = 0;
function setBusy(b) { $('dock').classList.toggle('busy', b); $('dock-sum').classList.toggle('busy', b); }
function scheduleBuild() {
  clearTimeout(buildTimer);
  buildTimer = setTimeout(runBuild, 300);
  refreshBougie();
}
async function runBuild() {
  const seq = ++buildSeq;
  setBusy(true);
  try {
    const res = await fetch('/api/build', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
      body: JSON.stringify({ selection: E.toSelection(), hyvaProject: ui.hyvaProject }),
    });
    if (!res.ok) throw new Error('build ' + res.status);
    const r = await res.json();
    if (seq !== buildSeq) return; // a newer build superseded this one
    window.paintComposer(r.composerJson);
    $('install-tree-pane').innerHTML = r.installTreeHtml;
    $('require-count').textContent = r.requireCount;
    $('replace-count').textContent = r.replaceCount;
    $('tree-count').textContent = r.treeMeta.count;
    setPackageCount(r.packageCount);
    const tab = $('hyva-tab');
    if (tab && !r.usesHyva && tab.classList.contains('active')) activateOutTab('composer');
    if (tab) tab.style.display = r.usesHyva ? '' : 'none';
  } catch (e) {
    /* leave the last good output in place */
  } finally {
    if (seq === buildSeq) setBusy(false);
  }
}
function setPackageCount(pkgs) {
  $('dock-pkgs').textContent = pkgs;
  $('m-pkgs').textContent = pkgs;
  if (ui.prevPkgs && ui.prevPkgs !== pkgs) {
    const d = pkgs - ui.prevPkgs;
    ['dock-delta', 'm-delta'].forEach((id) => {
      const el = $(id);
      el.className = (id === 'dock-delta' ? 'delta show ' : 'delta show ') + (d > 0 ? 'up' : 'down');
      el.textContent = (d > 0 ? '+' : '') + d;
      clearTimeout(el._t); el._t = setTimeout(() => el.classList.remove('show'), 4500);
    });
  }
  ui.prevPkgs = pkgs;
}

/* ---------- save (bougie) ---------- */
async function doSave(btn) {
  if (btn) { btn.disabled = true; }
  try {
    const res = await fetch('/save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
      body: JSON.stringify({ selection: E.toSelection() }),
    });
    if (!res.ok) throw new Error('save ' + res.status);
    const r = await res.json();
    ui.savedId = r.id;
    ui.savedSnapshot = snapshot();
    $('bougie-starter').textContent = r.starterArg;
    $('bougie-share').textContent = r.starterArg;
    history.replaceState({}, '', r.url);
    refreshBougie();
  } catch (e) {
    /* ignore */
  } finally {
    if (btn) btn.disabled = false;
  }
}

/* ---------- profile switch (the headline dependency) ---------- */
function switchProfile(name) {
  const diff = E.applyProfile(name);
  if (!diff) return;
  renderAll();
  setTimeout(() => {
    diff.changed.forEach((n) => {
      const card = document.querySelector('.modcard[data-mod="' + (window.CSS && CSS.escape ? CSS.escape(n) : n) + '"]');
      if (card) { card.classList.add('flash'); setTimeout(() => card.classList.remove('flash'), 2600); }
    });
  }, 40);
  if (diff.changed.length) pulseSection('modules');
  if (diff.themeChanged) pulseSection('theme');
  const p = D.profiles[name];
  const tether = $('profile-tether');
  if (diff.changed.length || diff.themeChanged) {
    const seg = [];
    if (diff.off) seg.push('−' + diff.off);
    if (diff.on) seg.push('+' + diff.on);
    $('tether-tt').textContent = diff.changed.length ? (diff.changed.length + ' modules changed below') : 'Theme updated below';
    $('tether-td').textContent = (seg.length ? seg.join(' / ') + ' modules' : '') + (diff.themeChanged ? (seg.length ? ' · ' : '') + 'theme → ' + optLabel('theme') : '');
    tether.classList.add('show');
    tether.setAttribute('data-goto', diff.changed.length ? 'modules' : 'theme');
    const bits = '<b>' + esc(p.label) + '</b>' + (seg.length ? ' · ' + seg.join(' / ') + ' modules' : '') + (diff.themeChanged ? ' · theme → ' + esc(optLabel('theme')) : '');
    logChange(bits, diff.changed.length ? 'modules' : 'theme');
  } else {
    tether.classList.remove('show');
    logChange('<b>' + esc(p.label) + '</b> · no module changes', null);
  }
  scheduleBuild();
}

/* ---------- wiring ---------- */
function wire() {
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', () => { buildNav(); fitScrollSpacer(); });

  document.addEventListener('click', (e) => {
    const jmp = e.target.closest('[data-jump]');
    if (jmp) { jumpTo(jmp.getAttribute('data-jump')); hideToast(); return; }
    const go = e.target.closest('[data-goto]');
    if (go && !e.target.closest('[data-option],[data-variant],[data-optsub]')) { jumpTo(go.getAttribute('data-goto')); return; }

    const ver = e.target.closest('[data-version]');
    if (ver) { E.setVersion(ver.getAttribute('data-version')); renderAll(); scheduleBuild(); return; }
    const dist = e.target.closest('[data-distribution]');
    if (dist) { E.setDistribution(dist.getAttribute('data-distribution')); renderAll(); scheduleBuild(); return; }
    const prof = e.target.closest('[data-profile]');
    if (prof) { switchProfile(prof.getAttribute('data-profile')); return; }

    const optsub = e.target.closest('[data-optsub]');
    if (optsub) { E.toggleOptionSubtoggle(optsub.getAttribute('data-optsub')); buildThemeCheckout(); buildAddons(); updateSummary(); syncHyvaTab(); scheduleBuild(); return; }
    const variant = e.target.closest('[data-variant]');
    if (variant) { const [g, o, v] = variant.getAttribute('data-variant').split('|'); E.setOptionVariant(g, o, v); buildThemeCheckout(); buildAddons(); updateSummary(); syncHyvaTab(); scheduleBuild(); return; }
    const option = e.target.closest('[data-option]');
    if (option) {
      const [g, o] = option.getAttribute('data-option').split('|');
      const prevHyva = E.usesHyva();
      E.setProfileGroup(g, o);
      renderAll();
      if (E.usesHyva() && !prevHyva) { syncHyvaTab(true); logChange('Theme → <b>Hyvä</b> · <b>★ Hyvä setup</b> steps added', null); }
      else if (!E.usesHyva() && prevHyva) { logChange('Theme → <b>' + esc(optLabel('theme')) + '</b> · Hyvä setup no longer needed', null); }
      scheduleBuild();
      return;
    }

    const mt = e.target.closest('[data-modtoggle]');
    if (mt) { E.toggleModule(mt.getAttribute('data-modtoggle')); buildModules(); updateSummary(); buildNav(); scheduleBuild(); return; }
    const st = e.target.closest('[data-subtoggle]');
    if (st) { E.toggleSubtoggle(st.getAttribute('data-subtoggle')); buildModules(); scheduleBuild(); return; }
    const lang = e.target.closest('[data-lang]');
    if (lang) { E.toggleModule(lang.getAttribute('data-lang')); buildLangs(); updateSummary(); buildNav(); scheduleBuild(); return; }
    const addon = e.target.closest('[data-addon]');
    if (addon) { E.toggleAddon(addon.getAttribute('data-addon')); buildAddons(); updateSummary(); syncHyvaTab(); scheduleBuild(); return; }
    const layer = e.target.closest('[data-layer]');
    if (layer) { E.toggleLayer(layer.getAttribute('data-layer')); buildLayers(); buildModules(); updateSummary(); buildNav(); scheduleBuild(); return; }

    const chip = e.target.closest('.fchip');
    if (chip) { document.querySelectorAll('.fchip').forEach((c) => c.classList.toggle('on', c === chip)); modFilter = chip.getAttribute('data-filter'); applyFilter(); return; }

    if (e.target.closest('#open-sheet')) { openSheet(); return; }
    if (e.target.closest('#sheet-scrim') || e.target.closest('.dock-grab')) { closeSheet(); return; }
    if (e.target.closest('#bougie-save') || e.target.closest('#dock-save')) { doSave(e.target.closest('#bougie-save') || e.target.closest('#dock-save')); return; }
    if (e.target.closest('#bougie-edit')) { ui.savedSnapshot = '__edit__'; refreshBougie(); return; }

    const otab = e.target.closest('[data-otab]');
    if (otab) { activateOutTab(otab.getAttribute('data-otab')); return; }
  });

  const search = $('mod-search');
  if (search) search.addEventListener('input', () => { modQuery = (search.value || '').trim().toLowerCase(); applyFilter(); });
}

document.addEventListener('DOMContentLoaded', () => {
  E.init();
  renderAll();
  wire();
  wireHyvaInputs();
});
