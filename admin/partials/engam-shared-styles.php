<?php if ( ! defined( 'WPINC' ) ) die; ?>
<style>
#engam-v2-wrap *{box-sizing:border-box}
#engam-v2-wrap{font-family:Arial,Helvetica,sans-serif;color:#050505;margin:0 0 0 -20px;padding:0}
#engam-v2-wrap .eg-content{padding:0 20px 2px}
#engam-v2-wrap .eg-mast{background:#050505;color:#fff;position:relative;overflow:hidden;padding:32px 38px;display:grid;grid-template-columns:1fr auto;gap:24px;align-items:center;margin:0 0 0 0}
#engam-v2-wrap .eg-full-bleed{margin-left:0;margin-right:0}
#engam-v2-wrap .eg-mast:before{content:"EN EN EN EN EN EN";position:absolute;right:-40px;bottom:-4px;font-size:88px;font-weight:900;letter-spacing:-10px;color:rgba(255,255,255,.035);transform:rotate(-8deg);pointer-events:none}
#engam-v2-wrap .eg-brand{display:flex;align-items:center;gap:14px;position:relative;z-index:1}
#engam-v2-wrap .eg-logo{width:52px;height:40px;background:#d0ff00;color:#111;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;letter-spacing:-4px;flex-shrink:0}
#engam-v2-wrap .eg-brand-text{position:relative;z-index:1}
#engam-v2-wrap .eg-brand-text small{color:#d0ff00;font-size:11px;letter-spacing:.14em;text-transform:uppercase;font-weight:800;display:block}
#engam-v2-wrap .eg-brand-text h1{font-size:42px;line-height:1;margin:4px 0 0;text-transform:uppercase;letter-spacing:-2px;color:#fff}
#engam-v2-wrap .eg-mast-actions{position:relative;z-index:1;display:flex;gap:10px}
#engam-v2-wrap .eg-btn{border:2px solid #050505;background:#d0ff00;color:#111;border-radius:999px;padding:10px 18px;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.04em;cursor:pointer;white-space:nowrap;line-height:1;text-decoration:none;display:inline-block}
#engam-v2-wrap .eg-btn:hover{background:#b9e900}
#engam-v2-wrap .eg-btn.dark{background:#fff;color:#111;border-color:#111}
#engam-v2-wrap .eg-btn.dark:hover{background:#e8e8e8}
#engam-v2-wrap .eg-btn.ghost{background:transparent;color:#fff;border-color:#777}
#engam-v2-wrap .eg-btn.ghost:hover{border-color:#d0ff00;color:#d0ff00}
#engam-v2-wrap .eg-btn.sm{padding:7px 14px;font-size:11px}
#engam-v2-wrap .eg-btn.danger{background:#fff;border-color:#cc0000;color:#cc0000}
#engam-v2-wrap .eg-btn.danger:hover{background:#cc0000;color:#fff}
#engam-v2-wrap .eg-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:18px}
#engam-v2-wrap .eg-stat{background:#fff;border:1px solid #deded8;padding:18px}
#engam-v2-wrap .eg-stat small{display:block;font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:900;color:#555}
#engam-v2-wrap .eg-stat strong{display:block;font-size:36px;letter-spacing:-2px;margin:8px 0 4px;line-height:1}
#engam-v2-wrap .eg-ok{background:#d0ff00;color:#111;padding:2px 8px;font-size:11px;font-weight:900;display:inline-block}
#engam-v2-wrap .eg-na{color:#777;font-size:12px;font-weight:700}
#engam-v2-wrap .eg-grid{display:grid;grid-template-columns:1fr 340px;gap:18px}
#engam-v2-wrap .eg-card{background:#fff;border:1px solid #deded8}
#engam-v2-wrap .eg-card.black{background:#080808;color:#fff;border-color:#080808}
#engam-v2-wrap .eg-head{padding:18px 24px;border-bottom:1px solid #deded8;display:flex;justify-content:space-between;align-items:center;gap:16px}
#engam-v2-wrap .eg-card.black .eg-head{border-color:#2c2c2c}
#engam-v2-wrap .eg-head h2{font-size:22px;text-transform:uppercase;letter-spacing:-1px;margin:0}
#engam-v2-wrap .eg-head p{margin:4px 0 0;color:#777;font-size:13px}
#engam-v2-wrap .eg-card.black .eg-head p{color:#bdbdb8}
#engam-v2-wrap .eg-tag{height:24px;display:inline-flex;align-items:center;background:#d0ff00;color:#111;padding:0 10px;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;flex-shrink:0}
/* Compact placement metric card — no dangling header border, optional footer button. */
#engam-v2-wrap .eg-metric{display:flex;flex-direction:column;height:100%}
#engam-v2-wrap a.eg-metric,#engam-v2-wrap a > .eg-metric{transition:border-color .12s ease}
#engam-v2-wrap a:hover .eg-metric{border-color:#050505}
#engam-v2-wrap .eg-metric-top{padding:18px 24px;display:flex;justify-content:space-between;align-items:center;gap:16px}
#engam-v2-wrap .eg-metric-top h2{font-size:20px;text-transform:uppercase;letter-spacing:-1px;margin:0;line-height:1.1}
#engam-v2-wrap .eg-metric-foot{padding:0 24px 18px;margin-top:auto}
#engam-v2-wrap .eg-body{padding:24px}
#engam-v2-wrap .eg-notice{padding:12px 18px;margin-bottom:18px;font-weight:700;font-size:13px;background:#d0ff00;color:#111;border-left:6px solid #050505}
#engam-v2-wrap .eg-add-form{display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end;padding:18px 24px;background:#f5f5f2;border-bottom:1px solid #deded8}
#engam-v2-wrap .eg-field-inline label{font-size:11px;text-transform:uppercase;letter-spacing:.1em;font-weight:900;display:block;margin-bottom:6px}
#engam-v2-wrap .eg-input{width:100%;border:1px solid #bbb;background:#fff;padding:11px 12px;font-size:14px;font-weight:600;outline:none;height:46px}
#engam-v2-wrap textarea.eg-input{height:auto}
#engam-v2-wrap select.eg-input{height:46px;padding:0 12px;-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%23555' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:34px}
#engam-v2-wrap .eg-input:focus{border-color:#050505}
#engam-v2-wrap .eg-table{width:100%;border-collapse:collapse}
#engam-v2-wrap .eg-table th{background:#050505;color:#fff;text-align:left;padding:10px 16px;font-size:11px;text-transform:uppercase;letter-spacing:.1em;font-weight:900}
#engam-v2-wrap .eg-table td{padding:13px 16px;border-bottom:1px solid #ebebeb;font-size:13px;vertical-align:middle}
#engam-v2-wrap .eg-table tr:last-child td{border-bottom:none}
#engam-v2-wrap .eg-table tr:hover td{background:#fafaf8}
#engam-v2-wrap .eg-table .eg-campaign-name{font-weight:700;font-size:14px}
#engam-v2-wrap .eg-table .eg-campaign-id{font-family:Consolas,monospace;font-size:12px;color:#555;margin-top:2px}
#engam-v2-wrap .eg-badge{display:inline-block;padding:3px 8px;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.06em}
#engam-v2-wrap .eg-badge.active{background:#d0ff00;color:#111}
#engam-v2-wrap .eg-badge.inactive{background:#ebebeb;color:#777}
#engam-v2-wrap .eg-badge.scheduled{background:#c8e6ff;color:#0055aa}
#engam-v2-wrap .eg-badge.expired{background:#f5e0e0;color:#990000}
#engam-v2-wrap .eg-actions-cell{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
#engam-v2-wrap .eg-empty{text-align:center;padding:40px 24px;color:#777}
#engam-v2-wrap .eg-empty strong{display:block;font-size:18px;margin-bottom:6px;color:#050505}
#engam-v2-wrap .eg-settings-field{margin-bottom:16px}
#engam-v2-wrap .eg-settings-field label{font-size:11px;text-transform:uppercase;letter-spacing:.1em;font-weight:900;display:block;margin-bottom:7px}
#engam-v2-wrap .eg-hint{font-size:12px;color:#777;margin-top:6px;font-weight:400;letter-spacing:0;text-transform:none}
#engam-v2-wrap .eg-accentline{height:5px;background:#d0ff00;margin-top:18px}
#engam-v2-wrap .eg-form-section{padding:24px;border-bottom:1px solid #deded8}
#engam-v2-wrap .eg-form-section:last-child{border-bottom:none}
#engam-v2-wrap .eg-form-section h3{font-size:14px;text-transform:uppercase;letter-spacing:.08em;margin:0 0 16px;color:#050505}
#engam-v2-wrap .eg-img-field{margin-bottom:14px}
#engam-v2-wrap .eg-img-field label{font-size:11px;text-transform:uppercase;letter-spacing:.1em;font-weight:900;display:block;margin-bottom:8px}
#engam-v2-wrap .eg-img-preview{width:100%;max-height:80px;object-fit:contain;border:1px solid #deded8;background:#f5f5f2;margin-bottom:6px;display:none}
#engam-v2-wrap .eg-img-preview.has-img{display:block}
#engam-v2-wrap .eg-img-actions{display:flex;gap:8px}
#engam-v2-wrap .eg-color-row{display:flex;gap:10px;align-items:center}
#engam-v2-wrap .eg-color-row input[type=color]{width:44px;height:44px;border:1px solid #bbb;padding:2px;cursor:pointer;background:#fff}
#engam-v2-wrap label.eg-toggle{display:inline-flex;align-items:center;gap:10px;cursor:pointer;text-transform:none;letter-spacing:0;font-weight:700;font-size:13px;color:#111;margin:0}
#engam-v2-wrap .eg-toggle input{position:absolute;opacity:0;width:1px;height:1px;margin:0}
#engam-v2-wrap .eg-toggle .eg-toggle-track{display:inline-block;width:42px;height:24px;background:#cfcfc8;border-radius:999px;position:relative;transition:background .2s ease;flex-shrink:0}
#engam-v2-wrap .eg-toggle .eg-toggle-thumb{position:absolute;top:3px;left:3px;width:18px;height:18px;background:#fff;border-radius:50%;transition:transform .2s ease;box-shadow:0 1px 2px rgba(0,0,0,.25)}
#engam-v2-wrap .eg-toggle input:checked + .eg-toggle-track{background:#d0ff00}
#engam-v2-wrap .eg-toggle input:checked + .eg-toggle-track .eg-toggle-thumb{transform:translateX(18px);background:#050505}
#engam-v2-wrap .eg-toggle input:focus-visible + .eg-toggle-track{outline:2px solid #d0ff00;outline-offset:2px}
#engam-api-status{white-space:pre-line;line-height:1.55;text-align:left;word-break:break-word;max-height:420px;overflow:auto}
#engam-api-status.success{background:#d0ff00;color:#111}
#engam-api-status.error{background:#ffdddd;color:#a00}
#engam-api-status.info{background:#f0f0f0;color:#555}
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
  #engam-v2-wrap .eg-table-card tr{padding:16px;border-bottom:3px solid #f0f0ea}
  #engam-v2-wrap .eg-table-card tr:hover td{background:transparent}
  #engam-v2-wrap .eg-table-card td{display:block;padding:0;border-bottom:none}
  #engam-v2-wrap .eg-table-card td+td{margin-top:8px}
  #engam-v2-wrap .eg-table-card td[data-label]::before{
    content:attr(data-label);display:block;
    font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:#999;margin-bottom:3px
  }
  #engam-v2-wrap .eg-table-card .eg-campaign-name{font-size:16px}
  #engam-v2-wrap .eg-table-card .eg-actions-cell{flex-wrap:wrap;margin-top:4px}
  /* Card header: name + badges on same row */
  #engam-v2-wrap .eg-table-card .egm-top-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:2px}
}
</style>
