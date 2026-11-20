/* ===== CUSTOM CURSOR ===== */
const dot = document.querySelector('.cursor-dot');
const ring = document.querySelector('.cursor-ring');
let mx=0,my=0,rx=0,ry=0;
window.addEventListener('mousemove',e=>{mx=e.clientX;my=e.clientY;dot.style.transform=`translate(${mx}px,${my}px) translate(-50%,-50%)`;});
function animateRing(){rx+=(mx-rx)*.18;ry+=(my-ry)*.18;ring.style.transform=`translate(${rx}px,${ry}px) translate(-50%,-50%)`;requestAnimationFrame(animateRing);}
animateRing();
document.querySelectorAll('a,button,.glass-card,.work-card').forEach(el=>{
  el.addEventListener('mouseenter',()=>ring.classList.add('hover'));
  el.addEventListener('mouseleave',()=>ring.classList.remove('hover'));
});

/* ===== CLICK RIPPLE ===== */
const rippleLayer = document.getElementById('ripple-layer');
document.addEventListener('click',e=>{
  for(let i=0;i<3;i++){
    const r = document.createElement('div');
    r.className='ripple';
    r.style.left=e.clientX+'px';
    r.style.top=e.clientY+'px';
    r.style.animationDelay=(i*0.12)+'s';
    rippleLayer.appendChild(r);
    setTimeout(()=>r.remove(),1200);
  }
});

/* ===== SCROLL PROGRESS ===== */
const progress = document.querySelector('.scroll-progress span');
window.addEventListener('scroll',()=>{
  const h=document.documentElement;
  const p=(h.scrollTop/(h.scrollHeight-h.clientHeight))*100;
  progress.style.width=p+'%';
});

/* ===== HERO CINEMATIC SCROLL SLIDES ===== */
/* Guarded: this page currently has no .hero section, so this used to throw
   "Cannot read properties of null" on every scroll event. Kept in place
   (rather than deleted) in case a .hero section is reintroduced later. */
const hero = document.querySelector('.hero');
const slides = document.querySelectorAll('.slide');
const dots = document.querySelectorAll('.slide-indicator .dot');
if (hero) {
  window.addEventListener('scroll',()=>{
    const rect = hero.getBoundingClientRect();
    const total = hero.offsetHeight - window.innerHeight;
    const scrolled = Math.min(Math.max(-rect.top,0),total);
    const ratio = total > 0 ? scrolled/total : 0;
    const idx = Math.min(slides.length-1, Math.floor(ratio*slides.length));
    slides.forEach((s,i)=>s.classList.toggle('active',i===idx));
    dots.forEach((d,i)=>d.classList.toggle('active',i===idx));
  });
}

/* ===== NAV AUTO-HIDE + REVEAL ON SCROLL ===== */
let lastY = 0;
const nav = document.getElementById('nav');
window.addEventListener('scroll',()=>{
  const y=window.scrollY;
  if(y>lastY && y>200) nav.classList.add('hidden'); else nav.classList.remove('hidden');
  lastY=y;
});

/* ===== REVEAL ON SCROLL ===== */
const io = new IntersectionObserver(entries=>{
  entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('visible');io.unobserve(e.target);}});
},{threshold:0.12});
document.querySelectorAll('.reveal').forEach(el=>io.observe(el));

/* ===== GLASS CARD MOUSE GLOW ===== */
document.querySelectorAll('.glass-card').forEach(card=>{
  card.addEventListener('mousemove',e=>{
    const r=card.getBoundingClientRect();
    card.style.setProperty('--mx',(e.clientX-r.left)+'px');
    card.style.setProperty('--my',(e.clientY-r.top)+'px');
  });
});

/* ===== 3D TILT (photo) ===== */
document.querySelectorAll('.tilt').forEach(el=>{
  el.addEventListener('mousemove',e=>{
    const r=el.getBoundingClientRect();
    const x=(e.clientX-r.left)/r.width-.5;
    const y=(e.clientY-r.top)/r.height-.5;
    el.style.transform=`perspective(1200px) rotateY(${x*10}deg) rotateX(${-y*10}deg) translateZ(20px)`;
  });
  el.addEventListener('mouseleave',()=>el.style.transform='');
});

/* ===== TOUCH RIPPLE (mobile) ===== */
document.addEventListener('touchstart',e=>{
  const t=e.touches[0];
  for(let i=0;i<3;i++){
    const r=document.createElement('div');
    r.className='ripple';r.style.left=t.clientX+'px';r.style.top=t.clientY+'px';
    r.style.animationDelay=(i*0.12)+'s';
    rippleLayer.appendChild(r);setTimeout(()=>r.remove(),1200);
  }
},{passive:true});
