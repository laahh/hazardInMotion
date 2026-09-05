<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengawasan Langsung Berjarak — PT Berau Coal</title>
<link rel="icon" href="{{ URL::asset('build/images/logo-removebg.png') }}" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ============ TOKENS ============ */
:root{
  --bg:#070a06;
  --bg-2:#0d1309;
  --panel:#111a0d;
  --panel-2:#16210f;
  --line:rgba(190,255,80,.14);
  --line-2:rgba(255,255,255,.08);
  --text:#f4f8ee;
  --muted:rgba(232,240,222,.78);
  --dim:#6f7d66;
  --lime:#c6ef4a;
  --lime-2:#9ccc33;
  --lime-glow:rgba(198,239,74,.35);
  --amber:#ffbe3c;
  --red:#ff5a4a;
  --font:Inter,"Segoe UI",system-ui,sans-serif;
  --r-sm:10px; --r-md:18px; --r-lg:28px; --r-pill:999px;
  --ease:cubic-bezier(.22,1,.36,1);
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
  font-family:var(--font);background:var(--bg);color:var(--text);
  line-height:1.55;-webkit-font-smoothing:antialiased;overflow-x:hidden;
}
::selection{background:var(--lime);color:#0a0f05}
a{color:inherit;text-decoration:none}
button{font:inherit;color:inherit;background:none;border:0;cursor:pointer}
:focus-visible{outline:2px solid var(--lime);outline-offset:3px;border-radius:6px}
 
/* ============ BACKDROP ============ */
.grain{
  position:fixed;inset:0;pointer-events:none;z-index:0;opacity:.18;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3' stitchTiles='stitch'/%3E%3CfeColorMatrix values='0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 .08 0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
}
 
/* ============ NAV ============ */
.nav{
  position:fixed;top:0;left:0;right:0;z-index:50;
  display:flex;align-items:center;justify-content:space-between;
  padding:22px 4.5vw;
  background:linear-gradient(180deg,rgba(5,12,8,.35),transparent);
  transition:background .4s,backdrop-filter .4s,padding .4s;
}
.nav.scrolled{background:rgba(7,10,6,.72);backdrop-filter:blur(14px);padding:14px 4.5vw;border-bottom:1px solid var(--line-2)}
.brand{display:flex;align-items:center;gap:10px;color:#fff}
.brand-mark{
  width:40px;height:40px;border-radius:50%;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(198,239,74,.28);display:grid;place-items:center;
}
.brand-mark img{width:26px;height:26px;object-fit:contain}
.brand-name{font-weight:800;font-size:17px;letter-spacing:-.04em;line-height:1}
.brand-tag{font-size:10px;font-weight:500;color:rgba(232,240,222,.62);margin-top:4px;letter-spacing:.01em}
.nav-links{display:flex;gap:6px;padding:5px;border-radius:var(--r-pill);background:rgba(255,255,255,.03);border:1px solid var(--line-2)}
.nav-links a{padding:8px 16px;border-radius:var(--r-pill);font-size:14px;font-weight:500;color:var(--muted);transition:color .25s,background .25s}
.nav-links a:hover{color:var(--text)}
.nav-links a.active{background:rgba(255,255,255,.08);color:var(--text)}
.btn-ghost{
  display:inline-flex;align-items:center;gap:8px;
  padding:9px 16px;border-radius:var(--r-pill);
  border:1.5px solid rgba(198,239,74,.85);color:var(--lime);font-weight:600;font-size:13px;
  background:rgba(5,14,9,.28);backdrop-filter:blur(10px);
  transition:background .2s,transform .2s,box-shadow .2s;
}
.btn-ghost:hover{background:rgba(198,239,74,.12);transform:translateY(-1px);box-shadow:0 8px 22px rgba(0,0,0,.28),0 0 18px rgba(198,239,74,.18)}
.btn-ghost svg{width:16px;height:16px}
 
/* ============ HERO ============ */
.hero{
  position:relative;min-height:100vh;min-height:100dvh;display:grid;align-items:center;
  padding:clamp(100px,14vh,130px) 4.5vw 80px;overflow:hidden;isolation:isolate;
}
.hero-photo{
  position:absolute;inset:-5%;z-index:-2;
  background:var(--isc-hero) right center / cover no-repeat;
  transform-origin:68% 46%;
  animation:heroKenburns 22s ease-in-out infinite alternate;
  will-change:transform;
}
.hero-fade{
  position:absolute;inset:0;z-index:-1;pointer-events:none;
  background:
    linear-gradient(90deg,rgba(4,10,7,.92) 0%,rgba(4,10,7,.72) 22%,rgba(4,10,7,.28) 42%,rgba(4,10,7,.08) 62%,transparent 78%),
    linear-gradient(180deg,rgba(4,10,7,.42) 0%,transparent 18%,transparent 72%,rgba(5,12,8,.72) 100%);
}
.hero-inner{
  width:100%;display:grid;grid-template-columns:minmax(280px,46vw) 1fr;
  align-items:stretch;min-height:calc(100dvh - 180px);
}
.hero-copy{position:relative;z-index:2;max-width:620px;display:flex;flex-direction:column;justify-content:center}
.pills{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}
.pill{
  padding:6px 12px;border-radius:var(--r-pill);border:1px solid rgba(198,239,74,.55);
  color:var(--lime);font-size:10.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
  background:rgba(8,16,10,.28);animation:badgeGlow 2.8s ease-in-out infinite;
}
.pill:nth-child(2){animation-delay:1.1s}
.hero h1{
  font-size:clamp(34px,4.6vw,64px);line-height:.96;letter-spacing:-.045em;font-weight:800;
  margin-bottom:16px;text-shadow:0 10px 32px rgba(0,0,0,.45);
}
.hero h1 .lime{color:var(--lime);text-shadow:0 0 22px rgba(198,239,74,.28);animation:titleGlow 3.2s ease-in-out infinite}
.hero p{font-size:clamp(13.5px,1.15vw,16px);color:var(--muted);max-width:34rem;line-height:1.55;margin-bottom:26px}
.cta-row{display:flex;align-items:center;gap:18px 22px;flex-wrap:wrap;margin-bottom:34px}
.btn-primary{
  display:inline-flex;align-items:center;gap:8px;
  padding:13px 22px;border-radius:var(--r-pill);background:var(--lime);color:#142016;
  font-weight:700;font-size:14.5px;box-shadow:0 10px 28px rgba(198,239,74,.22);
  transition:transform .22s ease,box-shadow .22s ease,filter .22s ease;
}
.btn-primary:hover{filter:brightness(1.04);transform:translateY(-2px);box-shadow:0 14px 32px rgba(198,239,74,.32)}
.btn-primary svg{width:17px;height:17px;transition:transform .22s}
.btn-primary:hover svg{transform:translateX(4px)}
.link-underline{
  font-weight:700;font-size:14.5px;display:inline-flex;align-items:center;gap:8px;
  color:#fff;border-bottom:1.5px solid rgba(255,255,255,.7);padding:2px 0 4px;
  transition:color .2s,border-color .2s,transform .2s;
}
.link-underline:hover{color:var(--lime);border-color:var(--lime);transform:translateX(3px)}
.link-underline svg{width:17px;height:17px}
.hero-feats{display:flex;gap:18px 26px;flex-wrap:wrap;list-style:none;margin:0;padding:0}
.feat{display:flex;align-items:center;gap:10px;font-size:12.5px;font-weight:600;color:rgba(244,248,238,.88)}
.feat i{
  width:36px;height:36px;border-radius:10px;display:grid;place-items:center;
  border:1px solid rgba(198,239,74,.22);background:rgba(198,239,74,.08);color:var(--lime);
  transition:background .2s,box-shadow .2s,transform .2s;
}
.feat:hover i{background:rgba(198,239,74,.18);box-shadow:0 0 16px rgba(198,239,74,.22);transform:translateY(-1px)}
.feat i svg{width:18px;height:18px}

/* hero load sequence */
.reveal-seq>*:not(.hero-feats){opacity:0;transform:translateY(18px);animation:seq .8s var(--ease) forwards}
.reveal-seq>*:nth-child(1){animation-delay:.08s}
.reveal-seq>*:nth-child(2){animation-delay:.18s}
.reveal-seq>*:nth-child(3){animation-delay:.3s}
.reveal-seq>*:nth-child(4){animation-delay:.42s}
.reveal-seq .feat{opacity:0;transform:translateY(18px);animation:seq .7s var(--ease) forwards}
.reveal-seq .feat:nth-child(1){animation-delay:.54s}
.reveal-seq .feat:nth-child(2){animation-delay:.64s}
.reveal-seq .feat:nth-child(3){animation-delay:.74s}
@keyframes seq{to{opacity:1;transform:none}}
@keyframes badgeGlow{
  0%,100%{box-shadow:0 0 0 0 rgba(198,239,74,0);border-color:rgba(198,239,74,.55)}
  50%{box-shadow:0 0 14px rgba(198,239,74,.22);border-color:rgba(198,239,74,.9)}
}
@keyframes titleGlow{
  0%,100%{text-shadow:0 0 18px rgba(198,239,74,.18)}
  50%{text-shadow:0 0 28px rgba(198,239,74,.42)}
}
@keyframes heroKenburns{from{transform:scale(1) translate(0,0)}to{transform:scale(1.08) translate(-1.4%,1%)}}

/* stage: control room + geofence paths */
.hero-stage{position:relative;height:100%;min-height:520px;pointer-events:none;z-index:2}
.hero-stage-track{position:relative;height:100%;min-height:520px;will-change:transform}
.hero-control{
  position:absolute;top:2%;right:1.5vw;width:min(240px,22vw);margin:0;overflow:hidden;
  border-radius:10px;border:1px solid rgba(255,255,255,.22);
  box-shadow:0 0 0 1px rgba(198,239,74,.12),0 18px 40px rgba(0,0,0,.45);
  animation:stageIn .9s var(--ease) .45s both;
}
.hero-control img{display:block;width:100%;height:auto;aspect-ratio:16/9;object-fit:cover}
.hero-scan{
  position:absolute;left:0;right:0;height:28%;top:-30%;
  background:linear-gradient(180deg,transparent,rgba(198,239,74,.18),transparent);
  animation:heroScan 3.8s ease-in-out infinite;
}
.hero-ping{
  position:absolute;width:18px;height:18px;border-radius:50%;
  border:1.5px solid rgba(198,239,74,.7);box-shadow:0 0 12px rgba(198,239,74,.45);
}
.hero-ping::after{
  content:"";position:absolute;inset:4px;border-radius:50%;
  background:var(--lime);box-shadow:0 0 10px var(--lime);
}
.hero-ping--a{left:18%;bottom:28%;animation:heroPing 2.4s ease-out infinite}
.hero-ping--b{right:28%;top:38%;animation:heroPing 2.4s ease-out infinite .9s}
.hero-paths{
  position:absolute;inset:6% 4% 18% 8%;width:auto;height:auto;overflow:visible;
  animation:heroFade .8s ease .7s both;
}
.hero-paths path{
  fill:none;stroke:var(--lime);stroke-width:1.6;stroke-dasharray:8 10;stroke-linecap:round;
  opacity:.72;filter:drop-shadow(0 0 6px rgba(198,239,74,.55));animation:heroDash 12s linear infinite;
}
.hero-packet{filter:drop-shadow(0 0 8px rgba(198,239,74,.95))}

/* alert card */
.alert-card{
  position:absolute;right:5vw;top:52%;z-index:3;pointer-events:auto;
  width:min(248px,30vw);padding:14px 16px 14px 54px;border-radius:14px;
  background:rgba(10,14,12,.78);border:1px solid rgba(255,90,74,.55);
  backdrop-filter:blur(14px);
  animation:alertIn .7s var(--ease) .85s both,alertPulse 2.6s ease-in-out 1.6s infinite;
}
.alert-card .a-head{margin:0 0 3px;font-size:10.5px;letter-spacing:.14em;font-weight:800;text-transform:uppercase;color:#ff8a80}
.alert-card .a-icon{
  position:absolute;left:14px;top:14px;width:28px;height:28px;border-radius:50%;
  background:#c5221f;display:grid;place-items:center;color:#fff;
  animation:iconPulse 1.6s ease-in-out infinite;
}
.alert-card .a-icon svg{width:15px;height:15px}
.alert-card .a-title{display:block;font-weight:700;font-size:15px;margin:0}
.alert-card .a-tag{
  display:inline-block;margin-top:8px;padding:3px 8px;border-radius:var(--r-pill);
  background:rgba(198,239,74,.14);color:var(--lime);font-size:10.5px;font-weight:700;
}
@keyframes alertIn{from{opacity:0;transform:translateX(22px)}to{opacity:1;transform:none}}
@keyframes alertPulse{
  0%,100%{box-shadow:0 16px 36px rgba(0,0,0,.4),0 0 0 0 rgba(255,90,74,0)}
  50%{box-shadow:0 16px 36px rgba(0,0,0,.4),0 0 22px 2px rgba(255,90,74,.28)}
}
@keyframes iconPulse{
  0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(197,34,31,.5)}
  50%{transform:scale(1.06);box-shadow:0 0 0 8px rgba(197,34,31,0)}
}
@keyframes stageIn{from{opacity:0;transform:translate(12px,-10px) scale(.96)}to{opacity:1;transform:none}}
@keyframes heroScan{0%{top:-32%;opacity:0}15%{opacity:.85}85%{opacity:.85}100%{top:108%;opacity:0}}
@keyframes heroPing{0%{box-shadow:0 0 0 0 rgba(198,239,74,.55)}100%{box-shadow:0 0 0 18px rgba(198,239,74,0)}}
@keyframes heroFade{from{opacity:0}to{opacity:1}}
@keyframes heroDash{to{stroke-dashoffset:-180}}

.scroll-hint{
  position:absolute;left:4.5vw;bottom:22px;z-index:2;
  display:flex;align-items:center;gap:10px;font-size:11.5px;font-weight:600;
  color:rgba(244,248,238,.62);letter-spacing:.02em;
}
.scroll-hint span:first-child{
  width:16px;height:24px;border-radius:12px;border:1.5px solid rgba(244,248,238,.5);position:relative;
}
.scroll-hint span:first-child::after{
  content:"";position:absolute;left:50%;top:5px;width:2.5px;height:6px;border-radius:2px;
  background:var(--lime);transform:translateX(-50%);animation:drop 1.4s infinite;
}
@keyframes drop{0%{opacity:1;top:5px}100%{opacity:0;top:11px}}

.hero-dust{
  position:absolute;z-index:1;inset:auto 0 0 0;height:28vh;pointer-events:none;
  background:
    radial-gradient(ellipse at 18% 90%,rgba(198,239,74,.18),transparent 42%),
    radial-gradient(ellipse at 38% 100%,rgba(198,239,74,.1),transparent 38%),
    radial-gradient(ellipse at 70% 100%,rgba(198,239,74,.08),transparent 40%);
  filter:blur(1px);
}
.hero-dust::before,.hero-dust::after{
  content:"";position:absolute;inset:0;
  background-image:
    radial-gradient(1.4px 1.4px at 12% 70%,rgba(198,239,74,.55),transparent),
    radial-gradient(1px 1px at 22% 82%,rgba(198,239,74,.4),transparent),
    radial-gradient(1.6px 1.6px at 34% 64%,rgba(198,239,74,.5),transparent),
    radial-gradient(1px 1px at 48% 88%,rgba(198,239,74,.35),transparent),
    radial-gradient(1.3px 1.3px at 61% 74%,rgba(198,239,74,.45),transparent),
    radial-gradient(1px 1px at 76% 90%,rgba(198,239,74,.3),transparent),
    radial-gradient(1.5px 1.5px at 88% 68%,rgba(198,239,74,.4),transparent);
  animation:heroDust 7s linear infinite;
}
.hero-dust::after{opacity:.55;animation-duration:11s;animation-direction:reverse}
@keyframes heroDust{from{transform:translateY(8px)}to{transform:translateY(-10px)}}
 
/* ============ SECTIONS ============ */
.section{position:relative;padding:110px 48px;z-index:1}
.wrap{max-width:1360px;margin:0 auto}
.sec-head{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.2fr);gap:32px;align-items:end;margin-bottom:56px}
.sec-head .kicker{display:inline-flex;align-items:center;gap:10px;color:var(--lime);font-weight:700;font-size:13px;margin-bottom:14px}
.sec-head .kicker b{display:inline-grid;place-items:center;width:30px;height:30px;border-radius:50%;background:var(--lime);color:#0a0f05;font-weight:800;font-size:14px}
.sec-head h2{font-size:clamp(34px,4vw,56px);letter-spacing:-.03em;line-height:1.05;font-weight:800}
.sec-head p{color:var(--muted);font-size:17px;max-width:60ch}
.sec-head p a{color:var(--text);border-bottom:1px solid var(--dim)}
 
/* scroll reveal */
.rv{opacity:0;transform:translateY(28px);transition:opacity .9s var(--ease),transform .9s var(--ease)}
.rv.in{opacity:1;transform:none}
.rv-stagger>.rv:nth-child(2){transition-delay:.08s}
.rv-stagger>.rv:nth-child(3){transition-delay:.16s}
.rv-stagger>.rv:nth-child(4){transition-delay:.24s}
.rv-stagger>.rv:nth-child(5){transition-delay:.32s}
 
/* ============ SCOPE GRID ============ */
.scope-grid{display:grid;grid-template-columns:repeat(12,1fr);gap:18px}
.card{
  position:relative;border-radius:var(--r-lg);padding:30px;
  background:linear-gradient(160deg,var(--panel-2),var(--panel) 60%);
  border:1px solid var(--line-2);overflow:hidden;
}
.card::before{
  content:"";position:absolute;inset:0;border-radius:inherit;pointer-events:none;
  background:radial-gradient(420px 220px at var(--mx,20%) var(--my,10%),rgba(183,255,60,.11),transparent 60%);
  opacity:0;transition:opacity .4s;
}
.card:hover::before{opacity:1}
.card-num{
  width:38px;height:38px;border-radius:50%;background:var(--lime);color:#0a0f05;
  display:grid;place-items:center;font-weight:800;font-size:16px;margin-bottom:22px;
  box-shadow:0 0 0 6px rgba(183,255,60,.08);
}
.card h3{font-size:21px;font-weight:800;letter-spacing:-.02em;margin-bottom:10px}
.card p{color:var(--muted);font-size:15px}
.card .card-icon{position:absolute;right:26px;top:26px;width:44px;height:44px;color:var(--lime);opacity:.85}
.card .card-icon svg{width:100%;height:100%}
.c-4{grid-column:span 4}.c-5{grid-column:span 5}.c-6{grid-column:span 6}.c-7{grid-column:span 7}.c-8{grid-column:span 8}.c-12{grid-column:span 12}
 
.checklist{list-style:none;display:grid;gap:12px;margin-top:22px}
.checklist li{display:flex;align-items:center;gap:12px;font-size:15px;color:var(--text)}
.check{width:22px;height:22px;border-radius:50%;background:rgba(183,255,60,.14);border:1px solid rgba(183,255,60,.5);display:grid;place-items:center;flex:none}
.check svg{width:12px;height:12px;stroke:var(--lime);stroke-width:3;fill:none;stroke-dasharray:20;stroke-dashoffset:20;transition:stroke-dashoffset .6s var(--ease) .3s}
.in .check svg{stroke-dashoffset:0}
 
/* tech table */
.tbl{width:100%;border-collapse:collapse;margin-top:18px;font-size:14px}
.tbl th{text-align:left;font-weight:700;color:var(--muted);font-size:12px;letter-spacing:.04em;padding:10px 12px;border-bottom:1px solid var(--line)}
.tbl th:nth-child(n+3),.tbl td:nth-child(n+3){text-align:center;width:88px}
.tbl td{padding:11px 12px;border-bottom:1px solid var(--line-2);transition:background .25s}
.tbl tr:last-child td{border-bottom:0}
.tbl tbody tr:hover td{background:rgba(183,255,60,.05)}
.tbl td:first-child{color:var(--dim);font-weight:600;width:44px}
.tick{display:inline-grid;place-items:center;width:26px;height:26px;border-radius:50%;background:rgba(183,255,60,.14);color:var(--lime);font-weight:800;transform:scale(0);transition:transform .5s var(--ease)}
.in .tick{transform:scale(1)}
.dash{color:var(--dim);letter-spacing:.2em}
.tbl tbody tr:nth-child(1) .tick{transition-delay:.2s}.tbl tbody tr:nth-child(2) .tick{transition-delay:.27s}
.tbl tbody tr:nth-child(3) .tick{transition-delay:.34s}.tbl tbody tr:nth-child(4) .tick{transition-delay:.41s}
.tbl tbody tr:nth-child(5) .tick{transition-delay:.48s}.tbl tbody tr:nth-child(6) .tick{transition-delay:.55s}
.tbl tbody tr:nth-child(7) .tick{transition-delay:.62s}.tbl tbody tr:nth-child(8) .tick{transition-delay:.69s}
.tbl tbody tr:nth-child(9) .tick{transition-delay:.76s}
.legend{display:flex;gap:18px;margin-top:14px;font-size:12px;color:var(--muted)}
.legend span::before{content:"";display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:7px;background:var(--lime);vertical-align:middle}
.legend span:last-child::before{background:var(--dim)}
 
/* location steps */
.steps{list-style:none;display:grid;gap:0;margin-top:14px;position:relative}
.steps li{display:grid;grid-template-columns:44px 1fr;gap:16px;padding:18px 0;position:relative}
.steps li+li{border-top:1px dashed var(--line)}
.steps .s-num{width:44px;height:44px;border-radius:14px;border:1px solid rgba(183,255,60,.45);color:var(--lime);display:grid;place-items:center;font-weight:800;background:rgba(183,255,60,.05)}
.steps b{display:block;font-size:15px;margin-bottom:4px}
.steps small{color:var(--muted);font-size:13.5px;display:block}
 
/* exception card */
.card.warn{border-color:rgba(255,190,60,.3);background:linear-gradient(160deg,#1b160c,var(--panel) 65%)}
.card.warn .card-num{background:var(--amber);box-shadow:0 0 0 6px rgba(255,190,60,.1)}
.card.warn .card-icon{color:var(--amber)}
.bullets{list-style:none;display:grid;gap:10px;margin-top:18px}
.bullets li{position:relative;padding-left:20px;font-size:15px;color:var(--text)}
.bullets li::before{content:"";position:absolute;left:0;top:10px;width:8px;height:8px;border-radius:2px;background:var(--amber);transform:rotate(45deg)}
.callout{
  margin-top:22px;padding:18px 20px;border-radius:var(--r-md);
  background:rgba(255,190,60,.1);border:1px solid rgba(255,190,60,.35);
  color:#ffe1a6;font-size:14.5px;font-weight:500;display:flex;gap:14px;align-items:flex-start;
}
.callout svg{flex:none;width:22px;height:22px;margin-top:2px}
 
/* ============ DEFINITIONS ============ */
.defs-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:34px}
.tab{
  padding:12px 20px;border-radius:var(--r-pill);border:1px solid var(--line-2);
  color:var(--muted);font-weight:600;font-size:14px;display:flex;align-items:center;gap:10px;
  transition:color .25s,border-color .25s,background .25s;
}
.tab b{display:inline-grid;place-items:center;width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,.06);font-size:12px;font-weight:800;color:var(--text);transition:background .25s,color .25s}
.tab:hover{color:var(--text);border-color:var(--line)}
.tab[aria-selected="true"]{color:var(--text);background:rgba(183,255,60,.08);border-color:rgba(183,255,60,.5)}
.tab[aria-selected="true"] b{background:var(--lime);color:#0a0f05}
.panel{display:none;animation:panelIn .6s var(--ease)}
.panel.active{display:block}
@keyframes panelIn{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
 
.def-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px}
.def-item{
  display:grid;grid-template-columns:64px 1fr;gap:20px;align-items:start;
  padding:26px;border-radius:var(--r-lg);background:linear-gradient(160deg,var(--panel-2),var(--panel) 60%);
  border:1px solid var(--line-2);position:relative;overflow:hidden;
}
.def-item::after{
  content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--lime);
  transform:scaleY(0);transform-origin:top;transition:transform .5s var(--ease);
}
.def-item:hover::after{transform:scaleY(1)}
.def-ico{width:64px;height:64px;border-radius:18px;border:1px solid rgba(183,255,60,.35);background:rgba(183,255,60,.06);display:grid;place-items:center;color:var(--lime)}
.def-ico svg{width:28px;height:28px}
.def-ico.amber{border-color:rgba(255,190,60,.4);background:rgba(255,190,60,.07);color:var(--amber)}
.def-ico.red{border-color:rgba(255,61,61,.4);background:rgba(255,61,61,.07);color:#ff7b7b}
.def-item h4{font-size:19px;font-weight:800;letter-spacing:-.02em;margin-bottom:6px}
.def-item p{color:var(--muted);font-size:15px}
 
/* layer visual */
.layer-wrap{display:grid;grid-template-columns:1.1fr .9fr;gap:18px}
.layer-table{padding:30px;border-radius:var(--r-lg);background:linear-gradient(160deg,var(--panel-2),var(--panel) 60%);border:1px solid var(--line-2)}
.layer-table h4{font-size:20px;font-weight:800;margin-bottom:6px;letter-spacing:-.02em}
.layer-table .sub{color:var(--muted);font-size:14px;margin-bottom:20px}
.layers{list-style:none;display:grid;gap:10px}
.layers li{
  display:grid;grid-template-columns:90px 1fr;align-items:center;gap:14px;
  padding:14px 16px;border-radius:14px;border:1px solid var(--line-2);background:rgba(255,255,255,.02);
  opacity:0;transform:translateX(-14px);transition:opacity .6s var(--ease),transform .6s var(--ease),border-color .3s,background .3s;
}
.in .layers li{opacity:1;transform:none}
.layers li:nth-child(1){transition-delay:.1s}.layers li:nth-child(2){transition-delay:.2s}
.layers li:nth-child(3){transition-delay:.3s}.layers li:nth-child(4){transition-delay:.4s}
.layers li:hover{border-color:rgba(183,255,60,.45);background:rgba(183,255,60,.05)}
.layers .lv{font-size:12px;font-weight:800;color:var(--lime);letter-spacing:.06em}
.layers .lv::after{content:"";display:block;height:3px;border-radius:2px;background:var(--lime);margin-top:6px;width:var(--w)}
.layers li:nth-child(1) .lv{--w:25%}.layers li:nth-child(2) .lv{--w:50%}.layers li:nth-child(3) .lv{--w:75%}.layers li:nth-child(4) .lv{--w:100%}
.layers .who{font-size:15px;font-weight:600}
.switch{display:inline-flex;padding:4px;border-radius:var(--r-pill);border:1px solid var(--line-2);gap:4px;margin-bottom:22px}
.switch button{padding:8px 16px;border-radius:var(--r-pill);font-size:13px;font-weight:700;color:var(--muted);transition:background .25s,color .25s}
.switch button[aria-pressed="true"]{background:var(--lime);color:#0a0f05}
 
/* pyramid */
.pyramid{
  padding:30px;border-radius:var(--r-lg);background:linear-gradient(160deg,var(--panel-2),var(--panel) 60%);
  border:1px solid var(--line-2);display:flex;flex-direction:column;justify-content:center;align-items:center;gap:8px;
}
.pyr{
  height:56px;border-radius:12px;display:grid;place-items:center;font-weight:700;font-size:14px;
  border:1px solid rgba(183,255,60,.35);color:var(--text);position:relative;
  transform:scaleX(.6);opacity:0;transition:transform .7s var(--ease),opacity .7s;
}
.in .pyr{transform:none;opacity:1}
.pyr:nth-child(1){width:45%;background:rgba(183,255,60,.32);transition-delay:.45s}
.pyr:nth-child(2){width:62%;background:rgba(183,255,60,.22);transition-delay:.35s}
.pyr:nth-child(3){width:80%;background:rgba(183,255,60,.14);transition-delay:.25s}
.pyr:nth-child(4){width:100%;background:rgba(183,255,60,.07);transition-delay:.15s}
.pyr small{position:absolute;left:14px;font-size:11px;color:var(--lime);font-weight:800}
.pyramid p{color:var(--muted);font-size:13.5px;text-align:center;margin-top:16px;max-width:36ch}
 
/* ============ FLOW BAND ============ */
.flow{
  padding:60px 40px;border-radius:var(--r-lg);
  background:linear-gradient(120deg,rgba(183,255,60,.10),rgba(183,255,60,.02) 50%,rgba(255,61,61,.06));
  border:1px solid var(--line);position:relative;overflow:hidden;
}
.flow h3{font-size:clamp(24px,2.4vw,34px);font-weight:800;letter-spacing:-.025em;margin-bottom:34px;max-width:28ch}
.flow-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;position:relative}
.flow-steps::before{content:"";position:absolute;left:8%;right:8%;top:30px;height:2px;background:linear-gradient(90deg,var(--lime),rgba(183,255,60,.2));z-index:0}
.fs{position:relative;z-index:1;text-align:left}
.fs .dot{width:60px;height:60px;border-radius:50%;border:1px solid rgba(183,255,60,.5);background:var(--bg-2);display:grid;place-items:center;color:var(--lime);margin-bottom:18px;position:relative}
.fs .dot svg{width:24px;height:24px}
.fs .dot::after{content:"";position:absolute;inset:-8px;border-radius:50%;border:1px solid rgba(183,255,60,.35);opacity:0}
.in .fs:nth-child(1) .dot::after{animation:ring 2.4s ease-out infinite}
.in .fs:nth-child(2) .dot::after{animation:ring 2.4s ease-out infinite .6s}
.in .fs:nth-child(3) .dot::after{animation:ring 2.4s ease-out infinite 1.2s}
.in .fs:nth-child(4) .dot::after{animation:ring 2.4s ease-out infinite 1.8s}
@keyframes ring{0%{transform:scale(.8);opacity:.7}100%{transform:scale(1.5);opacity:0}}
.fs b{display:block;font-size:17px;margin-bottom:6px;letter-spacing:-.01em}
.fs span{color:var(--muted);font-size:14px}
 
/* ============ FOOTER ============ */
.footer{padding:60px 48px 40px;border-top:1px solid var(--line-2);position:relative;z-index:1}
.footer .wrap{display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap}
.footer p{color:var(--dim);font-size:13px}
.footer .icons{display:flex;gap:10px}
.footer .icons span{width:42px;height:42px;border-radius:12px;border:1px solid var(--line-2);display:grid;place-items:center;color:var(--muted)}
.footer .icons svg{width:18px;height:18px}
.footer .tagline{display:inline-flex;align-items:center;gap:10px;padding:10px 18px;border-radius:var(--r-pill);background:rgba(183,255,60,.08);border:1px solid rgba(183,255,60,.35);color:var(--lime);font-weight:700;font-size:13px}
 
/* ============ RESPONSIVE ============ */
@media (max-width:1100px){
  .hero-inner{grid-template-columns:1fr}
  .hero-stage{display:none}
  .scope-grid>*{grid-column:span 6}
  .scope-grid .c-12{grid-column:span 12}
  .layer-wrap,.sec-head{grid-template-columns:1fr}
  .flow-steps{grid-template-columns:repeat(2,1fr)}
  .flow-steps::before{display:none}
}
@media (max-width:760px){
  .nav,.nav.scrolled{padding:14px 20px}
  .nav-links{display:none}
  .hero,.section{padding-left:20px;padding-right:20px}
  .hero{padding-top:96px}
  .hero h1{font-size:clamp(30px,9vw,42px)}
  .scope-grid>*{grid-column:span 12}
  .def-grid,.flow-steps{grid-template-columns:1fr}
  .def-item{grid-template-columns:52px 1fr}
  .def-ico{width:52px;height:52px}
  .scroll-hint{left:20px}
  .footer{padding:40px 20px}
}
@media (max-height:760px){
  .hero{padding-top:86px;padding-bottom:58px}
  .hero h1{font-size:clamp(30px,4.2vw,48px)}
  .hero p{margin-bottom:18px}
  .cta-row{margin-bottom:22px}
  .hero-control{width:min(200px,20vw)}
  .hero-stage,.hero-stage-track{min-height:420px}
}
@media (prefers-reduced-motion:reduce){
  *,*::before,*::after{animation-duration:.001ms!important;animation-iteration-count:1!important;transition-duration:.001ms!important}
  .rv,.reveal-seq>*,.reveal-seq .feat,.alert-card,.hero-control,.hero-paths{opacity:1;transform:none}
  .in .tick,.tick{transform:scale(1)}
  .layers li,.pyr{opacity:1;transform:none}
  .hero-packet{display:none}
}
</style>
</head>
<body>
<div class="grain" aria-hidden="true"></div>
 
<!-- ================= NAV ================= -->
<header class="nav" id="nav">
  <a class="brand" href="{{ $homeUrl ?? route('isc.index') }}" aria-label="Berau Coal">
    <span class="brand-mark">
      <img src="{{ URL::asset('build/images/logo-removebg.png') }}" alt="">
    </span>
    <span>
      <div class="brand-name">beraucoal</div>
      <div class="brand-tag">better mining, brighter future.</div>
    </span>
  </a>
  <nav class="nav-links" aria-label="Navigasi utama">
    <a href="#top" class="active" data-nav>Overview</a>
    <a href="#ruang-lingkup" data-nav>Ruang Lingkup</a>
    <a href="#definisi" data-nav>Definisi</a>
    <a href="#alur" data-nav>Alur Intervensi</a>
  </nav>
  <a class="btn-ghost" href="#alur">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
    Masuk Dashboard
  </a>
</header>
 
<!-- ================= HERO ================= -->
<section class="hero" id="top">
  <div class="hero-photo" style="--isc-hero: url('{{ $heroImage ?? asset('isc-assets/isc-home-hero.png') }}')" aria-hidden="true"></div>
  <div class="hero-fade" aria-hidden="true"></div>

  <div class="hero-inner">
    <div class="hero-copy reveal-seq">
      <div class="pills">
        <span class="pill">PENGAWASAN JARAK JAUH</span>
        <span class="pill">REAL-TIME MONITORING</span>
      </div>
      <h1>Pengawasan<br>Langsung<br><span class="lime">Berjarak.</span></h1>
      <p>Ruang lingkup dan definisi pengawasan K3L di PT Berau Coal — menghubungkan CCTV, DMS, drone, dan Risk Intervention Center dalam satu alur observasi, inspeksi, dan intervensi untuk operasi yang aman, efisien, dan berkelanjutan.</p>
      <div class="cta-row">
        <a class="btn-primary" href="#ruang-lingkup">Lihat Ruang Lingkup
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <a class="link-underline" href="#definisi">Pelajari Definisi
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
      </div>
      <ul class="hero-feats">
        <li class="feat"><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="12" cy="12" r="3.5"/></svg></i>CCTV &amp; Mining Eyes</li>
        <li class="feat"><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/></svg></i>Driver Monitoring</li>
        <li class="feat"><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg></i>Risk Intervention Center</li>
      </ul>
    </div>

    <aside class="hero-stage">
      <div class="hero-stage-track">
        <span class="hero-ping hero-ping--a" aria-hidden="true"></span>
        <span class="hero-ping hero-ping--b" aria-hidden="true"></span>
        <figure class="hero-control" aria-hidden="true">
          <img src="{{ $controlRoomImage ?? asset('isc-assets/isc-home-control-room.png') }}" alt="">
          <span class="hero-scan"></span>
        </figure>
        <svg class="hero-paths" viewBox="0 0 640 520" preserveAspectRatio="none" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true">
          <path id="isc-s2-path-a" d="M90 430 C 180 360, 260 220, 520 78" />
          <path id="isc-s2-path-b" d="M140 410 C 250 330, 340 250, 470 210" />
          <circle class="hero-packet" r="4" fill="#c6ef4a">
            <animateMotion dur="4.2s" repeatCount="indefinite" rotate="auto">
              <mpath href="#isc-s2-path-a" xlink:href="#isc-s2-path-a" />
            </animateMotion>
          </circle>
          <circle class="hero-packet" r="3" fill="#c6ef4a">
            <animateMotion dur="5.6s" begin="1.1s" repeatCount="indefinite" rotate="auto">
              <mpath href="#isc-s2-path-b" xlink:href="#isc-s2-path-b" />
            </animateMotion>
          </circle>
        </svg>
        <article class="alert-card" role="status">
          <span class="a-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 4 21 19H3L12 4z"/><path d="M12 10v4M12 16.5v.5"/></svg></span>
          <p class="a-head">Person Detected</p>
          <strong class="a-title">High-Risk Boundary</strong>
          <span class="a-tag">Control Room Alerted</span>
        </article>
      </div>
    </aside>
  </div>

  <div class="scroll-hint"><span></span><span>Jelajahi sistem</span></div>
  <div class="hero-dust" aria-hidden="true"></div>
</section>
 
<!-- ================= 1. RUANG LINGKUP ================= -->
<section class="section" id="ruang-lingkup">
  <div class="wrap">
    <div class="sec-head rv">
      <div>
        <div class="kicker"><b>1</b> Ruang Lingkup</div>
        <h2>Apa yang diawasi, dengan alat apa, dari mana.</h2>
      </div>
      <p>Pengawasan langsung berjarak mencakup observasi dan inspeksi K3L atas seluruh aktivitas operasional yang terpantau jelas — dilakukan dari pos pengawasan atau Risk Intervention Center dengan bantuan teknologi.</p>
    </div>
 
    <div class="scope-grid rv-stagger">
      <!-- 1 Cakupan -->
      <article class="card c-4 rv">
        <div class="card-num">1</div>
        <div class="card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 20V10a8 8 0 0 1 16 0v10"/><path d="M2 20h20"/><path d="M12 2v3"/></svg></div>
        <h3>Cakupan Utama</h3>
        <p>Mencakup observasi dan inspeksi K3L pada seluruh aktivitas kerja.</p>
        <ul class="checklist">
          <li><span class="check"><svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg></span>Observasi aktivitas kerja</li>
          <li><span class="check"><svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg></span>Inspeksi kondisi & area</li>
          <li><span class="check"><svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg></span>Intervensi saat ditemukan risiko</li>
        </ul>
      </article>
 
      <!-- 2 Alat Bantu -->
      <article class="card c-8 rv">
        <div class="card-num">2</div>
        <h3>Alat Bantu & Teknologi</h3>
        <p>Sembilan alat bantu yang dipakai untuk pengawasan langsung (live) dan tinjauan pasca kejadian (post-event).</p>
        <table class="tbl">
          <thead><tr><th>No</th><th>Alat bantu & teknologi</th><th>Live</th><th>Post-event</th></tr></thead>
          <tbody>
            <tr><td>1</td><td>CCTV (Mining Eyes)</td><td><span class="tick">✓</span></td><td><span class="tick">✓</span></td></tr>
            <tr><td>2</td><td>CCTV (Plant & Support)</td><td><span class="tick">✓</span></td><td><span class="tick">✓</span></td></tr>
            <tr><td>3</td><td>Driving Monitoring System (DMS)</td><td><span class="tick">✓</span></td><td><span class="tick">✓</span></td></tr>
            <tr><td>4</td><td>Teropong</td><td><span class="tick">✓</span></td><td><span class="dash">–</span></td></tr>
            <tr><td>5</td><td>Kamera DSLR</td><td><span class="tick">✓</span></td><td><span class="dash">–</span></td></tr>
            <tr><td>6</td><td>Drone</td><td><span class="tick">✓</span></td><td><span class="dash">–</span></td></tr>
            <tr><td>7</td><td>CCTV (Kantor, Mess & Kantin)</td><td><span class="dash">–</span></td><td><span class="tick">✓</span></td></tr>
            <tr><td>8</td><td>In Cabin Camera (ICC)</td><td><span class="dash">–</span></td><td><span class="tick">✓</span></td></tr>
            <tr><td>9</td><td>Mining Eyes Analytics (MEA)</td><td><span class="tick">✓</span></td><td><span class="tick">✓</span></td></tr>
          </tbody>
        </table>
        <div class="legend"><span>Tersedia</span><span>Tidak digunakan</span></div>
      </article>
 
      <!-- 3 Lokasi -->
      <article class="card c-4 rv">
        <div class="card-num">3</div>
        <div class="card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 21s-7-6.5-7-12a7 7 0 0 1 14 0c0 5.5-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/></svg></div>
        <h3>Lokasi Pengawasan</h3>
        <ol class="steps">
          <li><span class="s-num">1</span><div><b>Risk Intervention Center / monitor laptop</b><small>CCTV Mining Eyes, CCTV Plant & Support, DMS</small></div></li>
          <li><span class="s-num">2</span><div><b>Pos pengawasan</b><small>Drone, teropong, kamera DSLR</small></div></li>
          <li><span class="s-num">3</span><div><b>Site dengan Mining Eyes Analytics</b><small>Deteksi otomatis berbasis AI (MEA)</small></div></li>
        </ol>
      </article>
 
      <!-- 4 Objek -->
      <article class="card c-4 rv">
        <div class="card-num">4</div>
        <div class="card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 17h2l2-6h9l3 3h2v3"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/><path d="M7 11V7h5l3 4"/></svg></div>
        <h3>Objek yang Diawasi</h3>
        <ul class="checklist">
          <li><span class="check"><svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg></span>Seluruh aktivitas di area operasional yang terpantau jelas</li>
          <li><span class="check"><svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg></span>Khusus MEA: manusia dan jarak aman HD–LV</li>
        </ul>
      </article>
 
      <!-- 5 Pengecualian -->
      <article class="card c-4 warn rv">
        <div class="card-num">5</div>
        <div class="card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10.3 3.9 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg></div>
        <h3>Pengecualian</h3>
        <p>Aktivitas safety & quality yang perlu pengawasan langsung di lapangan.</p>
        <ul class="bullets">
          <li>Force majeure: listrik, sinyal, jaringan, genset</li>
          <li>Blind spot / keterbatasan teknologi</li>
          <li>Faktor lingkungan: kabut, debu, pencahayaan</li>
        </ul>
        <div class="callout">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 12l3 3 5-6"/><path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z"/></svg>
          <span>Jika terjadi force majeure, blind spot, atau kendala lingkungan, pengawas wajib melakukan pengawasan jarak dekat / ke lapangan dengan persetujuan supervisor berdasarkan assessment risiko.</span>
        </div>
      </article>
    </div>
  </div>
</section>
 
<!-- ================= 2. DEFINISI UTAMA ================= -->
<section class="section" id="definisi" style="padding-top:40px">
  <div class="wrap">
    <div class="sec-head rv">
      <div>
        <div class="kicker"><b>2</b> Definisi Utama</div>
        <h2>Istilah yang dipakai di seluruh sistem.</h2>
      </div>
      <p>Empat kelompok definisi — aktivitas & area, sistem & teknologi, intervensi & sistem pengawasan, serta peran pengawas — plus struktur layer pengawas untuk PT Berau Coal dan mitra kerja.</p>
    </div>
 
    <div class="defs-tabs rv" role="tablist" aria-label="Kelompok definisi">
      <button class="tab" role="tab" aria-selected="true" aria-controls="p-a" id="t-a"><b>A</b> Aktivitas & Area</button>
      <button class="tab" role="tab" aria-selected="false" aria-controls="p-b" id="t-b"><b>B</b> Sistem & Teknologi</button>
      <button class="tab" role="tab" aria-selected="false" aria-controls="p-c" id="t-c"><b>C</b> Intervensi & Sistem Pengawasan</button>
      <button class="tab" role="tab" aria-selected="false" aria-controls="p-d" id="t-d"><b>D</b> Peran Pengawas</button>
    </div>
 
    <!-- A -->
    <div class="panel active rv" id="p-a" role="tabpanel" aria-labelledby="t-a">
      <div class="def-grid">
        <div class="def-item"><div class="def-ico amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10.3 3.9 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg></div><div><h4>Aktivitas Kritis</h4><p>Aktivitas berisiko tinggi yang berpotensi menyebabkan kecelakaan major/fatal.</p></div></div>
        <div class="def-item"><div class="def-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s-7-6.5-7-12a7 7 0 0 1 14 0c0 5.5-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/></svg></div><div><h4>Area Kritis</h4><p>Area dengan riwayat / potensi kecelakaan major/fatal.</p></div></div>
        <div class="def-item"><div class="def-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/><path d="M8 11h6M11 8v6"/></svg></div><div><h4>OAK</h4><p>Observasi aktivitas atau area kritis.</p></div></div>
        <div class="def-item"><div class="def-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M10 9l5 3-5 3z" fill="currentColor"/></svg></div><div><h4>Post Event</h4><p>Observasi dari rekaman CCTV setelah kejadian berlangsung.</p></div></div>
      </div>
    </div>
 
    <!-- B -->
    <div class="panel" id="p-b" role="tabpanel" aria-labelledby="t-b">
      <div class="def-grid">
        <div class="def-item"><div class="def-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/><path d="M6 10h4M6 13h7"/></svg></div><div><h4>Risk Intervention Center</h4><p>Ruang monitor untuk CCTV dan DMS.</p></div></div>
        <div class="def-item"><div class="def-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/></svg></div><div><h4>DMS</h4><p>Memantau pengemudi dan memberi peringatan.</p></div></div>
        <div class="def-item"><div class="def-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="6" width="16" height="12" rx="2"/><path d="M18 10l4-2v8l-4-2"/><circle cx="10" cy="12" r="3"/></svg></div><div><h4>Mining Eyes</h4><p>Pengawasan langsung berbasis CCTV.</p></div></div>
        <div class="def-item"><div class="def-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/></svg></div><div><h4>MEA</h4><p>AI untuk deteksi dan alert penyimpangan (Mining Eyes Analytics).</p></div></div>
      </div>
    </div>
 
    <!-- C -->
    <div class="panel" id="p-c" role="tabpanel" aria-labelledby="t-c">
      <div class="def-grid">
        <div class="def-item"><div class="def-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 10v4a1 1 0 0 0 1 1h3l6 4V5L7 9H4a1 1 0 0 0-1 1z"/><path d="M17 9a4 4 0 0 1 0 6M20 6a8 8 0 0 1 0 12"/></svg></div><div><h4>Intervensi Langsung</h4><p>Teguran / instruksi melalui radio, telepon, speaker, dll.</p></div></div>
        <div class="def-item"><div class="def-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="8" cy="7" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2 20c0-3.5 2.7-6 6-6s6 2.5 6 6"/><path d="M14 19c0-2.5 1.5-4 3.5-4S21 16.5 21 19"/></svg></div><div><h4>Kepengawasan Berjenjang</h4><p>Pengawasan oleh Layer 1 sampai Layer 4.</p></div></div>
        <div class="def-item" style="grid-column:1/-1"><div class="def-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2v6M8 22l4-14 4 14"/><path d="M5 22h14M9.5 15h5"/><path d="M6 6a8 8 0 0 1 12 0"/></svg></div><div><h4>Pengawasan Langsung Berjarak</h4><p>Pengawasan yang dilakukan dari pos, kabin, atau Risk Intervention Center (RIC) dengan bantuan CCTV, DMS, drone, teropong, dan kamera DSLR.</p></div></div>
      </div>
    </div>
 
    <!-- D -->
    <div class="panel" id="p-d" role="tabpanel" aria-labelledby="t-d">
      <div class="def-grid">
        <div class="def-item"><div class="def-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg></div><div><h4>Penanggung Jawab Area</h4><p>Memastikan operasi aman dan efektif di area tanggung jawabnya.</p></div></div>
        <div class="def-item"><div class="def-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3a4 4 0 0 1 4 4v1H8V7a4 4 0 0 1 4-4z"/><path d="M6 8h12v2H6z"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg></div><div><h4>Pengawas</h4><p>Observasi, inspeksi, intervensi, dan coaching.</p></div></div>
        <div class="def-item"><div class="def-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/><circle cx="12" cy="10" r="3"/></svg></div><div><h4>Pengawas RIC</h4><p>Memantau melalui CCTV dan DMS dari Risk Intervention Center.</p></div></div>
        <div class="def-item"><div class="def-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 8h16l-2 12H6z"/><path d="M8 8V6a4 4 0 0 1 8 0v2"/><path d="M4 8L2 4M20 8l2-4"/></svg></div><div><h4>Pengawas Langsung</h4><p>Mengawasi aktivitas secara kontinu.</p></div></div>
        <div class="def-item" style="grid-column:1/-1"><div class="def-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6l8-2 8 2v6c0 5-3.5 8-8 10-4.5-2-8-5-8-10z"/><path d="M9 12l2 2 4-4"/></svg></div><div><h4>Pengawas Lapangan</h4><p>Pengawasan dengan teropong, DSLR, atau drone dari pos pengawasan.</p></div></div>
      </div>
    </div>
 
    <!-- LAYER -->
    <div class="layer-wrap rv" style="margin-top:56px">
      <div class="layer-table" id="layerTable">
        <h4>Layer Pengawas</h4>
        <div class="sub">Struktur kepengawasan berjenjang untuk PT Berau Coal dan mitra kerja.</div>
        <div class="switch" role="group" aria-label="Pilih organisasi">
          <button aria-pressed="true" data-org="bc">PT Berau Coal</button>
          <button aria-pressed="false" data-org="mk">Mitra Kerja</button>
        </div>
        <ul class="layers" id="layers-bc">
          <li><span class="lv">LAYER 1</span><span class="who">Pengawas Lapangan</span></li>
          <li><span class="lv">LAYER 2</span><span class="who">Pengawas RIC / Inspektor / Supervisor</span></li>
          <li><span class="lv">LAYER 3</span><span class="who">PJA atau setara</span></li>
          <li><span class="lv">LAYER 4</span><span class="who">Superintendent / Superior / Manager</span></li>
        </ul>
        <ul class="layers" id="layers-mk" hidden>
          <li><span class="lv">LAYER 1</span><span class="who">Pengawas Lapangan & Pengawas RIC</span></li>
          <li><span class="lv">LAYER 2</span><span class="who">Supervisor / PJA atau setara</span></li>
          <li><span class="lv">LAYER 3</span><span class="who">Superintendent atau setara</span></li>
          <li><span class="lv">LAYER 4</span><span class="who">PJO / Manager atau setara</span></li>
        </ul>
      </div>
      <div class="pyramid">
        <div class="pyr"><small>L4</small>Manajemen</div>
        <div class="pyr"><small>L3</small>PJA / Superintendent</div>
        <div class="pyr"><small>L2</small>Supervisor / RIC</div>
        <div class="pyr"><small>L1</small>Pengawas Lapangan</div>
        <p>Semakin tinggi layer, semakin luas cakupan tanggung jawab — semakin dekat ke lapangan, semakin kontinu pengawasannya.</p>
      </div>
    </div>
  </div>
</section>
 
<!-- ================= ALUR ================= -->
<section class="section" id="alur" style="padding-top:20px">
  <div class="wrap">
    <div class="flow rv">
      <h3>Dari deteksi hingga intervensi — sebelum paparan terjadi.</h3>
      <div class="flow-steps">
        <div class="fs"><div class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="16" height="12" rx="2"/><path d="M18 10l4-2v8l-4-2"/></svg></div><b>Pantau</b><span>CCTV, DMS, drone, teropong, dan MEA memantau aktivitas secara live.</span></div>
        <div class="fs"><div class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.3 3.9 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg></div><b>Deteksi</b><span>Penyimpangan atau pelanggaran zona berisiko terdeteksi oleh pengawas atau AI.</span></div>
        <div class="fs"><div class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10v4a1 1 0 0 0 1 1h3l6 4V5L7 9H4a1 1 0 0 0-1 1z"/><path d="M17 9a4 4 0 0 1 0 6"/></svg></div><b>Intervensi</b><span>Teguran / instruksi langsung melalui radio, telepon, atau speaker.</span></div>
        <div class="fs"><div class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l8-2 8 2v6c0 5-3.5 8-8 10-4.5-2-8-5-8-10z"/><path d="M9 12l2 2 4-4"/></svg></div><b>Tindak lanjut</b><span>Coaching, tinjauan post-event, dan eskalasi berjenjang Layer 1–4.</span></div>
      </div>
    </div>
  </div>
</section>
 
<!-- ================= FOOTER ================= -->
<footer class="footer">
  <div class="wrap">
    <div>
      <div class="brand-name" style="margin-bottom:6px">beraucoal</div>
      <p>PT Berau Coal · Pengawasan Langsung Berjarak — Ruang Lingkup & Definisi</p>
    </div>
    <div class="icons" aria-hidden="true">
      <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l8-2 8 2v6c0 5-3.5 8-8 10-4.5-2-8-5-8-10z"/></svg></span>
      <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 8h16l-2 12H6z"/><path d="M8 8V6a4 4 0 0 1 8 0v2"/></svg></span>
      <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="7" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2 20c0-3.5 2.7-6 6-6s6 2.5 6 6M14 19c0-2.5 1.5-4 3.5-4S21 16.5 21 19"/></svg></span>
      <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a15 15 0 0 1 20 0M5 13a10 10 0 0 1 14 0M8.5 17a5 5 0 0 1 7 0"/><circle cx="12" cy="20" r="1" fill="currentColor"/></svg></span>
      <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/></svg></span>
    </div>
    <div class="tagline">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22c5-3 8-7 8-12V5l-8-3-8 3v5c0 5 3 9 8 12z"/></svg>
      Berau Coal untuk Operasi Aman, Efisien & Berkelanjutan
    </div>
  </div>
</footer>
 
<script>
(function(){
  // Nav background on scroll + active section
  const nav=document.getElementById('nav');
  const links=[...document.querySelectorAll('[data-nav]')];
  const targets=links.map(a=>document.querySelector(a.getAttribute('href'))).filter(Boolean);
  function onScroll(){
    nav.classList.toggle('scrolled',window.scrollY>40);
    const y=window.scrollY+window.innerHeight*0.35;
    let cur=targets[0];
    targets.forEach(t=>{if(t.offsetTop<=y)cur=t;});
    links.forEach(a=>a.classList.toggle('active',a.getAttribute('href')==='#'+cur.id));
  }
  window.addEventListener('scroll',onScroll,{passive:true});onScroll();

  // Hero stage parallax (same feel as ISC home)
  const hero=document.querySelector('.hero');
  const track=document.querySelector('.hero-stage-track');
  const reduce=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if(hero && track && !reduce){
    let mx=0,my=0,tx=0,ty=0,running=false;
    function tick(){
      tx+=(mx-tx)*0.08; ty+=(my-ty)*0.08;
      track.style.transform='translate3d('+tx.toFixed(2)+'px,'+ty.toFixed(2)+'px,0)';
      if(Math.abs(mx-tx)>0.05 || Math.abs(my-ty)>0.05){requestAnimationFrame(tick);}
      else{running=false;}
    }
    hero.addEventListener('mousemove',e=>{
      const r=hero.getBoundingClientRect();
      mx=((e.clientX-r.left)/r.width-0.5)*16;
      my=((e.clientY-r.top)/r.height-0.5)*10;
      if(!running){running=true;requestAnimationFrame(tick);}
    });
    hero.addEventListener('mouseleave',()=>{
      mx=0;my=0;
      if(!running){running=true;requestAnimationFrame(tick);}
    });
  }
 
  // Scroll reveal
  const io=new IntersectionObserver((entries)=>{
    entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target);}});
  },{threshold:.15,rootMargin:'0px 0px -8% 0px'});
  document.querySelectorAll('.rv').forEach(el=>io.observe(el));
 
  // Card cursor glow
  document.querySelectorAll('.card').forEach(card=>{
    card.addEventListener('pointermove',e=>{
      const r=card.getBoundingClientRect();
      card.style.setProperty('--mx',((e.clientX-r.left)/r.width*100)+'%');
      card.style.setProperty('--my',((e.clientY-r.top)/r.height*100)+'%');
    });
  });
 
  // Definition tabs
  const tabs=[...document.querySelectorAll('.tab')];
  const panels=[...document.querySelectorAll('.panel')];
  function selectTab(tab){
    tabs.forEach(t=>t.setAttribute('aria-selected',String(t===tab)));
    panels.forEach(p=>p.classList.toggle('active',p.id===tab.getAttribute('aria-controls')));
  }
  tabs.forEach((t,i)=>{
    t.addEventListener('click',()=>selectTab(t));
    t.addEventListener('keydown',e=>{
      if(e.key==='ArrowRight'){tabs[(i+1)%tabs.length].focus();selectTab(tabs[(i+1)%tabs.length]);}
      if(e.key==='ArrowLeft'){tabs[(i-1+tabs.length)%tabs.length].focus();selectTab(tabs[(i-1+tabs.length)%tabs.length]);}
    });
  });
 
  // Layer org switch
  const bc=document.getElementById('layers-bc'),mk=document.getElementById('layers-mk');
  document.querySelectorAll('.switch button').forEach(btn=>{
    btn.addEventListener('click',()=>{
      document.querySelectorAll('.switch button').forEach(b=>b.setAttribute('aria-pressed',String(b===btn)));
      const isBC=btn.dataset.org==='bc';
      bc.hidden=!isBC;mk.hidden=isBC;
      const list=isBC?bc:mk;
      const wrap=document.getElementById('layerTable').closest('.rv');
      wrap.classList.remove('in');
      requestAnimationFrame(()=>requestAnimationFrame(()=>wrap.classList.add('in')));
    });
  });
})();
</script>
</body>
</html>