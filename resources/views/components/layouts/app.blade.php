<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>mageos-maker</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.10.0/build/styles/atom-one-dark.min.css">
    <script src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.10.0/build/highlight.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.10.0/build/languages/json.min.js"></script>
    @vite('resources/js/app.js')
    <style>
        /* ============================================================
           Design tokens — mirror the design bundle's maker.css
           ============================================================ */
        :root {
            --gray-50:#f9fafb; --gray-100:#f3f4f6; --gray-150:#eef0f3; --gray-200:#e5e7eb;
            --gray-300:#d1d5db; --gray-400:#9ca3af; --gray-500:#6b7280; --gray-600:#4b5563;
            --gray-700:#374151; --gray-800:#1f2937; --gray-900:#111827;
            --blue-50:#eff6ff; --blue-100:#dbeafe; --blue-600:#2563eb; --blue-700:#1d4ed8;
            --indigo-50:#eef2ff; --indigo-100:#e0e7ff; --indigo-600:#4f46e5; --indigo-700:#4338ca;
            --green-50:#f0fdf4; --green-500:#22c55e; --green-600:#16a34a;
            --amber-100:#fef3c7; --amber-700:#b45309;
            --purple-100:#f3e8ff; --purple-700:#7e22ce;
            --code-bg:#15171c; --code-bg-2:#1d2026;
            --sans: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            --mono: ui-monospace, "SFMono-Regular", "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
            --ring: 0 0 0 3px rgba(37, 99, 235, 0.18);
            --shadow-sm: 0 1px 2px rgba(16, 24, 40, 0.05);
            --shadow-md: 0 4px 12px rgba(16, 24, 40, 0.08);
            --shadow-lg: 0 12px 32px rgba(16, 24, 40, 0.14);
            --appbar-h: 48px; --chips-h: 0px;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: var(--sans); color: var(--gray-900); background: var(--gray-100);
            -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility; min-height: 100vh; }
        [x-cloak] { display: none !important; }

        /* ---- App bar (black) ---- */
        .appbar { height: 44px; background: #0b0c0e; color: #fff; display: flex; align-items: center; gap: 14px; padding: 0 18px; font-size: 13px; }
        .appbar .brand { font-weight: 600; letter-spacing: -0.01em; display: flex; align-items: center; gap: 8px; }
        .appbar .brand .glyph { width: 18px; height: 18px; border-radius: 5px; background: linear-gradient(135deg, #ff7a45, #f6431b); display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; color: #fff; }
        .appbar .sp { flex: 1; }
        .appbar .ghost { color: #9aa1ab; font-size: 12.5px; text-decoration: none; cursor: pointer; }
        .appbar .ghost:hover { color: #fff; }

        /* ---- Shared atoms (maker.css base) ---- */
        .input { width: 100%; appearance: none; border: 1px solid var(--gray-300); border-radius: 7px; padding: 8px 12px; font-size: 13px; font-family: var(--sans); color: var(--gray-900); background: #fff; }
        .input::placeholder { color: var(--gray-400); }
        .input:focus { outline: none; border-color: var(--blue-600); box-shadow: var(--ring); }
        .search { position: relative; }
        .search input { padding-left: 32px; }
        .search .ic { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; color: var(--gray-400); pointer-events: none; }
        .vh { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

        .badge { font-size: 10px; font-weight: 600; letter-spacing: 0.02em; padding: 1px 6px; border-radius: 4px; line-height: 1.5; white-space: nowrap; }
        .badge.req { background: var(--blue-50); color: var(--blue-700); }
        .badge.auto { background: var(--purple-100); color: var(--purple-700); }
        .badge.comm { background: var(--amber-100); color: var(--amber-700); }
        .badge.gray { background: var(--gray-100); color: var(--gray-600); }
        .badge.green { background: var(--green-50); color: var(--green-600); }

        .btn { font-family: var(--sans); font-size: 13px; font-weight: 600; border-radius: 7px; padding: 8px 14px; border: 1px solid transparent; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; line-height: 1; }
        .btn-primary { background: var(--blue-600); color: #fff; }
        .btn-primary:hover { background: var(--blue-700); }
        .btn-ghost { background: #fff; color: var(--gray-700); border-color: var(--gray-300); }
        .btn-ghost:hover { background: var(--gray-50); }
        .btn-sm { padding: 6px 11px; font-size: 12px; }

        .code { background: var(--code-bg); color: #d7dbe2; border-radius: 10px; font-family: var(--mono); font-size: 12.5px; line-height: 1.6; overflow: hidden; }
        .code pre { margin: 0; padding: 16px 18px; overflow: auto; }
        .tok-key { color: #79c0ff; } .tok-str { color: #a5d6a3; } .tok-punct { color: #8b949e; }
        .tok-com { color: #6e7681; font-style: italic; } .tok-cmd { color: #d7dbe2; } .tok-flag { color: #f0a868; }

        .countchip { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; background: var(--gray-100); color: var(--gray-600); padding: 2px 8px; border-radius: 999px; }
        .countchip b { color: var(--gray-900); }
        .scrollbox { overflow: auto; }
        .scrollbox::-webkit-scrollbar { width: 9px; height: 9px; }
        .scrollbox::-webkit-scrollbar-thumb { background: var(--gray-300); border-radius: 6px; }
        .scrollbox::-webkit-scrollbar-track { background: transparent; }

        /* checkbox / radio rows (shared by modules, variants, subtoggles) */
        .chk { display: flex; gap: 10px; align-items: flex-start; }
        .chk .box { flex: none; width: 16px; height: 16px; border-radius: 4px; border: 1.5px solid var(--gray-300); background: #fff; margin-top: 1px; position: relative; }
        .chk.on .box { background: var(--green-600); border-color: var(--green-600); }
        .chk.on .box::after { content: ""; position: absolute; left: 4.5px; top: 1.5px; width: 4px; height: 8px; border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(42deg); }
        .chk .label { font-size: 13px; font-weight: 600; color: var(--gray-900); display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
        .chk .desc { font-size: 12px; color: var(--gray-500); margin-top: 1px; line-height: 1.4; }
        .chk.disabled { opacity: 0.55; cursor: not-allowed; }
        .chk.disabled .label { color: var(--gray-500); }
        .subopts { display: block; margin: 6px 0 2px 2px; padding-left: 12px; border-left: 2px solid var(--gray-200); }
        .chk.mini { padding: 4px 0; cursor: pointer; }
        .chk.mini .label { font-size: 12.5px; font-weight: 500; }
        .chk .dot { flex: none; width: 16px; height: 16px; border-radius: 50%; border: 1.5px solid var(--gray-300); margin-top: 1px; position: relative; background: #fff; }
        .chk .dot.on { border-color: var(--blue-600); }
        .chk .dot.on::after { content: ""; position: absolute; inset: 3px; border-radius: 50%; background: var(--blue-600); }
        .chk.is-disabled { opacity: 0.5; cursor: not-allowed; }

        /* ============================================================
           Build Canvas page (ported from the design bundle's
           "MageOS Maker - Build Canvas Responsive.html")
           ============================================================ */
        html, body { background: var(--gray-100); }
        .shell { min-height: 100vh; }
        .appbar { position: sticky; top: 0; z-index: 60; height: var(--appbar-h); }
        .appbar .tagline { color: #9aa1ab; font-size: 12px; margin-left: 2px; }

        .chips { display: none; }

        .work { display: grid; grid-template-columns: 240px minmax(0,1fr) 386px; align-items: start; }

        .spine { position: sticky; top: var(--appbar-h); height: calc(100vh - var(--appbar-h)); overflow-y: auto; border-right: 1px solid var(--gray-200); background: #fcfcfd; padding: 16px 16px 28px; }
        .sum-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 11px; padding: 13px 14px; margin-bottom: 6px; }
        .sum-card .pf { font-size: 14px; font-weight: 800; letter-spacing: -0.01em; }
        .sum-card .meta { font-size: 11.5px; color: var(--gray-500); margin-top: 3px; line-height: 1.45; }
        .sum-card .lead { font-size: 11.5px; color: var(--gray-500); line-height: 1.5; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--gray-150); }

        .steps { position: relative; padding-left: 4px; margin-top: 8px; }
        .steps::before { content: ""; position: absolute; left: 15px; top: 14px; bottom: 16px; width: 1.5px; background: var(--gray-200); }
        .grpname { font-size: 10px; font-weight: 800; letter-spacing: 0.07em; text-transform: uppercase; color: var(--gray-400); margin: 17px 0 5px 38px; }
        .stepgrp:first-child .grpname { margin-top: 4px; }
        .step { display: flex; align-items: center; gap: 13px; padding: 7px 10px 7px 0; cursor: pointer; position: relative; border-radius: 9px; transition: background .12s; }
        .step:hover { background: var(--gray-100); }
        .step.current { background: var(--blue-50); }
        .step .dot { width: 16px; height: 16px; border-radius: 50%; background: #fcfcfd; border: 2px solid var(--gray-300); flex: none; z-index: 1; display: flex; align-items: center; justify-content: center; margin-left: 7px; transition: border-color .2s, background .2s; }
        .step .dot svg { width: 8px; height: 8px; color: #fff; opacity: 0; }
        .step.done .dot { background: var(--green-600); border-color: var(--green-600); }
        .step.done .dot svg { opacity: 1; }
        .step.current .dot { border-color: var(--blue-600); background: #fff; }
        .step.current .dot::after { content: ""; width: 6px; height: 6px; border-radius: 50%; background: var(--blue-600); position: absolute; }
        .step.current.done .dot::after { display: none; }
        .step .stbody { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
        .step .stname { font-size: 11px; font-weight: 600; color: var(--gray-400); line-height: 1.25; }
        .step.current .stname { color: var(--blue-700); }
        .step .stval { font-size: 13px; font-weight: 700; color: var(--gray-800); letter-spacing: -0.01em; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .step.current .stval { color: var(--gray-900); }
        .step .ping { position: absolute; left: 20px; top: 5px; width: 8px; height: 8px; border-radius: 50%; background: var(--amber-700); border: 2px solid #fcfcfd; opacity: 0; z-index: 2; }
        .step.current .ping { border-color: var(--blue-50); }
        .step.changed .ping { opacity: 1; }

        .canvas { min-width: 0; }
        .m-sum { display: none; }
        .csec { background: #fff; border-bottom: 1px solid var(--gray-200); padding: 28px 34px 20px; scroll-margin-top: calc(var(--appbar-h) + 12px); }
        .csec .kicker { font-size: 11px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: var(--gray-400); }
        .csec .kicker .num { color: var(--blue-600); }
        .csec .htop { display: flex; align-items: flex-start; gap: 12px; margin-top: 6px; }
        .csec h2 { font-size: 21px; font-weight: 800; letter-spacing: -0.02em; margin: 0; flex: 1; }
        .csec .curval { display: none; font-size: 11px; font-weight: 700; color: var(--gray-600); background: var(--gray-100); border-radius: 999px; padding: 4px 10px; white-space: nowrap; flex: none; margin-top: 2px; }
        .csec .sub { font-size: 13px; color: var(--gray-500); margin-top: 5px; line-height: 1.55; max-width: 76ch; }
        .csec .bd { margin-top: 16px; }
        .csec[hidden] { display: none; }

        .tether { display: none; align-items: center; gap: 10px; max-width: 780px; margin-top: 16px; background: var(--amber-100); border: 1px solid #f3d27e; border-radius: 11px; padding: 11px 14px; cursor: pointer; }
        .tether.show { display: flex; }
        .tether .ic { width: 30px; height: 30px; border-radius: 8px; background: #fff; display: flex; align-items: center; justify-content: center; flex: none; color: var(--amber-700); }
        .tether .ic svg { width: 16px; height: 16px; }
        .tether .tx { flex: 1; min-width: 0; }
        .tether .tt { font-size: 13px; font-weight: 700; color: #7c4a09; }
        .tether .td { font-size: 12px; color: var(--amber-700); margin-top: 1px; line-height: 1.4; }
        .tether .go { color: #7c4a09; flex: none; display: flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .tether .go svg { width: 14px; height: 14px; }

        .rcardgrid { display: grid; gap: 9px; max-width: 780px; }
        .rcardgrid.two { grid-template-columns: 1fr 1fr; }
        .rcard { border: 1.5px solid var(--gray-200); border-radius: 12px; padding: 13px 15px; cursor: pointer; display: flex; gap: 12px; align-items: flex-start; background: #fff; transition: border-color .12s, box-shadow .12s; }
        .rcard:hover { border-color: var(--gray-300); }
        .rcard.sel { border-color: var(--blue-600); box-shadow: var(--ring); }
        .rcard.disabled { opacity: 0.5; cursor: not-allowed; }
        .rcard .rdot { flex: none; width: 18px; height: 18px; border-radius: 50%; border: 1.5px solid var(--gray-300); margin-top: 1px; position: relative; background: #fff; }
        .rcard.sel .rdot { border-color: var(--blue-600); }
        .rcard.sel .rdot::after { content: ""; position: absolute; inset: 3.5px; border-radius: 50%; background: var(--blue-600); }
        .rcard .rt { font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
        .rcard .rd { font-size: 12.5px; color: var(--gray-500); margin-top: 3px; line-height: 1.5; }
        .rcard code { font-family: var(--mono); font-size: 11px; }
        .rcard .affecthint { font-size: 11px; color: var(--gray-400); margin-top: 7px; display: flex; align-items: center; gap: 5px; font-weight: 600; }
        .rcard .affecthint svg { width: 12px; height: 12px; }
        .rcard .big-ic { width: 34px; height: 34px; border-radius: 9px; background: var(--gray-100); color: var(--gray-500); display: flex; align-items: center; justify-content: center; flex: none; }
        .rcard.sel .big-ic { background: var(--blue-50); color: var(--blue-600); }

        .colpair { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; max-width: 780px; }
        .col-lbl { font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--gray-500); margin-bottom: 10px; }
        .col-lbl.second { margin-top: 0; }
        .dep-inline { font-size: 11px; color: var(--gray-400); font-weight: 600; text-transform: none; letter-spacing: 0; }
        .infonote { display: flex; gap: 10px; max-width: 780px; margin-top: 16px; background: var(--blue-50); border: 1px solid var(--blue-100); border-radius: 9px; padding: 12px 14px; }
        .infonote .ic { width: 16px; height: 16px; color: var(--blue-600); flex: none; margin-top: 1px; }
        .infonote p { margin: 0; font-size: 12.5px; color: var(--gray-600); line-height: 1.55; }
        .infonote code { font-family: var(--mono); font-size: 11.5px; background: #fff; padding: 1px 5px; border-radius: 4px; color: var(--blue-700); }

        .modtools { display: flex; gap: 9px; align-items: center; margin: 2px 0 14px; }
        .modtools .search { flex: 1; max-width: 300px; }
        .filterchips { display: flex; gap: 6px; overflow-x: auto; scrollbar-width: none; }
        .filterchips::-webkit-scrollbar { display: none; }
        .fchip { font-size: 12px; font-weight: 600; padding: 6px 12px; border-radius: 7px; border: 1px solid var(--gray-300); background: #fff; color: var(--gray-600); cursor: pointer; white-space: nowrap; flex: none; user-select: none; }
        .fchip.on { background: var(--gray-900); color: #fff; border-color: var(--gray-900); }
        .catblock { margin-bottom: 18px; }
        .cathead { display: flex; align-items: center; gap: 10px; margin-bottom: 7px; }
        .cathead .ct-name { font-size: 12px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--gray-600); }
        .cathead .ct-line { flex: 1; height: 1px; background: var(--gray-200); }
        .cathead .ct-count { font-size: 11px; color: var(--gray-400); font-weight: 600; white-space: nowrap; }
        .cathead .ct-count b { color: var(--gray-700); }
        .modgrid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; max-width: 820px; }
        .modcard { display: flex; border-radius: 9px; border: 1px solid transparent; transition: background .3s, border-color .3s; }
        .modcard > .chk { padding: 8px 10px; flex: 1; align-items: flex-start; }
        .modcard > .chk:not(.disabled) { cursor: pointer; }
        .modcard:hover { background: #fff; border-color: var(--gray-200); }
        .modcard:active { background: var(--gray-50); }
        .modcard.flash { background: var(--amber-100); border-color: #f3d27e; transition: background .5s ease, border-color .5s ease; }
        .mtext { min-width: 0; }
        .origin { font-size: 9.5px; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase; padding: 1px 5px; border-radius: 4px; white-space: nowrap; }
        .origin.req { background: var(--gray-100); color: var(--gray-500); }
        .origin.profile { background: var(--blue-50); color: var(--blue-700); }
        .origin.you { background: var(--green-50); color: var(--green-600); }

        .langgrid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; max-width: 780px; }
        .langgrid.addon-grid { grid-template-columns: 1fr 1fr; }
        .langcard { display: flex; align-items: center; gap: 10px; border: 1px solid var(--gray-200); border-radius: 10px; padding: 10px 12px; cursor: pointer; background: #fff; min-width: 0; }
        .langcard.on { border-color: var(--green-600); background: var(--green-50); }
        .langcard.forced { cursor: not-allowed; }
        .langcard > div { min-width: 0; }
        .langcard .lcode { width: 28px; height: 28px; border-radius: 7px; background: var(--gray-100); color: var(--gray-600); flex: none; display: flex; align-items: center; justify-content: center; font-family: var(--mono); font-size: 12px; font-weight: 600; }
        .langcard.on .lcode { background: #fff; color: var(--green-600); box-shadow: inset 0 0 0 1px rgba(22,163,74,0.3); }
        .langcard .ln { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .langcard .lc { font-size: 11px; color: var(--gray-400); font-family: var(--mono); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .langcard .addon-desc { font-family: var(--sans); color: var(--gray-500); font-size: 11px; margin-top: 2px; }
        .langcard .grow { flex: 1; }
        .langcard .tick { width: 18px; height: 18px; border-radius: 5px; border: 1.5px solid var(--gray-300); flex: none; position: relative; background: #fff; }
        .langcard.on .tick { background: var(--green-600); border-color: var(--green-600); }
        .langcard.on .tick::after { content: ""; position: absolute; left: 5.5px; top: 2px; width: 4px; height: 8px; border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(42deg); }

        .layerlist { display: flex; flex-direction: column; gap: 8px; max-width: 780px; }
        .layerrow { display: flex; align-items: center; gap: 13px; border: 1px solid var(--gray-200); border-radius: 11px; padding: 12px 15px; background: #fff; cursor: pointer; }
        .layerrow.forced { cursor: default; }
        .layerrow .lt { font-size: 13.5px; font-weight: 700; display: flex; align-items: center; gap: 7px; }
        .layerrow .ld { font-size: 12px; color: var(--gray-500); margin-top: 2px; line-height: 1.45; }
        .layerrow .grow { flex: 1; }
        .switch { width: 40px; height: 23px; border-radius: 12px; background: var(--gray-300); position: relative; transition: background .15s; flex: none; cursor: pointer; }
        .switch.on { background: var(--green-600); }
        .switch.switch-locked { opacity: 0.6; cursor: default; }
        .switch::after { content: ""; position: absolute; width: 19px; height: 19px; border-radius: 50%; background: #fff; top: 2px; left: 2px; transition: left .15s; box-shadow: var(--shadow-sm); }
        .switch.on::after { left: 19px; }

        /* ---- dock (desktop right column) ---- */
        .dock { position: sticky; top: var(--appbar-h); height: calc(100vh - var(--appbar-h)); border-left: 1px solid var(--gray-200); background: #fff; display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
        .dock-grab { display: none; }
        .dock-sum { padding: 16px 16px 14px; border-bottom: 1px solid var(--gray-200); }
        .dock-sum .ttl { font-size: 14px; font-weight: 800; letter-spacing: -0.01em; }
        .dock-sum .dmeta { font-size: 11.5px; color: var(--gray-500); margin-top: 2px; line-height: 1.5; }
        .dock-sum .big { display: flex; align-items: baseline; gap: 9px; margin-top: 11px; }
        .dock-sum .big .num { font-size: 27px; font-weight: 800; letter-spacing: -0.03em; }
        .dock-sum .big .unit { font-size: 12px; color: var(--gray-500); font-weight: 600; }
        .dock-sum .big .delta { font-size: 12px; font-weight: 800; padding: 2px 8px; border-radius: 6px; opacity: 0; transition: opacity .2s; }
        .dock-sum .big .delta.show { opacity: 1; }
        .dock-sum .big .delta.up { background: var(--green-50); color: var(--green-600); }
        .dock-sum .big .delta.down { background: var(--amber-100); color: var(--amber-700); }
        .dock-sum .spin { display: none; width: 13px; height: 13px; border: 2px solid var(--gray-300); border-top-color: var(--blue-600); border-radius: 50%; animation: dockspin .6s linear infinite; align-self: center; }
        .dock-sum.busy .spin { display: inline-block; }
        @keyframes dockspin { to { transform: rotate(360deg); } }
        .dock.busy .dock-body { opacity: 0.55; transition: opacity .15s; }

        .dock-bougie { margin: 12px 14px 2px; background: var(--indigo-50); border: 1px solid var(--indigo-100); border-radius: 11px; padding: 12px 13px; }
        .dock-bougie .h { font-size: 10.5px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: var(--indigo-700); display: flex; align-items: center; gap: 6px; }
        .dock-bougie .h svg { width: 13px; height: 13px; }
        .dock-bougie p { font-size: 12px; color: var(--gray-600); line-height: 1.55; margin: 8px 0 11px; }
        .dock-bougie p b { color: var(--gray-800); }
        .dock-bougie .save-btn { width: 100%; background: var(--blue-600); color: #fff; border: 0; border-radius: 8px; padding: 10px 12px; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; }
        .dock-bougie .save-btn:hover { background: var(--blue-700); }
        .dock-bougie .save-btn:disabled { opacity: 0.7; cursor: default; }
        .dock-bougie .save-btn svg { width: 14px; height: 14px; }
        .dock-bougie .alt { font-size: 12px; color: var(--indigo-700); font-weight: 600; margin: 10px 0 0; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .dock-bougie .alt:hover { color: var(--indigo-600); }
        .dock-bougie .bougie-note { display: block; margin-top: 8px; font-size: 11px; color: var(--gray-500); }
        .dock-bougie .bougie-note code { font-family: var(--mono); font-size: 10.5px; color: var(--indigo-700); word-break: break-all; }
        .dock-bougie .bg-result .cmd-row { margin-top: 4px; }

        .changelog { display: none; padding: 0 16px; }
        .changelog.show { display: block; padding: 9px 16px; border-bottom: 1px solid var(--gray-200); }
        .changelog .cl-item { display: flex; align-items: center; gap: 9px; }
        .changelog .cl-ic { width: 6px; height: 6px; border-radius: 50%; background: var(--amber-700); flex: none; }
        .changelog .cl-tx { flex: 1; min-width: 0; font-size: 12px; color: var(--gray-500); line-height: 1.45; }
        .changelog .cl-tx b { color: var(--gray-800); font-weight: 700; }
        .changelog .cl-link { font-size: 12px; font-weight: 600; color: var(--blue-600); cursor: pointer; white-space: nowrap; flex: none; }

        .dock-tabs { display: flex; gap: 3px; padding: 11px 13px 0; overflow-x: auto; scrollbar-width: none; }
        .dock-tabs::-webkit-scrollbar { display: none; }
        .dock-tab { font-size: 12px; font-weight: 600; color: var(--gray-500); padding: 7px 12px; border-radius: 8px; cursor: pointer; white-space: nowrap; flex: none; user-select: none; }
        .dock-tab:hover { background: var(--gray-100); }
        .dock-tab.active { color: #fff; background: var(--gray-900); }
        .dock-tab .n { opacity: 0.6; margin-left: 5px; }
        .dock-tab.hyva-tab { color: var(--indigo-700); }
        .dock-tab.hyva-tab:hover { background: var(--indigo-50); }
        .dock-tab.hyva-tab.active { color: #fff; background: var(--indigo-600); }
        .dock-body { flex: 1; min-height: 0; overflow-y: auto; padding: 11px 13px 13px; }
        [data-opane] { display: none; }
        [data-opane].show { display: block; }
        .dock-body .opane-composer { height: 100%; }
        .dock-foot { border-top: 1px solid var(--gray-200); padding: 11px 13px; display: flex; gap: 9px; align-items: center; }
        .dock-foot .sp { flex: 1; }

        /* composer.json (highlight.js + diff-flash) */
        pre.composer { padding: 0; border-radius: 10px; overflow: auto; font-size: 12.5px; line-height: 1.55; margin: 0; position: relative; height: 100%; }
        pre.composer code.hljs { display: block; padding: 16px 18px; background: var(--code-bg); border-radius: 10px; position: relative; z-index: 1; min-height: 100%; }
        .diff-overlay { position: absolute; left: 0; right: 0; top: 16px; pointer-events: none; z-index: 2; }
        .diff-overlay .strip { position: absolute; left: 0; right: 0; background: rgba(250, 204, 21, 0.32); border-left: 2px solid rgba(250, 204, 21, 0.85); animation: diffFade 1.8s ease-out forwards; mix-blend-mode: screen; }
        @keyframes diffFade { 0% { opacity: 1; } 70% { opacity: 0.6; } 100% { opacity: 0; } }

        /* install tree */
        .tree-filter { margin-bottom: 8px; }
        .install-tree-types { font-size: 11px; color: var(--gray-500); margin-bottom: 6px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .install-tree-types .tree-actions { margin-left: auto; }
        .install-tree-types a { font-size: 11px; color: var(--blue-600); }
        .install-tree-root { font-family: var(--mono); font-size: 12px; line-height: 1.6; color: var(--gray-700); }
        .install-tree-root details > details, .install-tree-root details > .leaf { margin-left: 14px; }
        .install-tree-root summary { cursor: pointer; list-style: none; padding: 1px 0; }
        .install-tree-root summary::-webkit-details-marker { display: none; }
        .install-tree-root summary::before { content: '▸'; display: inline-block; width: 10px; color: #888; transition: transform .1s; }
        .install-tree-root details[open] > summary::before { content: '▾'; }
        .install-tree-root .leaf::before { content: '·'; color: var(--gray-300); display: inline-block; width: 10px; }
        .install-tree-root .pkg-name { color: var(--blue-700); }
        .install-tree-root .pkg-name.shared { color: var(--gray-600); font-style: italic; }
        .install-tree-root .pkg-version { color: var(--gray-400); margin-left: 6px; }
        .install-tree-root .pkg-type { color: var(--gray-400); font-size: 10px; margin-left: 6px; }
        .install-tree-root .hidden { display: none; }
        .warn { font-size: 12px; color: #a15c00; }
        .tree-empty { font-size: 12px; color: var(--gray-500); }

        /* shared command block (bougie + hyvä) */
        pre.cmd { background: var(--code-bg); color: #e6e6e6; padding: 10px 12px; margin: 0; border-radius: 8px; font-size: 12px; line-height: 1.45; overflow-x: auto; white-space: pre; }
        pre.cmd code { font-family: var(--mono); }
        .cmd-row { position: relative; }
        .cmd-row pre.cmd { padding-right: 40px; }
        .cmd-row .cmd-copy { position: absolute; top: 6px; right: 6px; display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; padding: 0; background: rgba(255,255,255,0.08); color: #e6e6e6; border: 1px solid rgba(255,255,255,0.15); border-radius: 3px; font-size: 11px; cursor: pointer; }
        .cmd-row .cmd-copy svg { width: 14px; height: 14px; fill: currentColor; }
        .cmd-row .cmd-copy:hover { background: rgba(255,255,255,0.15); }
        .cmd-row .cmd-copy:disabled { cursor: default; opacity: 0.85; }
        .cmd-row .cmd-copy.copied { width: auto; padding: 0 8px; }

        /* hyvä pane (design's hy-* classes) */
        .hyva-pane { font-size: 12.5px; color: var(--gray-700); }
        .hy-intro { margin: 2px 0 13px; font-size: 12px; color: var(--gray-600); line-height: 1.6; }
        .hy-intro a { color: var(--blue-600); font-weight: 600; text-decoration: none; }
        .hy-intro code { font-family: var(--mono); font-size: 11px; background: var(--gray-100); padding: 1px 4px; border-radius: 4px; }
        .hy-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; margin-bottom: 15px; }
        .hy-fields label { font-size: 10px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: var(--gray-500); display: flex; flex-direction: column; gap: 5px; }
        .hy-fields .input { font-family: var(--mono); font-size: 11.5px; padding: 7px 9px; }
        .hy-steps { margin: 0; padding: 0; list-style: none; counter-reset: hy; }
        .hy-steps li { position: relative; padding: 0 0 15px 27px; counter-increment: hy; }
        .hy-steps li::before { content: counter(hy); position: absolute; left: 0; top: -1px; width: 19px; height: 19px; border-radius: 50%; background: var(--indigo-50); color: var(--indigo-700); font-size: 10.5px; font-weight: 800; display: flex; align-items: center; justify-content: center; }
        .hy-st { font-size: 12.5px; font-weight: 700; color: var(--gray-900); margin-bottom: 6px; }
        .hy-note { font-size: 11px; color: var(--gray-500); line-height: 1.5; margin-top: 6px; }
        .hy-note code { font-family: var(--mono); font-size: 10.5px; background: var(--gray-100); padding: 1px 4px; border-radius: 4px; }

        /* mobile-only chrome (hidden on desktop) */
        .m-spacer, .m-bar, .m-toast, .sheet-scrim { display: none; }

        /* ============================================================
           MOBILE (≤ 1023px)
           ============================================================ */
        @media (max-width: 1023px) {
            :root { --chips-h: 45px; }
            .appbar .tagline { display: none; }
            .chips { display: flex; position: sticky; top: var(--appbar-h); z-index: 50; background: rgba(252,252,253,0.94); backdrop-filter: blur(10px); border-bottom: 1px solid var(--gray-200); gap: 7px; overflow-x: auto; padding: 9px 14px; scrollbar-width: none; }
            .chips::-webkit-scrollbar { display: none; }
            .chip { flex: none; display: flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 600; color: var(--gray-500); padding: 6px 12px 6px 10px; border-radius: 999px; border: 1px solid var(--gray-200); background: #fff; white-space: nowrap; cursor: pointer; position: relative; user-select: none; }
            .chip .cdot { position: relative; width: 14px; height: 14px; border-radius: 50%; border: 1.5px solid var(--gray-300); background: #fff; flex: none; display: flex; align-items: center; justify-content: center; }
            .chip .cdot svg { width: 7px; height: 7px; color: #fff; opacity: 0; }
            .chip.done .cdot { background: var(--green-600); border-color: var(--green-600); }
            .chip.done .cdot svg { opacity: 1; }
            .chip.current { background: var(--blue-50); border-color: var(--blue-100); color: var(--blue-700); }
            .chip.current .cdot { border-color: var(--blue-600); }
            .chip.current .cdot::after { content: ""; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 6px; height: 6px; border-radius: 50%; background: var(--blue-600); }
            .chip .badge-dot { position: absolute; top: 2px; right: 4px; width: 7px; height: 7px; border-radius: 50%; background: var(--amber-700); border: 1.5px solid #fff; opacity: 0; }
            .chip.changed .badge-dot { opacity: 1; }

            .work { display: block; }
            .spine { display: none; }
            .m-sum { display: block; background: #fff; border-bottom: 1px solid var(--gray-200); padding: 15px 16px 16px; }
            .m-sum .pf { font-size: 17px; font-weight: 800; letter-spacing: -0.02em; }
            .m-sum .mmeta { font-size: 12px; color: var(--gray-500); margin-top: 3px; line-height: 1.5; }
            .m-sum .lead { font-size: 12px; color: var(--gray-500); line-height: 1.55; margin-top: 9px; padding-top: 11px; border-top: 1px solid var(--gray-150); }

            .csec { border-bottom: 8px solid var(--gray-100); padding: 20px 16px 22px; scroll-margin-top: calc(var(--appbar-h) + var(--chips-h) + 6px); }
            .csec h2 { font-size: 18px; }
            .csec .curval { display: block; }
            .csec .sub { font-size: 12.5px; }
            .colpair { grid-template-columns: 1fr; gap: 0; }
            .col-lbl.second { margin-top: 16px; }
            .rcardgrid.two { grid-template-columns: 1fr; }
            .langgrid.addon-grid { grid-template-columns: 1fr; }
            .modgrid { grid-template-columns: 1fr; max-width: none; }
            .langgrid { grid-template-columns: 1fr 1fr; }
            .modtools { position: sticky; top: calc(var(--appbar-h) + var(--chips-h)); z-index: 25; background: rgba(255,255,255,0.96); backdrop-filter: blur(8px); margin: 0 -16px 12px; padding: 4px 16px 11px; border-bottom: 1px solid var(--gray-200); flex-direction: column; align-items: stretch; }
            .modtools .search { max-width: none; }

            .dock { position: fixed; left: 50%; bottom: 0; top: auto; transform: translate(-50%, 100%); width: 100%; max-width: 480px; height: auto; max-height: 86vh; border-left: 0; border-radius: 20px 20px 0 0; box-shadow: var(--shadow-lg); z-index: 71; transition: transform .28s cubic-bezier(.2,.7,.3,1); }
            .dock.open { transform: translate(-50%, 0); }
            .dock-grab { display: block; width: 38px; height: 4px; border-radius: 3px; background: var(--gray-300); margin: 9px auto 2px; flex: none; }
            .changelog, .changelog.show { display: none; }
            .dock-foot { padding-bottom: calc(11px + env(safe-area-inset-bottom)); }

            .m-spacer { display: block; height: 74px; background: var(--gray-100); }
            .m-bar { display: flex; position: sticky; bottom: 0; z-index: 42; background: rgba(255,255,255,0.97); backdrop-filter: blur(12px); border-top: 1px solid var(--gray-200); padding: 11px 14px calc(11px + env(safe-area-inset-bottom)); align-items: center; gap: 11px; }
            .m-bar .pk { line-height: 1.05; }
            .m-bar .pk .n { font-size: 19px; font-weight: 800; letter-spacing: -0.02em; }
            .m-bar .pk .n .delta { font-size: 11px; font-weight: 800; padding: 1px 6px; border-radius: 5px; margin-left: 6px; opacity: 0; }
            .m-bar .pk .n .delta.show { opacity: 1; }
            .m-bar .pk .n .delta.up { background: var(--green-50); color: var(--green-600); }
            .m-bar .pk .n .delta.down { background: var(--amber-100); color: var(--amber-700); }
            .m-bar .pk .l { font-size: 11px; color: var(--gray-500); font-weight: 600; }
            .m-bar .grow { flex: 1; }
            .m-bar .view-out { font-size: 13px; font-weight: 700; color: #fff; background: var(--gray-900); border: 0; border-radius: 9px; padding: 10px 15px; display: flex; align-items: center; gap: 7px; cursor: pointer; }
            .m-bar .view-out svg { width: 14px; height: 14px; }

            .sheet-scrim { display: block; position: fixed; inset: 0; background: rgba(16,24,40,0.4); z-index: 70; opacity: 0; pointer-events: none; transition: opacity .2s; }
            .sheet-scrim.show { opacity: 1; pointer-events: auto; }

            .m-toast { display: none; position: fixed; left: 50%; transform: translateX(-50%); bottom: 84px; z-index: 80; width: calc(100% - 32px); max-width: 420px; background: var(--gray-900); color: #fff; border-radius: 13px; padding: 12px 14px; box-shadow: var(--shadow-lg); align-items: center; gap: 11px; }
            .m-toast.show { display: flex; }
            .m-toast .ic { width: 8px; height: 8px; border-radius: 50%; background: #fbbf24; flex: none; }
            .m-toast .tx { flex: 1; font-size: 12.5px; line-height: 1.45; color: #e8eaee; }
            .m-toast .tx b { color: #fff; font-weight: 700; }
            .m-toast .act { font-size: 12.5px; font-weight: 700; color: #fff; background: rgba(255,255,255,0.14); border: 0; border-radius: 8px; padding: 7px 11px; cursor: pointer; flex: none; }
        }
        @media (max-width: 344px) {
            .langgrid { grid-template-columns: 1fr; }
            .hy-fields { grid-template-columns: 1fr; }
            .csec .htop { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

{{ $slot }}

<script>
    // Pure presentation helpers: highlight, diff-flash, copy, install-tree filter.
    // All form state + interaction lives in the client engine (resources/js).
    let lastJson = document.getElementById('composer-out')?.textContent || '';

    function diffLineIndices(oldStr, newStr) {
        const oldLines = oldStr.split('\n');
        const newLines = newStr.split('\n');
        const m = oldLines.length, n = newLines.length;
        if (m === 0 || n === 0) return new Set(newLines.map((_, i) => i));
        const normOld = oldLines.map(l => l.replace(/,(\s*)$/, '$1'));
        const normNew = newLines.map(l => l.replace(/,(\s*)$/, '$1'));
        const lcs = Array.from({length: m + 1}, () => new Uint16Array(n + 1));
        for (let i = 1; i <= m; i++) {
            for (let j = 1; j <= n; j++) {
                lcs[i][j] = normOld[i-1] === normNew[j-1]
                    ? lcs[i-1][j-1] + 1
                    : Math.max(lcs[i-1][j], lcs[i][j-1]);
            }
        }
        const changed = new Set();
        let i = m, j = n;
        while (i > 0 && j > 0) {
            if (normOld[i-1] === normNew[j-1]) { i--; j--; }
            else if (lcs[i-1][j] >= lcs[i][j-1]) { i--; }
            else { changed.add(j - 1); j--; }
        }
        while (j > 0) { changed.add(j - 1); j--; }
        return changed;
    }
    function groupRanges(indices) {
        const sorted = [...indices].sort((a, b) => a - b);
        const ranges = [];
        for (const i of sorted) {
            const last = ranges[ranges.length - 1];
            if (last && i === last[1] + 1) last[1] = i;
            else ranges.push([i, i]);
        }
        return ranges;
    }
    function flashChangedLines(preEl, codeEl, changedRanges) {
        preEl.querySelectorAll('.diff-overlay').forEach(o => o.remove());
        if (changedRanges.length === 0) return;
        const lineHeight = parseFloat(getComputedStyle(codeEl).lineHeight);
        const overlay = document.createElement('div');
        overlay.className = 'diff-overlay';
        for (const [start, end] of changedRanges) {
            const strip = document.createElement('div');
            strip.className = 'strip';
            strip.style.top = (start * lineHeight) + 'px';
            strip.style.height = ((end - start + 1) * lineHeight) + 'px';
            overlay.appendChild(strip);
        }
        preEl.appendChild(overlay);
        setTimeout(() => overlay.remove(), 2000);
        const firstStart = changedRanges[0][0];
        const padTop = parseFloat(getComputedStyle(codeEl).paddingTop) || 0;
        const targetTop = firstStart * lineHeight + padTop;
        const visibleTop = preEl.scrollTop;
        const visibleBottom = visibleTop + preEl.clientHeight;
        if (targetTop < visibleTop || targetTop > visibleBottom - lineHeight * 2) {
            preEl.scrollTo({top: Math.max(0, targetTop - lineHeight * 2), behavior: 'smooth'});
        }
    }
    window.paintComposer = function (json) {
        const els = document.querySelectorAll('code.composer-code');
        if (!els.length) return;
        const changed = diffLineIndices(lastJson, json);
        lastJson = json;
        els.forEach(el => {
            el.textContent = json;
            delete el.dataset.highlighted;
            if (window.hljs) hljs.highlightElement(el);
        });
        const desktop = document.getElementById('composer-out');
        if (desktop) flashChangedLines(desktop.parentElement, desktop, groupRanges(changed));
    };
    window.copyComposer = function () {
        const el = document.querySelector('code.composer-code');
        if (el) navigator.clipboard.writeText(el.textContent);
    };
    window.copyCmd = function (btn) {
        const pre = btn.closest('.cmd-row')?.querySelector('pre.cmd code');
        if (!pre) return;
        navigator.clipboard.writeText(pre.textContent);
        const original = btn.innerHTML;
        btn.textContent = 'Copied';
        btn.classList.add('copied');
        btn.disabled = true;
        setTimeout(() => { btn.innerHTML = original; btn.classList.remove('copied'); btn.disabled = false; }, 1200);
    };
    window.filterInstallTree = function (q) {
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
    };
    window.installTreeToggleAll = function (open) {
        document.querySelectorAll('#install-tree-root details').forEach(d => d.open = open);
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('code.composer-code').forEach(el => { if (window.hljs) hljs.highlightElement(el); });
    });
</script>
</body>
</html>
