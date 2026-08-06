<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'

/* ── State ─────────────────────────────────────── */
const loading   = ref(true)
const progress  = ref(0)
const shown     = ref(false)
const canvasRef = ref<HTMLCanvasElement | null>(null)
let raf = 0

/* ── Word cycling ──────────────────────────────── */
const WORDS = ['Models', 'Enums', 'FormRequests', 'Resources']
const wordIdx  = ref(0)
const wordFlip = ref(true)
let wordTimer: ReturnType<typeof setInterval>

/* ── Tab automation ──────────────────────────────── */
const TABS       = ['User.php', 'Post.php', 'Status.php']
const activeTab  = ref(0)
const tabPct     = ref(0)          // 0–100 progress bar fill
const generating = ref(false)      // flash overlay active
const isPaused   = ref(false)      // hover-pause

/* typewriter state */
const visibleLines = ref(0)        // how many TS lines are revealed
const typingLine   = ref('')       // current partial line being typed

const TAB_DURATION  = 4200         // ms per tab
const TYPE_INTERVAL = 52           // ms per char
const LINE_DELAY    = 140          // ms between lines

let tabCycleRaf  = 0
let typewriterT  : ReturnType<typeof setTimeout>
let lineRevealT  : ReturnType<typeof setTimeout>
let cycleStart   = 0
let pauseAccum   = 0               // accumulated paused ms
let pauseAt      = 0               // when we paused

function resetTypewriter() {
  clearTimeout(typewriterT)
  clearTimeout(lineRevealT)
  visibleLines.value = 0
  typingLine.value   = ''
}

function typeLines(panelLines: typeof TS_PANELS[0]) {
  resetTypewriter()
  let lineIdx = 0

  function revealNext() {
    if (isPaused.value) { lineRevealT = setTimeout(revealNext, 80); return }
    if (lineIdx >= panelLines.length) return
    visibleLines.value = lineIdx
    const line = panelLines[lineIdx]
    const fullText = line.tokens.map(t => t.t).join('')
    let charIdx = 0
    typingLine.value = ''

    function typeChar() {
      if (isPaused.value) { typewriterT = setTimeout(typeChar, 80); return }
      if (charIdx <= fullText.length) {
        typingLine.value = fullText.slice(0, charIdx)
        charIdx++
        typewriterT = setTimeout(typeChar, TYPE_INTERVAL)
      } else {
        visibleLines.value = lineIdx + 1
        typingLine.value = ''
        lineIdx++
        lineRevealT = setTimeout(revealNext, LINE_DELAY)
      }
    }
    typeChar()
  }
  lineRevealT = setTimeout(revealNext, 300)
}

function switchTab(idx: number) {
  generating.value = true
  setTimeout(() => {
    activeTab.value  = idx
    generating.value = false
    typeLines(TS_PANELS[idx])
    cycleStart  = performance.now()
    pauseAccum  = 0
    tabPct.value = 0
  }, 380)
}

function startTabCycle() {
  cycleStart = performance.now()

  function tick(now: number) {
    if (!isPaused.value) {
      const elapsed = now - cycleStart - pauseAccum
      tabPct.value = Math.min(100, (elapsed / TAB_DURATION) * 100)
      if (elapsed >= TAB_DURATION) {
        const next = (activeTab.value + 1) % TABS.length
        switchTab(next)
      }
    } else {
      /* accumulate pause time */
      pauseAccum += now - pauseAt
      pauseAt = now
    }
    tabCycleRaf = requestAnimationFrame(tick)
  }
  tabCycleRaf = requestAnimationFrame(tick)
}

function pauseCycle()  { isPaused.value = true;  pauseAt = performance.now() }
function resumeCycle() { isPaused.value = false }

function manualTab(idx: number) {
  if (idx === activeTab.value) return
  cancelAnimationFrame(tabCycleRaf)
  switchTab(idx)
  /* restart cycle after manual pick */
  setTimeout(startTabCycle, 400)
}

