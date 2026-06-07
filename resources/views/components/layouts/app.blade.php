<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>mageos-maker</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.10.0/build/styles/atom-one-dark.min.css">
    <script src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.10.0/build/highlight.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.10.0/build/languages/json.min.js"></script>
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
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body { margin: 0; font-family: var(--sans); color: var(--gray-900); background: var(--gray-100);
            -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility; }
        [x-cloak] { display: none !important; }

        /* ---- App bar ---- */
        .appbar { height: 44px; background: #0b0c0e; color: #fff; display: flex; align-items: center; gap: 14px; padding: 0 18px; font-size: 13px; }
        .appbar .brand { font-weight: 600; letter-spacing: -0.01em; display: flex; align-items: center; gap: 8px; }
        .appbar .brand .glyph { width: 18px; height: 18px; border-radius: 5px; background: linear-gradient(135deg, #ff7a45, #f6431b); display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; color: #fff; }
        .appbar .sp { flex: 1; }
        .appbar .ghost { color: #9aa1ab; font-size: 12.5px; text-decoration: none; }
        .appbar .ghost:hover { color: #fff; }
        .appbar-saved { font-size: 11.5px; color: #9aa1ab; }
        .appbar-saved code { font-family: var(--mono); color: #cfd3da; }

        /* ---- Shared atoms ---- */
        .section-label { font-size: 11px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: var(--gray-500); }
        .input { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: 8px 12px; font-size: 13px; font-family: var(--sans); color: var(--gray-900); background: #fff; }
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

        .countchip { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; background: var(--gray-100); color: var(--gray-600); padding: 2px 8px; border-radius: 999px; }
        .countchip b { color: var(--gray-900); }

        .scrollbox { overflow: auto; }
        .scrollbox::-webkit-scrollbar { width: 9px; height: 9px; }
        .scrollbox::-webkit-scrollbar-thumb { background: var(--gray-300); border-radius: 6px; }
        .scrollbox::-webkit-scrollbar-track { background: transparent; }

        /* ============================================================
           Tabbed workspace shell
           ============================================================ */
        .shell { display: flex; flex-direction: column; height: 100vh; min-width: 1200px; }
        .work { flex: 1; min-height: 0; display: grid; grid-template-columns: 258px minmax(0,1fr) 452px; }

        /* ---- Left rail ---- */
        .rail { border-right: 1px solid var(--gray-200); background: #fcfcfd; overflow-y: auto; padding: 16px 12px 28px; }
        .summary { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 13px 14px; margin-bottom: 16px; }
        .summary .pf { font-size: 14px; font-weight: 700; letter-spacing: -0.01em; }
        .summary .meta { font-size: 11.5px; color: var(--gray-500); margin-top: 3px; line-height: 1.4; }
        .summary .row2 { display: flex; gap: 6px; margin-top: 11px; flex-wrap: wrap; }
        .navitem { display: flex; align-items: center; gap: 10px; padding: 9px 11px; border-radius: 8px; cursor: pointer; color: var(--gray-700); border: 1px solid transparent; user-select: none; }
        .navitem:hover { background: var(--gray-100); }
        .navitem.active { background: #fff; border-color: var(--gray-200); box-shadow: var(--shadow-sm); }
        .navitem.active .nm { color: var(--gray-900); }
        .navitem .ic { width: 16px; height: 16px; color: var(--gray-400); flex: none; }
        .navitem.active .ic { color: var(--blue-600); }
        .navitem .nm { font-size: 13px; font-weight: 600; flex: 1; }
        .navitem .ct { font-size: 11px; font-weight: 600; color: var(--gray-400); }
        .navitem.active .ct { color: var(--blue-600); }
        .grouplbl { padding: 15px 11px 6px; }

        /* ---- Center ---- */
        .center { overflow-y: auto; position: relative; }
        .sec-head { position: sticky; top: 0; z-index: 5; background: rgba(243,244,246,0.86); backdrop-filter: blur(8px); border-bottom: 1px solid var(--gray-200); padding: 18px 28px 15px; }
        .sec-head h1 { font-size: 19px; font-weight: 700; margin: 0; letter-spacing: -0.01em; }
        .sec-head .sub { font-size: 12.5px; color: var(--gray-500); margin-top: 3px; line-height: 1.5; max-width: 74ch; }
        .sec-body { padding: 20px 28px 64px; }

        .snap-banner { display: flex; align-items: flex-start; gap: 10px; margin: 14px 28px 0; background: #fff8e0; border: 1px solid #e6c34c; border-radius: 9px; padding: 10px 14px; font-size: 12.5px; color: #7a5a00; }
        .snap-banner span { flex: 1; line-height: 1.5; }
        .snap-banner button { background: none; border: 0; cursor: pointer; color: #7a5a00; font-size: 16px; line-height: 1; padding: 0; }

        .toolbar { display: flex; gap: 10px; align-items: center; margin-top: 14px; }
        .toolbar .search { flex: 1; max-width: 340px; }
        .filterchips { display: flex; gap: 6px; }
        .fchip { font-size: 12px; font-weight: 600; padding: 6px 11px; border-radius: 7px; border: 1px solid var(--gray-300); background: #fff; color: var(--gray-600); cursor: pointer; user-select: none; }
        .fchip.on { background: var(--gray-900); color: #fff; border-color: var(--gray-900); }
        .empty-note { font-size: 12.5px; color: var(--gray-400); margin: 4px 0 0; }

        /* radio cards */
        .rcardgrid { display: grid; gap: 10px; max-width: 760px; }
        .rcardgrid.two { grid-template-columns: 1fr 1fr; }
        .rcard { border: 1.5px solid var(--gray-200); border-radius: 11px; padding: 14px 15px; cursor: pointer; display: flex; gap: 12px; align-items: flex-start; background: #fff; transition: border-color .12s, box-shadow .12s; }
        .rcard:hover { border-color: var(--gray-300); }
        .rcard.sel { border-color: var(--blue-600); box-shadow: var(--ring); }
        .rcard.disabled { opacity: 0.5; cursor: not-allowed; }
        .rcard .rdot { flex: none; width: 18px; height: 18px; border-radius: 50%; border: 1.5px solid var(--gray-300); margin-top: 1px; position: relative; background: #fff; }
        .rcard.sel .rdot { border-color: var(--blue-600); }
        .rcard.sel .rdot::after { content: ""; position: absolute; inset: 3.5px; border-radius: 50%; background: var(--blue-600); }
        .rcard .rt { font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .rcard .rd { font-size: 12.5px; color: var(--gray-500); margin-top: 3px; line-height: 1.5; }
        .rcard .big-ic { width: 34px; height: 34px; border-radius: 9px; background: var(--gray-100); color: var(--gray-500); display: flex; align-items: center; justify-content: center; flex: none; }
        .rcard.sel .big-ic { background: var(--blue-50); color: var(--blue-600); }
        .rcard code { font-family: var(--mono); font-size: 11px; }

        .colpair { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; max-width: 760px; }
        .col-lbl { font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--gray-500); margin-bottom: 10px; }

        .infonote { display: flex; gap: 10px; max-width: 760px; margin-top: 16px; background: var(--blue-50); border: 1px solid var(--blue-100); border-radius: 9px; padding: 12px 14px; }
        .infonote .ic { width: 16px; height: 16px; color: var(--blue-600); flex: none; margin-top: 1px; }
        .infonote p { margin: 0; font-size: 12.5px; color: var(--gray-600); line-height: 1.55; }
        .infonote code { font-family: var(--mono); font-size: 11.5px; background: #fff; padding: 1px 5px; border-radius: 4px; color: var(--blue-700); }

        /* modules */
        .catblock { margin-bottom: 22px; }
        .cathead { display: flex; align-items: center; gap: 10px; margin-bottom: 9px; cursor: pointer; user-select: none; }
        .cathead .ct-name { font-size: 12px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--gray-600); }
        .cathead .ct-line { flex: 1; height: 1px; background: var(--gray-200); }
        .cathead .ct-count { font-size: 11px; color: var(--gray-400); font-weight: 600; white-space: nowrap; }
        .cathead .ct-count b { color: var(--gray-700); }
        .cathead .ct-fold { width: 14px; height: 14px; color: var(--gray-400); transition: transform .15s; }
        .catblock.folded .ct-fold { transform: rotate(-90deg); }
        .catblock.folded .modgrid { display: none; }
        .modgrid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 26px; }
        .modcard { display: flex; border-radius: 8px; border: 1px solid transparent; }
        .modcard > .chk { padding: 9px 11px; flex: 1; align-items: flex-start; }
        .modcard:hover { background: #fff; border-color: var(--gray-200); }
        .modcard .chk:not(.disabled) { cursor: pointer; }

        /* checkbox rows */
        .chk { display: flex; gap: 10px; align-items: flex-start; }
        .chk .box { flex: none; width: 16px; height: 16px; border-radius: 4px; border: 1.5px solid var(--gray-300); background: #fff; margin-top: 1px; position: relative; }
        .chk.on .box { background: var(--green-600); border-color: var(--green-600); }
        .chk.on .box::after { content: ""; position: absolute; left: 4.5px; top: 1.5px; width: 4px; height: 8px; border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(42deg); }
        .chk .label { font-size: 13px; font-weight: 600; color: var(--gray-900); display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
        .chk .desc { font-size: 12px; color: var(--gray-500); margin-top: 1px; line-height: 1.4; }
        .chk.disabled { opacity: 0.55; cursor: not-allowed; }
        .chk.disabled .label { color: var(--gray-500); }
        .mtext { min-width: 0; }
        .subopts { display: block; margin: 6px 0 2px 2px; padding-left: 12px; border-left: 2px solid var(--gray-200); }
        .chk.mini { padding: 4px 0; cursor: pointer; }
        .chk.mini .label { font-size: 12.5px; font-weight: 500; }
        /* radio dot (profile-group option variants) */
        .chk .dot { flex: none; width: 16px; height: 16px; border-radius: 50%; border: 1.5px solid var(--gray-300); margin-top: 1px; position: relative; background: #fff; }
        .chk .dot.on { border-color: var(--blue-600); }
        .chk .dot.on::after { content: ""; position: absolute; inset: 3px; border-radius: 50%; background: var(--blue-600); }
        .chk.is-disabled { opacity: 0.5; cursor: not-allowed; }

        /* add-ons */
        .addon-grid { grid-template-columns: 1fr 1fr; }
        .addon-card { cursor: pointer; }
        .addon-card.forced { cursor: not-allowed; }
        .addon-desc { font-family: var(--sans) !important; color: var(--gray-500) !important; font-size: 12px !important; margin-top: 2px; }

        /* languages */
        .langgrid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; max-width: 760px; }
        .langcard { display: flex; align-items: center; gap: 11px; border: 1px solid var(--gray-200); border-radius: 9px; padding: 11px 13px; cursor: pointer; background: #fff; }
        .langcard:hover { border-color: var(--gray-300); }
        .langcard.on { border-color: var(--green-600); background: var(--green-50); }
        .langcard .lcode { width: 30px; height: 30px; border-radius: 7px; background: var(--gray-100); color: var(--gray-600); flex: none; display: flex; align-items: center; justify-content: center; font-family: var(--mono); font-size: 12px; font-weight: 600; letter-spacing: -0.01em; }
        .langcard.on .lcode { background: #fff; color: var(--green-600); box-shadow: inset 0 0 0 1px rgba(22,163,74,0.3); }
        .langcard .ln { font-size: 13px; font-weight: 600; }
        .langcard .lc { font-size: 11px; color: var(--gray-400); font-family: var(--mono); margin-top: 1px; }
        .langcard .grow { flex: 1; }
        .langcard .tick { width: 18px; height: 18px; border-radius: 5px; border: 1.5px solid var(--gray-300); flex: none; position: relative; background: #fff; }
        .langcard.on .tick { background: var(--green-600); border-color: var(--green-600); }
        .langcard.on .tick::after { content: ""; position: absolute; left: 5.5px; top: 2px; width: 4px; height: 8px; border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(42deg); }

        /* layers */
        .layerlist { display: flex; flex-direction: column; gap: 8px; max-width: 760px; }
        .layerrow { display: flex; align-items: center; gap: 14px; border: 1px solid var(--gray-200); border-radius: 10px; padding: 13px 15px; background: #fff; cursor: pointer; }
        .layerrow.forced { cursor: default; }
        .layerrow .lt { font-size: 13.5px; font-weight: 700; display: flex; align-items: center; gap: 7px; }
        .layerrow .ld { font-size: 12px; color: var(--gray-500); margin-top: 2px; line-height: 1.45; }
        .layerrow .grow { flex: 1; }
        .switch { width: 40px; height: 23px; border-radius: 12px; background: var(--gray-300); position: relative; transition: background .15s; flex: none; cursor: pointer; }
        .switch.on { background: var(--green-600); }
        .switch.switch-locked { opacity: 0.6; cursor: default; }
        .switch::after { content: ""; position: absolute; width: 19px; height: 19px; border-radius: 50%; background: #fff; top: 2px; left: 2px; transition: left .15s; box-shadow: var(--shadow-sm); }
        .switch.on::after { left: 19px; }

        /* ---- Right output ---- */
        .out { border-left: 1px solid var(--gray-200); background: #fff; display: flex; flex-direction: column; min-height: 0; }
        .out-bougie { margin: 14px 14px 0; background: var(--indigo-50); border: 1px solid var(--indigo-100); border-radius: 10px; padding: 13px 14px; }
        .out-bougie .h { font-size: 10.5px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: var(--indigo-700); display: flex; align-items: center; gap: 7px; }
        .out-bougie p { font-size: 12px; color: var(--gray-600); line-height: 1.5; margin: 6px 0 9px; }
        .out-bougie p a { color: var(--indigo-600); font-weight: 600; }
        .out-bougie .bougie-note { display: block; margin-top: 8px; font-size: 11px; color: var(--gray-500); }

        .out-tabs { display: flex; gap: 2px; padding: 14px 14px 0; }
        .out-tab { font-size: 12.5px; font-weight: 600; color: var(--gray-500); padding: 8px 13px; border-radius: 8px; cursor: pointer; user-select: none; }
        .out-tab:hover { background: var(--gray-100); }
        .out-tab.active { color: #fff; background: var(--gray-900); }
        .out-tab .n { opacity: 0.6; margin-left: 5px; }
        .out-body { flex: 1; min-height: 0; overflow-y: auto; padding: 12px 14px 14px; }
        .out-body .opane { height: 100%; }
        .out-foot { border-top: 1px solid var(--gray-200); padding: 12px 14px; display: flex; align-items: center; gap: 10px; }
        .out-foot .sp { flex: 1; }

        /* composer.json (highlight.js) */
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

        /* hyvä install steps (kept below output) */
        .hyva-panel { margin: 18px; background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 18px 20px; box-shadow: var(--shadow-sm); }
        .hyva-panel h2 { font-size: 14px; margin: 0 0 8px; font-weight: 700; }
        .hyva-panel > p { font-size: 13px; color: var(--gray-600); margin: 0 0 14px; line-height: 1.55; }
        .hyva-panel a { color: var(--blue-600); }
        .hyva-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
        .hyva-fields label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: var(--gray-600); }
        .hyva-fields input { padding: 6px 8px; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 13px; font-family: var(--mono); }
        .hyva-steps { list-style: decimal; padding-left: 22px; margin: 0; display: flex; flex-direction: column; gap: 12px; }
        .hyva-steps li { font-size: 13px; }
        .hyva-steps .step-label { display: block; color: var(--gray-700); margin-bottom: 4px; font-weight: 600; }
        .hyva-steps small { display: block; margin-top: 4px; color: var(--gray-500); font-size: 11px; }
        .hyva-steps small code { background: var(--gray-100); padding: 1px 4px; border-radius: 3px; }
    </style>
    @livewireStyles
</head>
<body>

{{ $slot }}

@livewireScripts
<script>
    // Pure presentation: highlight, diff-flash, scroll-to-change, copy.
    // All form-state lives in the Livewire component on the server.

    let lastJson = document.getElementById('composer-out')?.textContent || '';

    function diffLineIndices(oldStr, newStr) {
        const oldLines = oldStr.split('\n');
        const newLines = newStr.split('\n');
        const m = oldLines.length, n = newLines.length;
        if (m === 0 || n === 0) return new Set(newLines.map((_, i) => i));
        // A trailing comma flips on the previous line whenever a new entry is
        // inserted after it (or off when the entry is removed). Treat that as
        // unchanged so only the actually-added/removed line lights up.
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

    function paintComposer(json) {
        const el = document.getElementById('composer-out');
        if (!el) return;
        const pre = el.parentElement;
        const changed = diffLineIndices(lastJson, json);
        lastJson = json;
        el.textContent = json;
        delete el.dataset.highlighted;
        hljs.highlightElement(el);
        flashChangedLines(pre, el, groupRanges(changed));
    }

    function copyComposer() {
        const el = document.getElementById('composer-out');
        if (el) navigator.clipboard.writeText(el.textContent);
    }

    // Used by per-step copy buttons in the Hyvä install panel: copies the
    // <code> contents of the nearest preceding <pre> sibling and flashes a
    // momentary "Copied" label so the user gets visual confirmation.
    function copyCmd(btn) {
        const pre = btn.closest('.cmd-row')?.querySelector('pre.cmd code');
        if (!pre) return;
        navigator.clipboard.writeText(pre.textContent);
        const original = btn.innerHTML;
        btn.textContent = 'Copied';
        btn.classList.add('copied');
        btn.disabled = true;
        setTimeout(() => { btn.innerHTML = original; btn.classList.remove('copied'); btn.disabled = false; }, 1200);
    }

    // Initial highlight; subsequent updates come via the Livewire event.
    document.addEventListener('DOMContentLoaded', () => {
        const el = document.getElementById('composer-out');
        if (el) hljs.highlightElement(el);
    });

    document.addEventListener('livewire:initialized', () => {
        Livewire.on('composer-updated', ({json}) => paintComposer(json));
    });
</script>
</body>
</html>
