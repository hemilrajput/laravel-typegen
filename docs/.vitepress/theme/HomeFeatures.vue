<script setup lang="ts">
const features = [
  {
    icon: '🏗',
    color: '#FF2D20',
    title: 'Eloquent Models',
    desc: 'Deeply inspects casts, appended accessors, hidden columns, custom Cast classes, and relationships — mapped to exact TypeScript interfaces.',
    href: '/laravel-typegen/guide/models',
  },
  {
    icon: '🔗',
    color: '#3178C6',
    title: 'Relationship BFS',
    desc: 'Mark one model; TypeGen auto-discovers every related model via BFS graph walk, including polymorphic MorphTo unions from enforced morph maps.',
    href: '/laravel-typegen/guide/models',
  },
  {
    icon: '📋',
    color: '#7C3AED',
    title: 'FormRequest DTOs',
    desc: 'Parses validation rule arrays into typed DTO interfaces with nested dot-notation objects, wildcard arrays, Enum rule resolution, and Zod compilation.',
    href: '/laravel-typegen/guide/enums-and-requests',
  },
  {
    icon: '🌲',
    color: '#059669',
    title: 'AST-Powered Resources',
    desc: 'Uses nikic/php-parser to statically analyze JsonResource toArray(). Conditional when() / whenLoaded() fields automatically become optional keys.',
    href: '/laravel-typegen/guide/enums-and-requests',
  },
  {
    icon: '🛡',
    color: '#d97706',
    title: 'Type-Safe Routes',
    desc: 'Inspects controller signatures and bound Eloquent models to resolve route param types. Auto-increments → number, UUIDs → string. Ziggy-compatible.',
    href: '/laravel-typegen/guide/routes-and-dx',
  },
  {
    icon: '✅',
    color: '#0891b2',
    title: 'CI --check Flag',
    desc: 'Run --check in GitHub Actions to block stale types from merging. In-memory diff against committed files exits with code 1 on any drift.',
    href: '/laravel-typegen/guide/routes-and-dx',
  },
]

const stats = [
  { value: '54', label: 'Tests passing' },
  { value: '42', label: 'Files analyzed' },
  { value: '8', label: 'Generators' },
  { value: 'MIT', label: 'License' },
]
</script>

<template>
  <!-- Stats strip -->
  <div class="stats-strip">
    <div class="stats-inner">
      <div v-for="s in stats" :key="s.label" class="stat-item">
        <span class="stat-value">{{ s.value }}</span>
        <span class="stat-label">{{ s.label }}</span>
      </div>
    </div>
  </div>

  <!-- Features -->
  <div class="features-section">
    <div class="section-label">Features</div>
    <h2 class="section-heading">Everything you need.<br><span class="heading-muted">Nothing you don't.</span></h2>
    <p class="section-sub">Every common Laravel-to-TypeScript bridge pattern handled out of the box, deeply, without configuration.</p>

    <div class="features-grid">
      <a
        v-for="f in features"
        :key="f.title"
        :href="f.href"
        class="feature-card"
      >
        <div class="feature-icon" :style="{ background: f.color + '18', color: f.color }">
          {{ f.icon }}
        </div>
        <h3 class="feature-title">{{ f.title }}</h3>
        <p class="feature-desc">{{ f.desc }}</p>
        <span class="feature-link">
          Learn more
          <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6h8M7 3l3 3-3 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
      </a>
    </div>
  </div>

  <!-- Quick install strip -->
  <div class="install-strip">
    <div class="install-inner">
      <div class="install-label">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="2" y="3" width="12" height="10" rx="2" stroke="currentColor" stroke-width="1.3"/><path d="M5 7l2 2 4-3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Get started in 30 seconds
      </div>
      <div class="install-commands">
        <div class="install-cmd">
          <span class="cmd-prompt">$</span>
          <code>composer require hemilrajput/laravel-typegen</code>
        </div>
        <div class="install-divider" />
        <div class="install-cmd">
          <span class="cmd-prompt">$</span>
          <code>php artisan typescript:generate</code>
        </div>
      </div>
      <a href="/laravel-typegen/guide/getting-started" class="install-cta">
        Full setup guide →
      </a>
    </div>
  </div>
</template>

<style scoped>
/* ── Stats strip ────────────────────────────────────────────── */
.stats-strip {
  padding: 0 24px 80px;
}

.stats-inner {
  max-width: 1280px;
  margin: 0 auto;
  display: flex;
  gap: 0;
  border: 1px solid var(--vp-c-border);
  border-radius: 14px;
  overflow: hidden;
  background: var(--vp-c-bg-soft);
}

.stat-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 28px 16px;
  gap: 4px;
  border-right: 1px solid var(--vp-c-border);
}

.stat-item:last-child { border-right: none; }