/* ── PHP source code (left panel) ─────────────── */
const PHP_PANELS = [
  // User model
  [
    { t: '<?php', tokens: [{ t: '<?php', c: '#4a5070' }] },
    { t: '', tokens: [] },
    { t: 'class User extends Model', tokens: [
      { t: 'class ', c: '#c792ea' }, { t: 'User ', c: '#fbbf24' },
      { t: 'extends ', c: '#c792ea' }, { t: 'Model', c: '#3d78c8' },
    ]},
    { t: '{', tokens: [{ t: '{', c: '#6e7191' }] },
    { t: "  protected $fillable = [", tokens: [
      { t: '  ', c: '' }, { t: 'protected ', c: '#c792ea' },
      { t: '$fillable', c: '#FF5A4D' }, { t: ' = [', c: '#6e7191' },
    ]},
    { t: "    'name', 'email',", tokens: [
      { t: "    'name'", c: '#f1fa8c' }, { t: ', ', c: '#6e7191' },
      { t: "'email'", c: '#f1fa8c' }, { t: ',', c: '#6e7191' },
    ]},
    { t: "    'role',", tokens: [{ t: "    'role'", c: '#f1fa8c' }, { t: ',', c: '#6e7191' }] },
    { t: '  ];', tokens: [{ t: '  ];', c: '#6e7191' }] },
    { t: '', tokens: [] },
    { t: '  protected $casts = [', tokens: [
      { t: '  ', c: '' }, { t: 'protected ', c: '#c792ea' },
      { t: '$casts', c: '#FF5A4D' }, { t: ' = [', c: '#6e7191' },
    ]},
    { t: "    'role' => UserRole::class,", tokens: [
      { t: "    'role'", c: '#f1fa8c' }, { t: ' => ', c: '#6e7191' },
      { t: 'UserRole', c: '#fbbf24' }, { t: '::class,', c: '#6e7191' },
    ], hl: true },
    { t: '  ];', tokens: [{ t: '  ];', c: '#6e7191' }] },
    { t: '}', tokens: [{ t: '}', c: '#6e7191' }] },
  ],
  // Post model
  [
    { t: '<?php', tokens: [{ t: '<?php', c: '#4a5070' }] },
    { t: '', tokens: [] },
    { t: 'class Post extends Model', tokens: [
      { t: 'class ', c: '#c792ea' }, { t: 'Post ', c: '#fbbf24' },
      { t: 'extends ', c: '#c792ea' }, { t: 'Model', c: '#3d78c8' },
    ]},
    { t: '{', tokens: [{ t: '{', c: '#6e7191' }] },
    { t: "  protected $fillable = [", tokens: [
      { t: '  protected ', c: '#c792ea' },
      { t: '$fillable', c: '#FF5A4D' }, { t: ' = [', c: '#6e7191' },
    ]},
    { t: "    'title', 'body',", tokens: [
      { t: "    'title'", c: '#f1fa8c' }, { t: ', ', c: '#6e7191' },
      { t: "'body'", c: '#f1fa8c' }, { t: ',', c: '#6e7191' },
    ]},
    { t: "    'published_at',", tokens: [
      { t: "    'published_at'", c: '#f1fa8c' }, { t: ',', c: '#6e7191' },
    ], hl: true },
    { t: '  ];', tokens: [{ t: '  ];', c: '#6e7191' }] },
    { t: '', tokens: [] },
    { t: '  public function user()', tokens: [
      { t: '  public ', c: '#c792ea' }, { t: 'function ', c: '#3d78c8' },
      { t: 'user', c: '#7ec8a4' }, { t: '()', c: '#6e7191' },
    ]},
    { t: '  {', tokens: [{ t: '  {', c: '#6e7191' }] },
    { t: '    return $this->belongsTo(User::class);', tokens: [
      { t: '    return ', c: '#c792ea' }, { t: '$this', c: '#FF5A4D' },
      { t: '->', c: '#6e7191' }, { t: 'belongsTo', c: '#7ec8a4' },
      { t: '(', c: '#6e7191' }, { t: 'User', c: '#fbbf24' },
      { t: '::class);', c: '#6e7191' },
    ]},
    { t: '  }', tokens: [{ t: '  }', c: '#6e7191' }] },
    { t: '}', tokens: [{ t: '}', c: '#6e7191' }] },
  ],
  // Status enum
  [
    { t: '<?php', tokens: [{ t: '<?php', c: '#4a5070' }] },
    { t: '', tokens: [] },
    { t: 'enum Status: string', tokens: [
      { t: 'enum ', c: '#c792ea' }, { t: 'Status', c: '#fbbf24' },
      { t: ': ', c: '#6e7191' }, { t: 'string', c: '#7ec8a4' },
    ]},
    { t: '{', tokens: [{ t: '{', c: '#6e7191' }] },
    { t: "  case Active = 'active';", tokens: [
      { t: '  case ', c: '#c792ea' }, { t: 'Active', c: '#fbbf24' },
      { t: " = ", c: '#6e7191' }, { t: "'active'", c: '#f1fa8c' },
      { t: ';', c: '#6e7191' },
    ], hl: true },
    { t: "  case Draft = 'draft';", tokens: [
      { t: '  case ', c: '#c792ea' }, { t: 'Draft', c: '#fbbf24' },
      { t: " = ", c: '#6e7191' }, { t: "'draft'", c: '#f1fa8c' },
      { t: ';', c: '#6e7191' },
    ]},
    { t: "  case Archived = 'archived';", tokens: [
      { t: '  case ', c: '#c792ea' }, { t: 'Archived', c: '#fbbf24' },
      { t: " = ", c: '#6e7191' }, { t: "'archived'", c: '#f1fa8c' },
      { t: ';', c: '#6e7191' },
    ]},
    { t: '}', tokens: [{ t: '}', c: '#6e7191' }] },
  ],
]

/* ── TypeScript output (right panel) ──────────── */
const TS_PANELS = [
  // User.ts
  [
    { t: '// ✦ auto-generated', tokens: [{ t: '// ✦ auto-generated', c: '#3d4466' }], cm: true },
    { t: '', tokens: [] },
    { t: 'export interface User {', tokens: [
      { t: 'export ', c: '#3d78c8' }, { t: 'interface ', c: '#c792ea' },
      { t: 'User', c: '#fbbf24' }, { t: ' {', c: '#6e7191' },
    ]},
    { t: '  id: number;', tokens: [
      { t: '  id', c: '#e2e8f0' }, { t: ': ', c: '#6e7191' },
      { t: 'number', c: '#FF5A4D' }, { t: ';', c: '#6e7191' },
    ]},
    { t: '  name: string;', tokens: [
      { t: '  name', c: '#e2e8f0' }, { t: ': ', c: '#6e7191' },
      { t: 'string', c: '#7ec8a4' }, { t: ';', c: '#6e7191' },
    ]},
    { t: '  email: string;', tokens: [
      { t: '  email', c: '#e2e8f0' }, { t: ': ', c: '#6e7191' },
      { t: 'string', c: '#7ec8a4' }, { t: ';', c: '#6e7191' },
    ]},
    { t: '  role: UserRole;', tokens: [
      { t: '  role', c: '#e2e8f0' }, { t: ': ', c: '#6e7191' },
      { t: 'UserRole', c: '#fbbf24' }, { t: ';', c: '#6e7191' },
    ], hl: true },
    { t: '  posts?: Post[];', tokens: [
      { t: '  posts', c: '#e2e8f0' }, { t: '?: ', c: '#6e7191' },
      { t: 'Post', c: '#fbbf24' }, { t: '[];', c: '#6e7191' },
    ]},
    { t: '}', tokens: [{ t: '}', c: '#6e7191' }] },
    { t: '', tokens: [] },
    { t: "export type UserRole = 'admin' | 'member';", tokens: [
      { t: 'export ', c: '#3d78c8' }, { t: 'type ', c: '#c792ea' },
      { t: 'UserRole', c: '#fbbf24' }, { t: ' = ', c: '#6e7191' },
      { t: "'admin'", c: '#f1fa8c' }, { t: ' | ', c: '#6e7191' },
      { t: "'member'", c: '#f1fa8c' }, { t: ';', c: '#6e7191' },
    ]},
  ],
  // Post.ts
  [
    { t: '// ✦ auto-generated', tokens: [{ t: '// ✦ auto-generated', c: '#3d4466' }], cm: true },
    { t: '', tokens: [] },
    { t: 'export interface Post {', tokens: [
      { t: 'export ', c: '#3d78c8' }, { t: 'interface ', c: '#c792ea' },
      { t: 'Post', c: '#fbbf24' }, { t: ' {', c: '#6e7191' },
    ]},
    { t: '  id: number;', tokens: [
      { t: '  id', c: '#e2e8f0' }, { t: ': ', c: '#6e7191' },
      { t: 'number', c: '#FF5A4D' }, { t: ';', c: '#6e7191' },
    ]},
    { t: '  title: string;', tokens: [
      { t: '  title', c: '#e2e8f0' }, { t: ': ', c: '#6e7191' },
      { t: 'string', c: '#7ec8a4' }, { t: ';', c: '#6e7191' },
    ]},
    { t: '  body: string;', tokens: [
      { t: '  body', c: '#e2e8f0' }, { t: ': ', c: '#6e7191' },
      { t: 'string', c: '#7ec8a4' }, { t: ';', c: '#6e7191' },
    ]},
    { t: '  published_at: string | null;', tokens: [
      { t: '  published_at', c: '#e2e8f0' }, { t: ': ', c: '#6e7191' },
      { t: 'string', c: '#7ec8a4' }, { t: ' | ', c: '#6e7191' },
      { t: 'null', c: '#FF5A4D' }, { t: ';', c: '#6e7191' },
    ], hl: true },
    { t: '  user?: User;', tokens: [
      { t: '  user', c: '#e2e8f0' }, { t: '?: ', c: '#6e7191' },
      { t: 'User', c: '#fbbf24' }, { t: ';', c: '#6e7191' },
    ]},
    { t: '}', tokens: [{ t: '}', c: '#6e7191' }] },
  ],
  // Status.ts
  [
    { t: '// ✦ auto-generated', tokens: [{ t: '// ✦ auto-generated', c: '#3d4466' }], cm: true },
    { t: '', tokens: [] },
    { t: "export type Status =", tokens: [
      { t: 'export ', c: '#3d78c8' }, { t: 'type ', c: '#c792ea' },
      { t: 'Status', c: '#fbbf24' }, { t: ' =', c: '#6e7191' },
    ]},
    { t: "  | 'active'", tokens: [
      { t: "  | ", c: '#6e7191' }, { t: "'active'", c: '#f1fa8c' },
    ], hl: true },
    { t: "  | 'draft'", tokens: [
      { t: "  | ", c: '#6e7191' }, { t: "'draft'", c: '#f1fa8c' },
    ]},
    { t: "  | 'archived';", tokens: [
      { t: "  | ", c: '#6e7191' }, { t: "'archived'", c: '#f1fa8c' },
      { t: ';', c: '#6e7191' },
    ]},
    { t: '', tokens: [] },
    { t: 'export const StatusLabels = {', tokens: [
      { t: 'export ', c: '#3d78c8' }, { t: 'const ', c: '#c792ea' },
      { t: 'StatusLabels', c: '#fbbf24' }, { t: ' = {', c: '#6e7191' },
    ]},
    { t: "  active: 'Active',", tokens: [
      { t: '  active', c: '#7ec8a4' }, { t: ': ', c: '#6e7191' },
      { t: "'Active'", c: '#f1fa8c' }, { t: ',', c: '#6e7191' },
    ]},
    { t: "  draft: 'Draft',", tokens: [
      { t: '  draft', c: '#7ec8a4' }, { t: ': ', c: '#6e7191' },
      { t: "'Draft'", c: '#f1fa8c' }, { t: ',', c: '#6e7191' },
    ]},
    { t: '} as const;', tokens: [{ t: '} ', c: '#6e7191' }, { t: 'as const', c: '#c792ea' }, { t: ';', c: '#6e7191' }] },
  ],
]

