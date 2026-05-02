<?php /* _css.php — Design system partagé */ ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#F0F4F8;--sur:#fff;--sur2:#F8FAFC;
  --bdr:#E2E8F0;--bdr2:#CBD5E1;
  --txt:#0F172A;--txt2:#334155;--mut:#64748B;
  --acc:#1E3A8A;--acc-d:#172554;--acc-l:#EFF6FF;
  --blu:#3B82F6;--blu-d:#2563EB;--blu-l:#EFF6FF;
  --grn:#10B981;--grn-d:#059669;--grn-l:#ECFDF5;
  --red:#EF4444;--red-d:#DC2626;--red-l:#FEF2F2;
  --sh:0 1px 3px rgba(0,0,0,.07);
  --sh2:0 4px 16px rgba(0,0,0,.08);
  --r:12px;--rsm:8px;
}
html{scroll-behavior:smooth}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;-webkit-font-smoothing:antialiased;font-size:14px}
a{text-decoration:none;color:inherit}
button,input,select,textarea{font-family:inherit}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:var(--sur2)}
::-webkit-scrollbar-thumb{background:var(--bdr2);border-radius:3px}

/* NAV */
.nav{background:var(--sur);border-bottom:1px solid var(--bdr);padding:.875rem 2rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:var(--sh);gap:1rem;flex-wrap:wrap}
.nav-brand{display:flex;align-items:center;gap:.625rem}
.nav-logo{width:32px;height:32px;background:linear-gradient(135deg,var(--acc),var(--acc-d));border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.nav-title{font-size:.9rem;font-weight:700;color:var(--acc);letter-spacing:.02em}
.nav-sub{font-size:.68rem;color:var(--mut);margin-top:1px}
.nav-links{display:flex;align-items:center;gap:.375rem;flex-wrap:wrap}
.nl{display:inline-flex;align-items:center;gap:.25rem;padding:.35rem .8rem;border-radius:var(--rsm);font-size:.76rem;font-weight:600;border:1.5px solid var(--bdr);background:var(--sur);color:var(--txt2);cursor:pointer;transition:all .15s;text-decoration:none;white-space:nowrap}
.nl:hover{background:var(--sur2);border-color:var(--bdr2)}
.nl.active{background:var(--acc);border-color:var(--acc-d);color:#fff}
.nl-red{border-color:rgba(239,68,68,.3);background:var(--red-l);color:var(--red)}
.nl-red:hover{background:var(--red);color:#fff;border-color:var(--red)}

/* CONTAINER */
.container{max-width:1300px;margin:0 auto;padding:1.75rem 2rem}
@media(max-width:768px){.container{padding:1rem}.nav{padding:.75rem 1rem}}

/* GRID */
.g2{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
.g3{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem}
.g4{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem}
.gs{display:grid;grid-template-columns:2fr 1fr;gap:1.25rem}
@media(max-width:900px){.g2,.g3,.g4,.gs{grid-template-columns:1fr}}

/* PANEL */
.panel{background:var(--sur);border:1px solid var(--bdr);border-radius:var(--r);box-shadow:var(--sh2);overflow:hidden;margin-bottom:1.25rem}
.ph{padding:.875rem 1.25rem;border-bottom:1px solid var(--bdr);display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;background:var(--sur)}
.pt{font-size:.78rem;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:.4rem}
.pb{padding:1.25rem}

/* STAT */
.sc{background:var(--sur);border:1px solid var(--bdr);border-radius:var(--r);box-shadow:var(--sh2);padding:1.25rem;position:relative;overflow:hidden}
.sc::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.sc-o::before{background:linear-gradient(90deg,var(--acc),var(--acc-d))}
.sc-b::before{background:linear-gradient(90deg,var(--blu),var(--blu-d))}
.sc-g::before{background:linear-gradient(90deg,var(--grn),var(--grn-d))}
.sc-r::before{background:linear-gradient(90deg,var(--red),var(--red-d))}
.sc-ic{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1rem;margin-bottom:.625rem}
.oi{background:var(--acc-l)}.bi{background:var(--blu-l)}.gi{background:var(--grn-l)}.ri{background:var(--red-l)}
.sc-v{font-size:1.75rem;font-weight:700;color:var(--txt);line-height:1;margin-bottom:.2rem}
.sc-l{font-size:.68rem;font-weight:600;color:var(--mut);text-transform:uppercase;letter-spacing:.05em}

/* BADGE */
.badge{display:inline-flex;align-items:center;padding:.18rem .6rem;border-radius:9999px;font-size:.68rem;font-weight:700;border:1px solid;white-space:nowrap}
.bo{background:var(--acc-l);color:var(--acc-d);border-color:rgba(245,158,11,.3)}
.bb{background:var(--blu-l);color:var(--blu);border-color:rgba(59,130,246,.25)}
.bg{background:var(--grn-l);color:var(--grn-d);border-color:rgba(16,185,129,.25)}
.br{background:var(--red-l);color:var(--red);border-color:rgba(239,68,68,.25)}
.bgr{background:var(--sur2);color:var(--mut);border-color:var(--bdr)}
.dot{width:8px;height:8px;border-radius:50%;display:inline-block}
.don{background:var(--grn);box-shadow:0 0 0 3px rgba(16,185,129,.2)}
.dof{background:var(--red);box-shadow:0 0 0 3px rgba(239,68,68,.2)}
.dwa{background:var(--acc);animation:dpulse 1.5s infinite}
@keyframes dpulse{0%,100%{box-shadow:0 0 0 3px rgba(245,158,11,.2)}50%{box-shadow:0 0 0 6px transparent}}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:.3rem;padding:.45rem .9rem;border-radius:var(--rsm);font-size:.8rem;font-weight:600;border:1.5px solid;cursor:pointer;transition:all .15s;white-space:nowrap;text-decoration:none;line-height:1.4}
.btn:disabled{opacity:.4;cursor:not-allowed}
.btn-o{background:var(--acc);border-color:var(--acc-d);color:#fff}
.btn-o:hover:not(:disabled){background:var(--acc-d)}
.btn-b{background:var(--blu);border-color:var(--blu-d);color:#fff}
.btn-b:hover:not(:disabled){background:var(--blu-d)}
.btn-g{background:var(--grn);border-color:var(--grn-d);color:#fff}
.btn-g:hover:not(:disabled){background:var(--grn-d)}
.btn-r{background:var(--red);border-color:var(--red-d);color:#fff}
.btn-r:hover:not(:disabled){background:var(--red-d)}
.btn-ghost{background:var(--sur);border-color:var(--bdr);color:var(--txt2)}
.btn-ghost:hover:not(:disabled){background:var(--sur2)}
.btn-sm{padding:.28rem .6rem;font-size:.72rem}
.btn-lg{padding:.65rem 1.5rem;font-size:.9rem}
.btn-fw{width:100%}

/* ALERTS */
.alert{padding:.75rem 1rem;border-radius:var(--rsm);font-size:.82rem;font-weight:500;margin-bottom:1rem;border:1px solid;display:flex;align-items:center;gap:.5rem}
.a-ok{background:var(--grn-l);border-color:rgba(16,185,129,.3);color:#065f46}
.a-err{background:var(--red-l);border-color:rgba(239,68,68,.25);color:#991b1b}
.a-warn{background:var(--acc-l);border-color:rgba(245,158,11,.3);color:#92400e}

/* FORMS */
.field{margin-bottom:.875rem}
.field label,.flbl{display:block;font-size:.7rem;font-weight:700;color:var(--mut);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.35rem}
.inp{width:100%;padding:.6rem .875rem;background:var(--sur);border:1.5px solid var(--bdr);border-radius:var(--rsm);color:var(--txt);font-size:.875rem;outline:none;transition:border-color .15s,box-shadow .15s;font-family:inherit}
.inp:focus{border-color:var(--acc);box-shadow:0 0 0 3px rgba(245,158,11,.1)}
.inp::placeholder{color:#94a3b8}
select.inp{cursor:pointer}
textarea.inp{resize:vertical}

/* TABLES */
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
thead th{padding:.6rem 1rem;text-align:left;font-size:.68rem;font-weight:700;color:var(--mut);text-transform:uppercase;letter-spacing:.06em;background:var(--sur2);border-bottom:2px solid var(--bdr);white-space:nowrap}
tbody tr{border-bottom:1px solid var(--bdr);transition:background .1s}
tbody tr:hover{background:rgba(248,250,252,.9)}
tbody tr:last-child{border-bottom:none}
tbody td{padding:.65rem 1rem;font-size:.82rem;color:var(--txt2);vertical-align:middle}
.mono{font-family:'JetBrains Mono',monospace;font-size:.78rem;font-weight:500;color:var(--txt)}
.tbl-empty{text-align:center;padding:2rem;color:var(--mut);font-size:.85rem}
.tbl-foot{padding:.625rem 1.25rem;background:var(--sur2);border-top:1px solid var(--bdr);font-size:.72rem;color:var(--mut);text-align:center}

/* UPLOAD */
.upz{border:2px dashed var(--bdr2);border-radius:var(--rsm);padding:1.75rem;text-align:center;cursor:pointer;transition:all .2s;position:relative;background:var(--sur2)}
.upz:hover,.upz.drag{border-color:var(--acc);background:var(--acc-l)}
.upz input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}

/* TABS */
.tabs{display:flex;border-bottom:2px solid var(--bdr);gap:0}
.tab{padding:.55rem 1.25rem;font-size:.78rem;font-weight:600;color:var(--mut);cursor:pointer;border:none;background:none;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s;white-space:nowrap;display:flex;align-items:center;gap:.35rem}
.tab:hover{color:var(--txt)}
.tab.on{color:var(--acc-d);border-bottom-color:var(--acc);font-weight:700}
.tp{display:none}.tp.on{display:block}
.tc{background:var(--sur2);border:1px solid var(--bdr);border-radius:9999px;padding:.05rem .4rem;font-size:.6rem;font-weight:700;color:var(--mut)}
.tab.on .tc{background:var(--acc-l);border-color:rgba(245,158,11,.3);color:var(--acc-d)}

/* PROGRESS */
.prog{height:5px;background:var(--sur2);border-radius:9999px;overflow:hidden}
.prog-bar{height:100%;background:linear-gradient(90deg,var(--grn),var(--grn-d));border-radius:9999px;transition:width .3s}

/* SEARCH */
.srow{display:flex;gap:.5rem;align-items:center}
.srow input{flex:1;padding:.6rem 1rem;background:var(--sur);border:1.5px solid var(--bdr);border-radius:var(--rsm);color:var(--txt);font-size:.875rem;outline:none;transition:all .15s}
.srow input:focus{border-color:var(--acc);box-shadow:0 0 0 3px rgba(245,158,11,.1)}
.srow input::placeholder{color:#94a3b8}

/* PILLS */
.pill{display:inline-flex;align-items:center;gap:.2rem;padding:.18rem .5rem;border-radius:9999px;font-size:.67rem;font-weight:600;border:1px solid}
.px{background:var(--grn-l);color:var(--grn-d);border-color:rgba(16,185,129,.3)}
.pc{background:var(--blu-l);color:var(--blu);border-color:rgba(59,130,246,.3)}
.pv{background:#F5F3FF;color:#7C3AED;border-color:rgba(124,58,237,.3)}

/* DANGER */
.danger-box{background:var(--red-l);border:1px solid rgba(239,68,68,.25);border-radius:var(--rsm);padding:1rem}
.danger-title{font-size:.72rem;font-weight:700;color:var(--red);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.625rem}
code{background:var(--sur2);border:1px solid var(--bdr);padding:.1rem .3rem;border-radius:3px;font-family:'JetBrains Mono',monospace;font-size:.75rem;color:var(--txt2)}
</style>