.stat-value {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -1.5px;
  color: var(--vp-c-text-1);
  line-height: 1;
}

.stat-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--vp-c-text-3);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

@media (max-width: 600px) {
  .stats-inner { flex-wrap: wrap; }
  .stat-item { width: 50%; border-bottom: 1px solid var(--vp-c-border); }
  .stat-item:nth-child(2n) { border-right: none; }
  .stat-item:nth-child(3), .stat-item:nth-child(4) { border-bottom: none; }
}

/* ── Features section ───────────────────────────────────────── */
.features-section {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 24px 100px;
}

.section-label {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: #FF2D20;
  margin-bottom: 16px;
}

.section-heading {
  font-size: clamp(2rem, 4vw, 2.8rem);
  font-weight: 800;
  letter-spacing: -2px;
  line-height: 1.1;
  color: var(--vp-c-text-1);
  margin: 0 0 16px;
}

.heading-muted {
  color: var(--vp-c-text-3);
}

.section-sub {
  font-size: 16px;
  color: var(--vp-c-text-2);
  line-height: 1.65;
  max-width: 600px;
  margin: 0 0 56px;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

@media (max-width: 900px) {
  .features-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 600px) {
  .features-grid { grid-template-columns: 1fr; }
}

/* ── Feature card ───────────────────────────────────────────── */
.feature-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 28px;
  background: var(--vp-c-bg-soft);
  border: 1px solid var(--vp-c-border);
  border-radius: 14px;
  text-decoration: none !important;
  transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
  position: relative;
  overflow: hidden;
}

.feature-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,0.02) 0%, transparent 60%);
  pointer-events: none;
}

.feature-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 16px 48px rgba(0,0,0,0.12), 0 4px 12px rgba(0,0,0,0.07);
  border-color: rgba(255, 45, 32, 0.20);
}

.feature-icon {
  width: 44px;
  height: 44px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  flex-shrink: 0;
}

.feature-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--vp-c-text-1);
  letter-spacing: -0.3px;
  line-height: 1.2;
  margin: 0;
}

.feature-desc {
  font-size: 13.5px;
  line-height: 1.65;
  color: var(--vp-c-text-2);
  flex: 1;
  margin: 0;
}

.feature-link {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12.5px;
  font-weight: 600;
  color: #FF2D20;
  margin-top: 4px;
  opacity: 0;
  transform: translateX(-6px);
  transition: opacity 0.18s ease, transform 0.18s ease;
}

.feature-card:hover .feature-link {
  opacity: 1;
  transform: translateX(0);
}

/* ── Install strip ──────────────────────────────────────────── */
.install-strip {
  padding: 0 24px 100px;
}

.install-inner {
  max-width: 1280px;
  margin: 0 auto;
  background: linear-gradient(135deg, rgba(255,45,32,0.06) 0%, rgba(49,120,198,0.06) 100%);
  border: 1px solid var(--vp-c-border);
  border-radius: 16px;
  padding: 36px 40px;
  display: flex;
  align-items: center;
  gap: 32px;
  flex-wrap: wrap;
}

.install-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  color: var(--vp-c-text-1);
  white-space: nowrap;
  flex-shrink: 0;
}

.install-commands {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 16px;
  background: var(--vp-c-bg);
  border: 1px solid var(--vp-c-border);
  border-radius: 10px;
  padding: 14px 20px;
  min-width: 0;
  overflow-x: auto;
  font-family: 'JetBrains Mono', ui-monospace, monospace;
  flex-wrap: wrap;
  gap: 8px;
}

.install-cmd {
  display: flex;
  align-items: center;
  gap: 8px;
}

.cmd-prompt {
  color: #FF2D20;
  font-size: 13px;
  font-weight: 700;
  user-select: none;
}

.install-cmd code {
  font-size: 13px;
  color: var(--vp-c-text-1);
  background: none;
  padding: 0;
  border: none;
  font-family: inherit;
}

.install-divider {
  width: 1px;
  height: 20px;
  background: var(--vp-c-border);
  flex-shrink: 0;
}

.install-cta {
  display: inline-flex;
  align-items: center;
  padding: 10px 20px;
  background: #FF2D20;
  color: #fff !important;
  font-size: 13.5px;
  font-weight: 600;
  border-radius: 9px;
  text-decoration: none !important;
  white-space: nowrap;
  flex-shrink: 0;
  letter-spacing: -0.2px;
  box-shadow: 0 2px 8px rgba(255,45,32,0.30);
  transition: transform 0.18s, filter 0.18s;
}

.install-cta:hover {
  transform: translateY(-1px);
  filter: brightness(1.08);
}

@media (max-width: 768px) {
  .install-inner {
    flex-direction: column;
    align-items: flex-start;
    padding: 28px 24px;
    gap: 20px;
  }
  .install-commands { width: 100%; }
}
</style>