const currentPhp = computed(() => PHP_PANELS[activeTab.value])
const currentTs  = computed(() => TS_PANELS[activeTab.value])

/* ── Arrow beam (driven by tabPct) ──────────────── */
const arrowAnim = computed(() => tabPct.value)

/* ── Particles ─────────────────────────────────── */
// (arrowTimer removed — arrow now driven by tabPct)
function startParticles(canvas: HTMLCanvasElement) {
  const ctx = canvas.getContext('2d')!
  let W = 0, H = 0
  const SYMS = ['{ }', 'TS', 'PHP', '<T>', '=>', '::', 'interface', 'export', '|>']

  function resize() {
    W = canvas.width  = canvas.offsetWidth
    H = canvas.height = canvas.offsetHeight
  }
  resize()
  const ro = new ResizeObserver(resize)
  ro.observe(canvas)

  const ps = Array.from({ length: 40 }, () => ({
    x: Math.random() * 1600, y: Math.random() * 900,
    vy: -(Math.random() * 0.32 + 0.07),
    vx: (Math.random() - 0.5) * 0.12,
    a: Math.random() * 0.12 + 0.03,
    sym: SYMS[Math.floor(Math.random() * SYMS.length)],
    col: Math.random() > 0.5 ? '#FF2D20' : '#3178C6',
    sz: Math.random() * 9 + 7,
  }))

  function tick() {
    ctx.clearRect(0, 0, W, H)
    ps.forEach(p => {
      p.y += p.vy; p.x += p.vx
      if (p.y < -30) { p.y = H + 20; p.x = Math.random() * W }
      ctx.font = `${p.sz}px 'JetBrains Mono',monospace`
      ctx.fillStyle = p.col + Math.round(p.a * 255).toString(16).padStart(2, '0')
      ctx.fillText(p.sym, p.x, p.y)
    })
    raf = requestAnimationFrame(tick)
  }
  tick()
  return () => { cancelAnimationFrame(raf); ro.disconnect() }
}

let cleanupParticles: (() => void) | null = null

/* ── Loader ─────────────────────────────────────── */
function runLoader() {
  return new Promise<void>(resolve => {
    let p = 0
    const step = () => {
      p = Math.min(100, p + Math.random() * 18 + 5)
      progress.value = Math.round(p)
      if (p >= 100) {
        setTimeout(() => {
          loading.value = false
          setTimeout(() => { shown.value = true }, 80)
          resolve()
        }, 300)
      } else {
        setTimeout(step, Math.random() * 140 + 55)
      }
    }
    setTimeout(step, 100)
  })
}

/* ── Lifecycle ─────────────────────────────────── */
onMounted(async () => {
  await runLoader()
  if (canvasRef.value) cleanupParticles = startParticles(canvasRef.value)

  wordTimer = setInterval(() => {
    wordFlip.value = false
    setTimeout(() => { wordIdx.value = (wordIdx.value + 1) % WORDS.length; wordFlip.value = true }, 320)
  }, 2600)

  /* Start typewriter on first tab, then begin auto-cycle */
  typeLines(TS_PANELS[0])
  setTimeout(startTabCycle, 600)
})

onBeforeUnmount(() => {
  clearInterval(wordTimer)
  cancelAnimationFrame(tabCycleRaf)
  clearTimeout(typewriterT)
  clearTimeout(lineRevealT)
  cleanupParticles?.()
})
</script>

