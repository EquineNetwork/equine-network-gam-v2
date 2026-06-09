<?php if ( ! defined( 'WPINC' ) ) die; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<style>
/* ── EN GAM design system — brand tokens (Brand Guide v1.0) ───────────────
   Display font: Space Grotesk · UI font: IBM Plex Sans
   Lime #C8FF00 · Deep Black #111 · Chrome #0d0d0d · 8px radius · soft borders */
#engam-v2-wrap{
  --en-lime:#C8FF00; --en-lime-hover:#b8ef00; --en-black:#111111; --en-chrome:#0d0d0d;
  --en-surface:#F5F5F5; --en-border:#E8E8E8; --en-border-soft:#F0F0F0; --en-muted:#888888;
  --en-font-ui:'IBM Plex Sans',Arial,Helvetica,sans-serif;
  --en-font-display:'Space Grotesk','IBM Plex Sans',Arial,sans-serif;
  --en-radius:8px; --en-radius-sm:6px; --en-radius-pill:4px;
}
#engam-v2-wrap *{box-sizing:border-box}
#engam-v2-wrap{font-family:var(--en-font-ui);color:#111111;margin:0 0 0 -20px;padding:0;-webkit-font-smoothing:antialiased}
#engam-v2-wrap .eg-content{padding:0 20px 2px}
#engam-v2-wrap .eg-mast{background:var(--en-chrome);color:#fff;position:relative;overflow:hidden;padding:28px 32px;display:grid;grid-template-columns:1fr auto;gap:24px;align-items:center;margin:0}
#engam-v2-wrap .eg-full-bleed{margin-left:0;margin-right:0}
/* Full-bleed cards span edge-to-edge, so square corners (and a square accent line). */
#engam-v2-wrap .eg-card.eg-full-bleed{border-radius:0;box-shadow:none}
#engam-v2-wrap .eg-full-bleed .eg-accentline{border-radius:0}
#engam-v2-wrap .eg-brand{display:flex;align-items:center;gap:14px;position:relative;z-index:1}
#engam-v2-wrap .eg-logo{width:48px;height:48px;flex-shrink:0;border-radius:10px;font-size:0;color:transparent;background:#0d0d0d url('<?php echo EQUINENETWORK_GAM_V2_URL; ?>admin/img/en-icon-dark.png') center/contain no-repeat}
#engam-v2-wrap .eg-brand-text{position:relative;z-index:1}
#engam-v2-wrap .eg-brand-text small{color:var(--en-lime);font-size:11px;letter-spacing:.14em;text-transform:uppercase;font-weight:600;display:block;font-family:var(--en-font-ui)}
#engam-v2-wrap .eg-brand-text h1{font-family:var(--en-font-display);font-size:34px;line-height:1;margin:5px 0 0;letter-spacing:-.01em;color:#fff;font-weight:700}
#engam-v2-wrap .eg-mast-actions{position:relative;z-index:1;display:flex;gap:10px}
#engam-v2-wrap .eg-btn{font-family:var(--en-font-ui);border:1.5px solid var(--en-lime);background:var(--en-lime);color:#111;border-radius:var(--en-radius-sm);padding:9px 18px;font-size:13px;font-weight:600;letter-spacing:0;cursor:pointer;white-space:nowrap;line-height:1.2;text-decoration:none;display:inline-block;transition:background .12s,border-color .12s,color .12s}
#engam-v2-wrap .eg-btn:hover{background:var(--en-lime-hover);border-color:var(--en-lime-hover)}
#engam-v2-wrap .eg-btn.dark{background:transparent;color:#111;border-color:#333}
#engam-v2-wrap .eg-btn.dark:hover{background:#111;color:#fff;border-color:#111}
#engam-v2-wrap .eg-btn.ghost{background:transparent;color:#fff;border-color:#444}
#engam-v2-wrap .eg-btn.ghost:hover{border-color:var(--en-lime);color:var(--en-lime)}
#engam-v2-wrap .eg-btn.sm{padding:6px 13px;font-size:12px}
#engam-v2-wrap .eg-btn.danger{background:transparent;border-color:#e0a0a0;color:#c0392b}
#engam-v2-wrap .eg-btn.danger:hover{background:#FDE8E8;border-color:#D04040;color:#b02020}
#engam-v2-wrap .eg-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:18px}
#engam-v2-wrap .eg-stat{background:var(--en-surface);border:1px solid var(--en-border);border-radius:var(--en-radius);padding:18px}
#engam-v2-wrap .eg-stat small{display:block;font-size:11px;letter-spacing:.08em;text-transform:uppercase;font-weight:600;color:var(--en-muted)}
#engam-v2-wrap .eg-stat strong{display:block;font-family:var(--en-font-display);font-size:34px;letter-spacing:-.02em;margin:8px 0 4px;line-height:1;font-weight:700}
#engam-v2-wrap .eg-ok{background:#E8F5C8;color:#4a6600;padding:3px 9px;font-size:11px;font-weight:600;display:inline-block;border-radius:var(--en-radius-pill)}
#engam-v2-wrap .eg-na{color:var(--en-muted);font-size:12px;font-weight:500}
#engam-v2-wrap .eg-grid{display:grid;grid-template-columns:1fr 340px;gap:18px}
#engam-v2-wrap .eg-card{background:#fff;border:1px solid var(--en-border);border-radius:var(--en-radius);box-shadow:0 1px 2px rgba(17,17,17,.04)}
#engam-v2-wrap .eg-card.black{background:var(--en-chrome);color:#fff;border-color:var(--en-chrome)}
#engam-v2-wrap .eg-head{padding:18px 24px;border-bottom:1px solid var(--en-border);display:flex;justify-content:space-between;align-items:center;gap:16px}
#engam-v2-wrap .eg-card.black .eg-head{border-color:#2a2a2a}
#engam-v2-wrap .eg-head h2{font-family:var(--en-font-display);font-size:20px;letter-spacing:-.01em;margin:0;font-weight:600}
#engam-v2-wrap .eg-head p{margin:5px 0 0;color:var(--en-muted);font-size:13px;font-weight:400}
#engam-v2-wrap .eg-card.black .eg-head p{color:#bdbdb8}
#engam-v2-wrap .eg-tag{height:24px;display:inline-flex;align-items:center;background:var(--en-lime);color:#111;padding:0 10px;font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;flex-shrink:0;border-radius:var(--en-radius-pill)}
/* Compact placement metric card — no dangling header border, optional footer button. */
#engam-v2-wrap .eg-metric{display:flex;flex-direction:column;height:100%}
#engam-v2-wrap a.eg-metric,#engam-v2-wrap a > .eg-metric{transition:border-color .12s ease,box-shadow .12s ease}
#engam-v2-wrap a:hover .eg-metric{border-color:#111;box-shadow:0 2px 8px rgba(17,17,17,.08)}
#engam-v2-wrap .eg-metric-top{padding:18px 24px;display:flex;justify-content:space-between;align-items:center;gap:16px}
#engam-v2-wrap .eg-metric-top h2{font-family:var(--en-font-display);font-size:18px;letter-spacing:-.01em;margin:0;line-height:1.1;font-weight:600}
#engam-v2-wrap .eg-metric-foot{padding:0 24px 18px;margin-top:auto}
#engam-v2-wrap .eg-metric-row{display:block;font-size:12px;color:#555;padding:2px 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-decoration:none}
#engam-v2-wrap a.eg-metric-row{color:#5a7a00}
#engam-v2-wrap a.eg-metric-row:hover{color:#466600;text-decoration:underline}
#engam-v2-wrap .eg-body{padding:24px}
#engam-v2-wrap .eg-notice{padding:12px 18px;margin-bottom:18px;font-weight:500;font-size:13px;background:#E8F5C8;color:#3c5200;border-left:4px solid var(--en-lime);border-radius:var(--en-radius-sm)}
#engam-v2-wrap .eg-add-form{display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end;padding:18px 24px;background:var(--en-surface);border-bottom:1px solid var(--en-border)}
#engam-v2-wrap .eg-field-inline label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;font-weight:600;display:block;margin-bottom:6px}
#engam-v2-wrap .eg-input{width:100%;border:1px solid #ccc;background:#fff;padding:11px 12px;font-size:14px;font-weight:400;font-family:var(--en-font-ui);outline:none;height:46px;border-radius:var(--en-radius-sm)}
#engam-v2-wrap textarea.eg-input{height:auto}
#engam-v2-wrap select.eg-input{height:46px;padding:0 12px;-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%23888' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:34px}
#engam-v2-wrap .eg-input:focus{border-color:#111;box-shadow:0 0 0 3px rgba(200,255,0,.25)}
#engam-v2-wrap .eg-table{width:100%;border-collapse:collapse}
#engam-v2-wrap .eg-table th{background:transparent;color:var(--en-muted);text-align:left;padding:12px 16px;font-size:11px;text-transform:uppercase;letter-spacing:.08em;font-weight:600;border-bottom:1px solid var(--en-border)}
#engam-v2-wrap .eg-table td{padding:13px 16px;border-bottom:1px solid var(--en-border-soft);font-size:13px;vertical-align:middle}
#engam-v2-wrap .eg-table tr:last-child td{border-bottom:none}
#engam-v2-wrap .eg-table tr:hover td{background:#fafdf0}
#engam-v2-wrap .eg-table .eg-campaign-name{font-weight:600;font-size:14px}
#engam-v2-wrap .eg-table .eg-campaign-id{font-family:'IBM Plex Mono',Consolas,monospace;font-size:12px;color:var(--en-muted);margin-top:2px}
#engam-v2-wrap .eg-badge{display:inline-block;padding:3px 9px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;border-radius:var(--en-radius-pill)}
#engam-v2-wrap .eg-badge.active{background:#E8F5C8;color:#4a6600}
#engam-v2-wrap .eg-badge.inactive{background:var(--en-border-soft);color:var(--en-muted)}
#engam-v2-wrap .eg-badge.scheduled{background:#E3EEFF;color:#2257b8}
#engam-v2-wrap .eg-badge.expired{background:#FDE8E8;color:#c0392b}
#engam-v2-wrap .eg-actions-cell{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
#engam-v2-wrap .eg-empty{text-align:center;padding:40px 24px;color:var(--en-muted)}
#engam-v2-wrap .eg-empty strong{display:block;font-family:var(--en-font-display);font-size:18px;margin-bottom:6px;color:#111;font-weight:600}
#engam-v2-wrap .eg-settings-field{margin-bottom:16px}
#engam-v2-wrap .eg-settings-field label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;font-weight:600;display:block;margin-bottom:7px}
#engam-v2-wrap .eg-hint{font-size:12px;color:var(--en-muted);margin-top:6px;font-weight:400;letter-spacing:0;text-transform:none}
#engam-v2-wrap .eg-accentline{height:4px;background:var(--en-lime);margin-top:18px;border-radius:0 0 var(--en-radius) var(--en-radius)}
#engam-v2-wrap .eg-form-section{padding:24px;border-bottom:1px solid var(--en-border)}
#engam-v2-wrap .eg-form-section:last-child{border-bottom:none}
#engam-v2-wrap .eg-form-section h3{font-family:var(--en-font-display);font-size:15px;letter-spacing:-.005em;margin:0 0 16px;color:#111;text-transform:none;font-weight:600}
#engam-v2-wrap .eg-img-field{margin-bottom:14px}
#engam-v2-wrap .eg-img-field label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;font-weight:600;display:block;margin-bottom:8px}
#engam-v2-wrap .eg-img-preview{width:100%;max-height:80px;object-fit:contain;border:1px solid var(--en-border);background:var(--en-surface);margin-bottom:6px;display:none;border-radius:var(--en-radius-sm)}
#engam-v2-wrap .eg-img-preview.has-img{display:block}
#engam-v2-wrap .eg-img-actions{display:flex;gap:8px}
#engam-v2-wrap .eg-color-row{display:flex;gap:10px;align-items:center}
#engam-v2-wrap .eg-color-row input[type=color]{width:44px;height:44px;border:1px solid #ccc;padding:2px;cursor:pointer;background:#fff;border-radius:var(--en-radius-sm)}
#engam-v2-wrap label.eg-toggle{display:inline-flex;align-items:center;gap:10px;cursor:pointer;text-transform:none;letter-spacing:0;font-weight:500;font-size:13px;color:#111;margin:0}
#engam-v2-wrap .eg-toggle input{position:absolute;opacity:0;width:1px;height:1px;margin:0}
#engam-v2-wrap .eg-toggle .eg-toggle-track{display:inline-block;width:42px;height:24px;background:#cfcfc8;border-radius:999px;position:relative;transition:background .2s ease;flex-shrink:0}
#engam-v2-wrap .eg-toggle .eg-toggle-thumb{position:absolute;top:3px;left:3px;width:18px;height:18px;background:#fff;border-radius:50%;transition:transform .2s ease;box-shadow:0 1px 2px rgba(0,0,0,.25)}
#engam-v2-wrap .eg-toggle input:checked + .eg-toggle-track{background:var(--en-lime)}
#engam-v2-wrap .eg-toggle input:checked + .eg-toggle-track .eg-toggle-thumb{transform:translateX(18px);background:#111}
#engam-v2-wrap .eg-toggle input:focus-visible + .eg-toggle-track{outline:2px solid var(--en-lime);outline-offset:2px}
#engam-api-status{white-space:pre-line;line-height:1.55;text-align:left;word-break:break-word;max-height:420px;overflow:auto}
#engam-api-status.success{background:#E8F5C8;color:#3c5200}
#engam-api-status.error{background:#FDE8E8;color:#b02020}
#engam-api-status.info{background:var(--en-surface);color:#555}
/* Responsive auto-slot / ad-placement mini grids */
#engam-v2-wrap .eg-mini-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:18px}
@media(max-width:900px){
  #engam-v2-wrap .eg-grid{grid-template-columns:1fr}
  #engam-v2-wrap .eg-add-form{grid-template-columns:1fr;gap:12px}
  #engam-v2-wrap .eg-mast{grid-template-columns:1fr;padding:24px 20px}
  #engam-v2-wrap .eg-mast h1{font-size:28px}
  #engam-v2-wrap .eg-mast-actions{flex-wrap:wrap}
  #engam-v2-wrap .eg-content{padding:0 12px 40px}
  /* Collapse EVERY multi-column grid to a single column on mobile — including the inline-styled
     form rows (page targeting, name/position, padding, hide rules, metric cards, settings) whose
     inline grid-template-columns would otherwise override the class-based rules above. */
  #engam-v2-wrap [style*="grid-template-columns"]{grid-template-columns:1fr!important}
  /* Every text-like input, select and textarea spans the full width on mobile. */
  #engam-v2-wrap .eg-input,
  #engam-v2-wrap select,
  #engam-v2-wrap textarea,
  #engam-v2-wrap input:not([type=checkbox]):not([type=radio]):not([type=color]):not([type=file]):not([type=submit]):not([type=button]){
    width:100%!important;max-width:100%!important;box-sizing:border-box
  }
  /* Masthead action buttons stack full width instead of crowding. */
  #engam-v2-wrap .eg-mast-actions .eg-btn{flex:1 1 100%;text-align:center}
  /* Card-stack table: rows become cards, thead hidden, data-label becomes the field label. */
  #engam-v2-wrap .eg-table-card thead{display:none}
  #engam-v2-wrap .eg-table-card,
  #engam-v2-wrap .eg-table-card tbody,
  #engam-v2-wrap .eg-table-card tr{display:block}
  #engam-v2-wrap .eg-table-card tr{padding:16px;border-bottom:3px solid var(--en-border-soft)}
  #engam-v2-wrap .eg-table-card tr:hover td{background:transparent}
  #engam-v2-wrap .eg-table-card td{display:block;padding:0;border-bottom:none}
  #engam-v2-wrap .eg-table-card td+td{margin-top:8px}
  #engam-v2-wrap .eg-table-card td[data-label]::before{
    content:attr(data-label);display:block;
    font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:#999;margin-bottom:3px
  }
  #engam-v2-wrap .eg-table-card .eg-campaign-name{font-size:16px}
  #engam-v2-wrap .eg-table-card .eg-actions-cell{flex-wrap:wrap;margin-top:4px}
  /* Card header: name + badges on same row */
  #engam-v2-wrap .eg-table-card .egm-top-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:2px}
}
</style>