<template>
  <!-- ══════════ LOADER ══════════ -->
  <Transition name="loader-fade">
    <div v-if="loading" class="tg-loader" aria-label="Loading">
      <div class="tgl-inner">
        <svg class="tgl-ring-svg" viewBox="0 0 56 56" fill="none">
          <circle cx="28" cy="28" r="24" stroke="rgba(255,255,255,0.06)" stroke-width="3"/>
          <circle cx="28" cy="28" r="24" stroke="url(#lg)" stroke-width="3"
            stroke-linecap="round"
            stroke-dasharray="150.8"
            :stroke-dashoffset="150.8 * (1 - progress / 100)"
            transform="rotate(-90 28 28)"
          />
          <defs>
            <linearGradient id="lg" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%"   stop-color="#FF2D20"/>
              <stop offset="50%"  stop-color="#FF6B35"/>
              <stop offset="100%" stop-color="#fbbf24"/>
            </linearGradient>
          </defs>
          <text x="28" y="33" text-anchor="middle"
            font-family="JetBrains Mono,monospace" font-size="10"
            font-weight="800" fill="#FF2D20">TS</text>
        </svg>
        <div class="tgl-name">Laravel TypeGen</div>
        <div class="tgl-bar-wrap">
          <div class="tgl-bar" :style="{ width: progress + '%' }"/>
        </div>
        <div class="tgl-num">{{ progress }}<span>%</span></div>
      </div>
    </div>
  </Transition>

  <!-- ══════════ HERO ══════════ -->
  <div class="tg-hero" :class="{ shown }" id="hero-root">

    <canvas ref="canvasRef" class="hero-canvas" aria-hidden="true"/>

    <!-- Aurora atmosphere -->
    <div class="atm atm-1" aria-hidden="true"/>
    <div class="atm atm-2" aria-hidden="true"/>
    <div class="atm atm-3" aria-hidden="true"/>
    <div class="dot-grid"  aria-hidden="true"/>
    <div class="vignette"  aria-hidden="true"/>

    <div class="hero-body">

      <!-- ── BADGE ── -->
      <a href="https://github.com/hemilrajput/laravel-typegen/blob/main/CHANGELOG.md"
         class="hero-badge" target="_blank" rel="noopener"
         style="--d:0.05s">
        <span class="badge-dot"/>
        v2.2.3 — What's new
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
          <path d="M2.5 6.5h7M7 3l3 3.5-3 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </a>

      <!-- ── HEADLINE ── -->
      <h1 class="hero-h1" style="--d:0.12s">
        <span class="h1-top">TypeScript types,</span>
        <span class="h1-bot">
          <span class="h1-red">direct from</span>
          <span class="h1-gold">Laravel</span>
        </span>
      </h1>

      <!-- Word strip -->
      <div class="word-strip" style="--d:0.2s">
        <span class="ws-label">Supports:</span>
        <span class="ws-track">
          <Transition name="wslide">
            <span :key="wordIdx" class="ws-word">{{ WORDS[wordIdx] }}</span>
          </Transition>
        </span>
      </div>

      <!-- ── TAGLINE ── -->
      <p class="hero-sub" style="--d:0.28s">
        One Artisan command turns your <strong>Eloquent models</strong>,
        <strong>Enums</strong>, <strong>FormRequests</strong> &amp;
        <strong>API Resources</strong> into fully-typed TypeScript.
        AST parsing · Zod schemas · CI safety gates.
      </p>

      <!-- ── BUTTONS ── -->
      <div class="hero-btns" style="--d:0.36s">
        <a href="/laravel-typegen/guide/getting-started" class="btn-fire" id="hero-cta">
          <span class="btn-shine"/>
          <span class="btn-text">
            Get started
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
              <path d="M3 7.5h9M9 4l3.5 3.5L9 11" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </a>
        <a href="https://github.com/hemilrajput/laravel-typegen" class="btn-ghost" target="_blank" rel="noopener" id="hero-github">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2C6.477 2 2 6.477 2 12c0 4.418 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.604-3.369-1.341-3.369-1.341-.454-1.155-1.11-1.462-1.11-1.462-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.268 2.75 1.026A9.578 9.578 0 0 1 12 6.836c.85.004 1.705.115 2.504.337 1.909-1.294 2.747-1.026 2.747-1.026.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.741 0 .267.18.579.688.481C19.138 20.163 22 16.418 22 12c0-5.523-4.477-10-10-10z"/>
          </svg>
          GitHub
        </a>
      </div>

      <!-- ── META ── -->
      <div class="hero-meta" style="--d:0.42s">
        <span class="meta-i"><span class="mdot g"/>Open source · MIT</span>
        <span class="msep"/>
        <span class="meta-i">Laravel 11–13</span>
        <span class="msep"/>
        <span class="meta-i">PHP 8.3+</span>
        <span class="msep"/>
        <span class="meta-i"><span class="mdot b"/>54 tests passing</span>
      </div>

      <!-- ══════════════════════════════════════
           CODE TRANSFORMATION STAGE
      ══════════════════════════════════════ -->
      <div class="transform-stage" style="--d:0.52s">

        <!-- Stage header: tabs -->
        <div class="stage-header">
          <div class="stage-tabs">
            <button
              v-for="(tab, i) in TABS" :key="tab"
              class="stage-tab"
              :class="{ active: activeTab === i }"
              @click="manualTab(i)"
              :id="`tab-${tab}`"
            >
              <span class="tab-dot php"/>
              {{ tab }}
            </button>
          </div>
          <div class="stage-meta">
            <span class="stage-badge">
              <span class="pulse-dot"/>
              live generation
            </span>
            <span class="stage-cmd">php artisan typegen:generate</span>
          </div>
        </div>

        <!-- Tab progress bar -->
        <div class="tab-progress-track" aria-hidden="true">
          <div class="tab-progress-bar" :style="{ width: tabPct + '%' }"/>
        </div>

        <!-- Split panels -->
        <div
          class="split-panels"
          @mouseenter="pauseCycle"
          @mouseleave="resumeCycle"
        >

          <!-- Generation flash overlay -->
          <Transition name="gen-flash">
            <div v-if="generating" class="gen-overlay" aria-hidden="true">
              <div class="gen-scanner"/>
              <div class="gen-label">
                <span class="gen-dot"/>
                Generating types…
              </div>
            </div>
          </Transition>

          <!-- ── LEFT: PHP Source ── -->
          <div class="panel panel-php">
            <div class="panel-bar">
              <div class="panel-bar-dots">
                <span class="bd r"/><span class="bd y"/><span class="bd g"/>
              </div>
              <span class="panel-bar-title">
                <span class="lang-badge php-badge">PHP</span>
                {{ TABS[activeTab] }}
              </span>
              <span class="panel-bar-hint">source</span>
            </div>
            <div class="panel-body">
              <Transition name="panel-switch" mode="out-in">
                <div :key="activeTab" class="code-lines">
                  <div
                    v-for="(line, i) in currentPhp"
                    :key="i"
                    class="code-row"
                    :class="{ hl: line.hl }"
                  >
                    <span class="ln">{{ i + 1 }}</span>
                    <span class="lc">
                      <span v-for="(tok, j) in line.tokens" :key="j" :style="{ color: tok.c }">{{ tok.t }}</span>
                    </span>
                  </div>
                </div>
              </Transition>
            </div>
          </div>

          <!-- ── CENTER: Arrow beam ── -->
          <div class="arrow-col">
            <div class="arrow-track">
              <div class="arrow-beam" :style="{ width: arrowAnim + '%' }"/>
              <svg class="arrow-icon" width="28" height="28" viewBox="0 0 28 28" fill="none">
                <circle cx="14" cy="14" r="13" stroke="rgba(255,45,32,0.3)" stroke-width="1"/>
                <path d="M10 14h8M15 10l4 4-4 4" stroke="#FF2D20" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <div class="arrow-label">
              <svg width="11" height="11" viewBox="0 0 11 11" fill="none">
                <path d="M2 5.5h7M6.5 2l3 3.5-3 3.5" stroke="#FF5A4D" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              typegen
            </div>
          </div>

          <!-- ── RIGHT: TypeScript output ── -->
          <div class="panel panel-ts">
            <div class="panel-bar ts-bar">
              <div class="panel-bar-dots">
                <span class="bd r"/><span class="bd y"/><span class="bd g"/>
              </div>
              <span class="panel-bar-title">
                <span class="lang-badge ts-badge">TS</span>
                {{ TABS[activeTab].replace('.php', '.ts') }}
              </span>
              <span class="ts-auto-badge">
                <span class="status-dot"/>
                auto
              </span>
            </div>
            <div class="panel-body ts-body">
              <div :key="activeTab" class="code-lines">
                <template v-for="(line, i) in currentTs" :key="i">
                  <!-- fully revealed lines -->
                  <div
                    v-if="i < visibleLines"
                    class="code-row"
                    :class="{ hl: line.hl, cm: line.cm }"
                  >
                    <span class="ln">{{ i + 1 }}</span>
                    <span class="lc">
                      <span v-for="(tok, j) in line.tokens" :key="j" :style="{ color: tok.c }">{{ tok.t }}</span>
                    </span>
                  </div>
                  <!-- currently-typing line -->
                  <div
                    v-else-if="i === visibleLines"
                    class="code-row typing-row"
                    :class="{ hl: line.hl, cm: line.cm }"
                  >
                    <span class="ln">{{ i + 1 }}</span>
                    <span class="lc">
                      {{ typingLine }}<span class="blink-cur"/>
                    </span>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </div><!-- /split-panels -->

        <!-- Stage footer: terminal strip -->
        <div class="stage-footer">
          <div class="sf-stats">
            <span class="sf-stat">
              <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                <circle cx="6" cy="6" r="5" stroke="#22c55e" stroke-width="1.1"/>
                <path d="M3.5 6l1.5 1.5L8.5 4" stroke="#22c55e" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              54 tests passing
            </span>
            <span class="sf-stat">
              <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                <polyline points="1,9 4,5 7,7.5 10,3 11,5" stroke="#3178C6" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              Zero config
            </span>
            <span class="sf-stat">
              <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                <path d="M6 1L7.4 4.2 11 4.6 8.5 7l.7 3.5L6 9 2.8 10.5l.7-3.5L1 4.6l3.6-.4z" fill="#fbbf24"/>
              </svg>
              AST-powered
            </span>
          </div>
          <div class="sf-cmds">
            <div class="sf-cmd">
              <span class="sf-ps">$</span>
              <code>composer require hemilrajput/laravel-typegen</code>
            </div>
            <div class="sf-div"/>
            <div class="sf-cmd">
              <span class="sf-ps">$</span>
              <code>php artisan typegen:generate</code>
            </div>
          </div>
          <a href="/laravel-typegen/guide/getting-started" class="sf-cta">
            Full guide →
          </a>
        </div>

      </div><!-- /transform-stage -->

      <!-- ── SCROLL INDICATOR ── -->
      <div class="scroll-hint" style="--d:0.85s">
        <div class="scroll-mouse"><div class="scroll-wheel"/></div>
        <span>scroll</span>
      </div>

    </div><!-- /hero-body -->
  </div><!-- /tg-hero -->
</template>

<style scoped>
/* ══════════════════════════════════════════
   LOADER
══════════════════════════════════════════ */
.tg-loader {
  position: fixed; inset: 0; z-index: 9999;
  background: #08080f;
  display: flex; align-items: center; justify-content: center;
}
.tgl-inner {
  display: flex; flex-direction: column;
  align-items: center; gap: 18px;
  animation: fadeUp 0.4s ease both;
}
.tgl-ring-svg { width: 68px; height: 68px; }
.tgl-name {
  font-family: 'Inter', sans-serif;
  font-size: 15px; font-weight: 700;
  color: rgba(255,255,255,0.5); letter-spacing: -0.3px;
}
.tgl-bar-wrap {
  width: 180px; height: 3px;
  background: rgba(255,255,255,0.07); border-radius: 10px; overflow: hidden;
}
.tgl-bar {
  height: 100%; border-radius: 10px;
  background: linear-gradient(90deg, #FF2D20, #FF6B35, #fbbf24);
  transition: width 0.12s ease;
  box-shadow: 0 0 12px rgba(255,45,32,0.5);
}
.tgl-num {
  font-family: 'JetBrains Mono', monospace;
  font-size: 13px; font-weight: 600;
  color: rgba(255,255,255,0.3);
}
.tgl-num span { color: rgba(255,255,255,0.15); }

.loader-fade-leave-active { transition: opacity 0.5s ease, transform 0.5s ease; }
.loader-fade-leave-to     { opacity: 0; transform: scale(1.05); }

/* ══════════════════════════════════════════
   HERO SHELL
══════════════════════════════════════════ */
.tg-hero {
  position: relative;
  min-height: 100svh;
  display: flex; align-items: flex-start;
  padding: 96px 24px 64px;
  max-width: 1280px; margin: 0 auto;
  opacity: 0; transform: translateY(24px);
  transition: opacity 0.85s cubic-bezier(0.34,1.2,0.64,1),
              transform 0.85s cubic-bezier(0.34,1.2,0.64,1);
  overflow: hidden;
}
.tg-hero.shown { opacity: 1; transform: translateY(0); }

.hero-canvas {
  position: absolute; inset: 0; width: 100%; height: 100%;
  pointer-events: none; opacity: 0.45; z-index: 0;
}

/* Atmosphere */
.atm {
  position: absolute; border-radius: 50%;
  pointer-events: none; filter: blur(110px);
  animation: atmFloat 16s ease-in-out infinite alternate;
}
.atm-1 { width: 700px; height: 700px; top: -200px; left: -100px; background: radial-gradient(circle, rgba(255,45,32,0.13), transparent 70%); }
.atm-2 { width: 520px; height: 520px; top: -80px; right: -80px; background: radial-gradient(circle, rgba(49,120,198,0.11), transparent 70%); animation-delay: -5s; }
.atm-3 { width: 380px; height: 380px; bottom: 80px; left: 38%; background: radial-gradient(circle, rgba(124,58,237,0.09), transparent 70%); animation-delay: -10s; }
@keyframes atmFloat {
  from { transform: translate(0,0) scale(1); }
  to   { transform: translate(30px,-30px) scale(1.08); }
}

.dot-grid {
  position: absolute; inset: 0; pointer-events: none; z-index: 0;
  background-image: radial-gradient(circle, rgba(255,255,255,0.04) 1px, transparent 1px);
  background-size: 30px 30px;
  mask-image: radial-gradient(ellipse 100% 80% at 50% 40%, black 0%, transparent 100%);
}
.vignette {
  position: absolute; inset: 0; pointer-events: none; z-index: 1;
  background: radial-gradient(ellipse 120% 100% at 50% 50%, transparent 35%, rgba(8,8,15,0.75) 100%);
}

/* ── Hero body ── */
.hero-body {
  position: relative; z-index: 2;
  width: 100%;
  display: flex; flex-direction: column;
  align-items: center; text-align: center;
}

/* ── Staggered riseIn helper ── */
.hero-badge, .hero-h1, .word-strip, .hero-sub, .hero-btns, .hero-meta, .transform-stage, .scroll-hint {
  opacity: 0; transform: translateY(22px);
  animation: riseIn 0.75s calc(var(--d, 0s)) cubic-bezier(0.34,1.2,0.64,1) both;
}
.tg-hero.shown .hero-badge,
.tg-hero.shown .hero-h1,
.tg-hero.shown .word-strip,
.tg-hero.shown .hero-sub,
.tg-hero.shown .hero-btns,
.tg-hero.shown .hero-meta,
.tg-hero.shown .transform-stage,
.tg-hero.shown .scroll-hint { opacity: 1; }

@keyframes riseIn {
  from { opacity: 0; transform: translateY(28px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Badge ── */
.hero-badge {
  display: inline-flex; align-items: center; gap: 9px;
  padding: 7px 16px; border-radius: 100px;
  font-size: 12.5px; font-weight: 600;
  color: var(--vp-c-text-2);
  background: var(--vp-c-bg-soft);
  border: 1px solid var(--vp-c-border);
  text-decoration: none; margin-bottom: 28px;
  transition: border-color 0.2s, color 0.2s, transform 0.2s;
}
.hero-badge:hover { border-color: rgba(255,45,32,0.4); color: var(--vp-c-text-1); transform: translateY(-2px); }
.badge-dot {
  width: 7px; height: 7px; border-radius: 50%; background: #22c55e; flex-shrink: 0;
  animation: pulseDot 2.4s ease infinite;
}
@keyframes pulseDot {
  0%   { box-shadow: 0 0 0 0 rgba(34,197,94,0.7); }
  70%  { box-shadow: 0 0 0 7px rgba(34,197,94,0); }
  100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
}

/* ── H1 ── */
.hero-h1 {
  font-size: clamp(3.2rem, 8vw, 5.8rem);
  font-weight: 900; letter-spacing: -4.5px; line-height: 1.04;
  color: var(--vp-c-text-1); margin: 0 0 20px;
  font-feature-settings: 'cv02','cv11';
}
.h1-top { display: block; }
.h1-bot { display: flex; align-items: baseline; justify-content: center; flex-wrap: wrap; gap: 14px; }
.h1-red {
  background: linear-gradient(120deg, #FF2D20 0%, #FF6B35 60%, #fbbf24 100%);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.h1-gold {
  background: linear-gradient(120deg, #fbbf24 0%, #f59e0b 80%);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
  animation: goldShimmer 4s ease infinite; background-size: 200% auto;
}
@keyframes goldShimmer {
  0%,100% { background-position: 0% center; }
  50%      { background-position: 100% center; }
}

/* ── Word strip ── */
.word-strip {
  display: inline-flex; align-items: center; gap: 10px;
  padding: 6px 16px; border-radius: 100px;
  background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-border);
  font-size: 13px; margin-bottom: 24px;
}
.ws-label { color: var(--vp-c-text-3); font-weight: 500; }
.ws-track { position: relative; display: inline-block; min-width: 96px; text-align: left; overflow: hidden; height: 1.4em; }
.ws-word { display: block; font-weight: 800; color: #FF2D20; font-family: 'JetBrains Mono', monospace; font-size: 12.5px; }
.wslide-enter-active { transition: all 0.32s cubic-bezier(0.34,1.56,0.64,1); }
.wslide-leave-active { transition: all 0.18s ease; position: absolute; }
.wslide-enter-from   { opacity: 0; transform: translateY(10px); }
.wslide-leave-to     { opacity: 0; transform: translateY(-8px); }

/* ── Sub ── */
.hero-sub {
  font-size: 16px; line-height: 1.82; color: var(--vp-c-text-2);
  max-width: 580px; margin: 0 0 36px;
}
.hero-sub strong { color: var(--vp-c-text-1); font-weight: 700; }

/* ── Buttons ── */
.hero-btns { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; margin-bottom: 22px; }

.btn-fire {
  position: relative; overflow: hidden;
  display: inline-flex; align-items: center;
  padding: 14px 30px; border-radius: 14px;
  font-size: 15px; font-weight: 800;
  text-decoration: none; color: #fff !important;
  background: linear-gradient(135deg, #FF2D20 0%, #d92619 100%);
  box-shadow: 0 2px 8px rgba(255,45,32,0.35), 0 10px 32px rgba(255,45,32,0.28);
  transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.25s, filter 0.25s;
}
.btn-shine {
  position: absolute; inset: 0;
  background: linear-gradient(105deg, transparent 30%, rgba(255,255,255,0.28) 50%, transparent 70%);
  transform: translateX(-100%); transition: transform 0.6s ease;
}
.btn-fire:hover .btn-shine { transform: translateX(100%); }
.btn-fire:hover { transform: translateY(-4px) scale(1.02); box-shadow: 0 6px 14px rgba(255,45,32,0.38), 0 18px 44px rgba(255,45,32,0.32); filter: brightness(1.06); }
.btn-text { position: relative; z-index: 1; display: flex; align-items: center; gap: 8px; }

.btn-ghost {
  display: inline-flex; align-items: center; gap: 9px;
  padding: 14px 28px; border-radius: 14px;
  font-size: 15px; font-weight: 700;
  text-decoration: none; color: var(--vp-c-text-1) !important;
  border: 1.5px solid var(--vp-c-border); background: transparent;
  transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1), border-color 0.2s, background 0.2s;
}
.btn-ghost:hover { transform: translateY(-4px) scale(1.02); border-color: rgba(255,45,32,0.35); background: rgba(255,45,32,0.05); }

/* ── Meta ── */
.hero-meta {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  justify-content: center; margin-bottom: 52px;
}
.meta-i { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; color: var(--vp-c-text-3); }
.mdot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
.mdot.g { background: #22c55e; box-shadow: 0 0 5px #22c55e; }
.mdot.b { background: #3178C6; box-shadow: 0 0 5px #3178C6; }
.msep { width: 4px; height: 4px; border-radius: 50%; background: var(--vp-c-border); }

/* ══════════════════════════════════════════
   TRANSFORM STAGE
══════════════════════════════════════════ */
.transform-stage {
  width: 100%;
  background: var(--vp-c-bg-soft);
  border: 1px solid var(--vp-c-border);
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 32px 100px rgba(0,0,0,0.18), 0 8px 28px rgba(0,0,0,0.10);
  position: relative;
  animation: floatStage 10s ease-in-out infinite;
}
@keyframes floatStage {
  0%,100% { transform: translateY(0); }
  50%      { transform: translateY(-6px); }
}
/* Subtle glow border */
.transform-stage::before {
  content: '';
  position: absolute; inset: -1px; border-radius: 21px;
  background: linear-gradient(135deg, rgba(255,45,32,0.25), rgba(49,120,198,0.18), transparent 60%);
  z-index: -1; pointer-events: none;
}

/* ── Stage header: tabs ── */
.stage-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 20px;
  background: var(--vp-c-bg);
  border-bottom: 1px solid var(--vp-c-border);
  flex-wrap: wrap; gap: 10px;
}
.stage-tabs { display: flex; gap: 4px; }
.stage-tab {
  display: flex; align-items: center; gap: 7px;
  padding: 6px 14px; border-radius: 8px;
  font-size: 12.5px; font-weight: 600;
  font-family: 'JetBrains Mono', monospace;
  color: var(--vp-c-text-3); background: transparent; border: none;
  cursor: pointer; transition: background 0.15s, color 0.15s;
}
.stage-tab.active {
  background: var(--vp-c-bg-soft);
  color: var(--vp-c-text-1);
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.tab-dot { width: 8px; height: 8px; border-radius: 50%; background: #FF2D20; opacity: 0.5; }
.stage-tab.active .tab-dot { opacity: 1; box-shadow: 0 0 5px #FF2D20; }

/* ── Tab auto-progress bar ── */
.tab-progress-track {
  height: 2px;
  background: var(--vp-c-border);
  overflow: hidden;
  position: relative;
}
.tab-progress-bar {
  height: 100%;
  background: linear-gradient(90deg, #FF2D20, #FF6B35, #fbbf24);
  border-radius: 2px;
  box-shadow: 0 0 8px rgba(255,45,32,0.5);
  transition: width 0.08s linear;
}

/* ── Generation flash overlay ── */
.gen-overlay {
  position: absolute; inset: 0; z-index: 20;
  background: rgba(8,8,15,0.88);
  display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 14px;
  backdrop-filter: blur(4px);
}
.gen-scanner {
  position: absolute; top: 0; left: 0; right: 0; height: 2px;
  background: linear-gradient(90deg, transparent, #FF2D20, rgba(255,45,32,0.3), transparent);
  animation: scanLine 0.38s ease-out;
}
@keyframes scanLine {
  from { transform: translateY(0); opacity: 1; }
  to   { transform: translateY(300px); opacity: 0; }
}
.gen-label {
  display: flex; align-items: center; gap: 8px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.6);
}
.gen-dot {
  width: 8px; height: 8px; border-radius: 50%; background: #FF2D20;
  animation: genPulse 0.5s ease infinite alternate;
}
@keyframes genPulse { from { opacity: 0.3; } to { opacity: 1; } }

.gen-flash-enter-active { transition: opacity 0.15s ease; }
.gen-flash-leave-active { transition: opacity 0.3s ease; }
.gen-flash-enter-from, .gen-flash-leave-to { opacity: 0; }

/* ── Blink cursor inside typewriter ── */
.blink-cur {
  display: inline-block; width: 2px; height: 13px;
  background: #FF2D20; margin-left: 1px;
  vertical-align: middle; border-radius: 1px;
  animation: blinkCur 1.05s step-end infinite;
}
@keyframes blinkCur { 0%,100% { opacity: 1; } 50% { opacity: 0; } }

.typing-row .lc { color: rgba(255,255,255,0.7); }

/* ── Pause hint chip ── */
.split-panels:hover .pause-hint { opacity: 1; }
.pause-hint {
  position: absolute; bottom: 8px; right: 10px; z-index: 15;
  display: flex; align-items: center; gap: 5px;
  font-size: 10px; font-weight: 700; letter-spacing: 0.5px;
  text-transform: uppercase; color: rgba(255,255,255,0.3);
  opacity: 0; transition: opacity 0.25s; pointer-events: none;
}


.stage-meta {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.stage-badge {
  display: flex; align-items: center; gap: 6px;
  font-size: 11px; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase;
  color: #22c55e;
  padding: 4px 10px; border-radius: 6px;
  background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2);
}
.pulse-dot {
  width: 6px; height: 6px; border-radius: 50%; background: #22c55e;
  animation: pulseDot 1.8s ease infinite;
}
.stage-cmd {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px; color: var(--vp-c-text-3);
}

/* ── Split panels ── */
.split-panels {
  display: grid;
  grid-template-columns: 1fr 56px 1fr;
  gap: 0;
  position: relative;
}

/* ── Panel shared ── */
.panel {
  display: flex; flex-direction: column;
  background: #09090f;
  font-family: 'JetBrains Mono', ui-monospace, monospace;
  overflow: hidden;
}
.panel-php { border-right: 1px solid rgba(255,255,255,0.06); }

.panel-bar {
  display: flex; align-items: center; gap: 10px;
  padding: 11px 16px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  background: #101020;
}
.ts-bar { background: #0c1020; border-bottom-color: rgba(49,120,198,0.12); }

.panel-bar-dots { display: flex; gap: 6px; flex-shrink: 0; }
.bd { width: 10px; height: 10px; border-radius: 50%; }
.bd.r { background: #ff5f57; } .bd.y { background: #ffbd2e; } .bd.g { background: #28c840; }

.panel-bar-title {
  display: flex; align-items: center; gap: 8px;
  font-size: 11.5px; color: rgba(255,255,255,0.35);
  flex: 1; justify-content: center;
}
.lang-badge {
  font-size: 9px; font-weight: 800; letter-spacing: 0.5px;
  text-transform: uppercase; padding: 2px 7px; border-radius: 4px;
}
.php-badge { background: rgba(119,123,237,0.15); color: #9d9fed; border: 1px solid rgba(119,123,237,0.25); }
.ts-badge  { background: rgba(49,120,198,0.15); color: #3178C6; border: 1px solid rgba(49,120,198,0.25); }

.panel-bar-hint {
  font-size: 10px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;
  color: rgba(255,255,255,0.15);
}
.ts-auto-badge {
  display: flex; align-items: center; gap: 5px;
  font-size: 10px; font-weight: 800; letter-spacing: 0.6px; text-transform: uppercase;
  color: #22c55e; padding: 2px 9px; border-radius: 5px;
  background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2);
}
.status-dot {
  width: 5px; height: 5px; border-radius: 50%; background: #22c55e;
  animation: pulseDot 1.8s ease infinite;
}

.panel-body {
  padding: 16px 0 20px; min-height: 240px; overflow-x: auto;
}
.ts-body { background: rgba(49,120,198,0.025); }

.code-lines { min-width: 0; }
.code-row {
  display: flex; align-items: baseline;
  min-height: 21px; padding: 1.5px 0;
  transition: background 0.12s;
}
.code-row:hover { background: rgba(255,255,255,0.025); }
.code-row.hl {
  background: rgba(255,45,32,0.1) !important;
  border-left: 2.5px solid #FF2D20;
}
.code-row.cm { opacity: 0.5; }
.ln {
  display: inline-block; width: 36px; text-align: right;
  padding-right: 12px; font-size: 11px;
  color: rgba(255,255,255,0.13); user-select: none; flex-shrink: 0;
}
.lc { font-size: 12.5px; line-height: 1.75; letter-spacing: 0.1px; white-space: pre; }

/* Panel switch transition */
.panel-switch-enter-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.panel-switch-leave-active { transition: opacity 0.15s ease; }
.panel-switch-enter-from   { opacity: 0; transform: translateY(8px); }
.panel-switch-leave-to     { opacity: 0; }

/* ── Center arrow column ── */
.arrow-col {
  display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 10px;
  background: linear-gradient(180deg, rgba(255,45,32,0.04) 0%, rgba(255,45,32,0.02) 100%);
  border-left: 1px solid rgba(255,45,32,0.08);
  border-right: 1px solid rgba(255,45,32,0.08);
  padding: 20px 0;
}
.arrow-track {
  display: flex; flex-direction: column; align-items: center; gap: 8px; width: 40px;
}
.arrow-beam {
  height: 2px; max-width: 28px;
  background: linear-gradient(90deg, rgba(255,45,32,0.3), #FF2D20);
  border-radius: 2px; align-self: flex-start;
  box-shadow: 0 0 6px rgba(255,45,32,0.5);
  transition: width 0.1s linear;
}
.arrow-icon {
  animation: arrowPulse 2s ease-in-out infinite;
}
@keyframes arrowPulse {
  0%,100% { transform: scale(1) translateX(0); filter: drop-shadow(0 0 4px rgba(255,45,32,0.4)); }
  50%      { transform: scale(1.08) translateX(2px); filter: drop-shadow(0 0 8px rgba(255,45,32,0.7)); }
}
.arrow-label {
  display: flex; align-items: center; gap: 4px;
  font-size: 9px; font-weight: 800; letter-spacing: 0.8px; text-transform: uppercase;
  color: rgba(255,45,32,0.6);
  writing-mode: vertical-rl; transform: rotate(180deg);
  margin-top: 4px;
}

/* ── Stage footer ── */
.stage-footer {
  display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
  padding: 14px 20px;
  background: var(--vp-c-bg);
  border-top: 1px solid var(--vp-c-border);
}
.sf-stats {
  display: flex; gap: 14px; flex-wrap: wrap; flex-shrink: 0;
}
.sf-stat {
  display: flex; align-items: center; gap: 5px;
  font-size: 11.5px; font-weight: 600; color: var(--vp-c-text-3);
}
.sf-cmds {
  display: flex; align-items: center; gap: 10px; flex: 1;
  font-family: 'JetBrains Mono', monospace;
  background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-border);
  border-radius: 9px; padding: 10px 16px; overflow-x: auto; flex-wrap: nowrap; gap: 8px;
  min-width: 0;
}
.sf-cmd { display: flex; align-items: center; gap: 7px; white-space: nowrap; }
.sf-ps { color: #FF2D20; font-size: 12px; font-weight: 700; user-select: none; }
.sf-cmd code { font-size: 12px; color: var(--vp-c-text-1); background: none; padding: 0; border: none; font-family: inherit; }
.sf-div { width: 1px; height: 18px; background: var(--vp-c-border); flex-shrink: 0; }
.sf-cta {
  padding: 9px 20px; background: #FF2D20; color: #fff !important;
  font-size: 13px; font-weight: 800; border-radius: 9px; text-decoration: none;
  white-space: nowrap; flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(255,45,32,0.3);
  transition: transform 0.2s cubic-bezier(0.34,1.56,0.64,1), filter 0.2s;
  font-family: 'Inter', sans-serif;
}
.sf-cta:hover { transform: translateY(-2px) scale(1.04); filter: brightness(1.08); }

/* ── Scroll hint ── */
.scroll-hint {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  margin-top: 36px; font-size: 10px; font-weight: 700; letter-spacing: 1.2px;
  text-transform: uppercase; color: var(--vp-c-text-3);
}
.scroll-mouse {
  width: 22px; height: 36px; border-radius: 12px;
  border: 1.5px solid var(--vp-c-border);
  display: flex; justify-content: center; padding-top: 6px;
}
.scroll-wheel {
  width: 3px; height: 8px; border-radius: 3px; background: var(--vp-c-text-3);
  animation: scrollWheel 2s ease infinite;
}
@keyframes scrollWheel {
  0%   { transform: translateY(0); opacity: 1; }
  75%  { transform: translateY(12px); opacity: 0; }
  76%  { transform: translateY(0); opacity: 0; }
  100% { opacity: 1; }
}

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Responsive ── */
@media (max-width: 700px) {
  .split-panels { grid-template-columns: 1fr; }
  .arrow-col { display: none; }
  .panel-php { border-right: none; border-bottom: 1px solid rgba(255,255,255,0.06); }
  .hero-h1 { letter-spacing: -2.5px; }
  .stage-header { flex-direction: column; align-items: flex-start; }
  .sf-cmds { display: none; }
}
@media (max-width: 540px) {
  .tg-hero { padding-top: 80px; }
  .stage-tabs .stage-tab:last-child { display: none; }
}
</style>
