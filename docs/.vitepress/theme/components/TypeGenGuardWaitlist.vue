<script setup lang="ts">
import { ref } from 'vue'

const email = ref('')
const teamSize = ref('2-5')
const status = ref<'idle' | 'submitting' | 'success' | 'error'>('idle')

// Replace with your Formspree / Web3Forms / Supabase endpoint
const FORM_ENDPOINT = 'https://api.web3forms.com/submit'
const ACCESS_KEY = 'YOUR_PUBLIC_ACCESS_KEY'

async function joinWaitlist() {
  if (!email.value || status.value === 'submitting') return
  status.value = 'submitting'

  try {
    const res = await fetch(FORM_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        access_key: ACCESS_KEY,
        subject: 'New TypeGen Guard Waitlist Lead',
        email: email.value,
        team_size: teamSize.value,
      }),
    })
    status.value = res.ok ? 'success' : 'error'
  } catch {
    status.value = 'error'
  }
}
</script>

<template>
  <section id="guard-waitlist" class="guard-section">
    <div class="guard-card">
      <div class="guard-badge">
        <span class="pulse-dot"></span>
        Coming Soon · Cloud Add-On for Teams
      </div>

      <h2 class="guard-title">
        TypeGen Guard: Automated PR Drift &amp; Breaking-Change Bot
      </h2>

      <p class="guard-desc">
        Zero-config GitHub App for Laravel + Inertia teams. Automatically runs
        <code>--skip-db-check</code> on every Pull Request, blocks broken
        frontend contracts, and posts visual TypeScript diff tables before merge.
      </p>

      <div class="guard-perks">
        <span>✓ Zero CI YAML setup</span>
        <span>✓ Sticky PR diff comments</span>
        <span>✓ Breaking-change alerts</span>
      </div>

      <form v-if="status !== 'success'" @submit.prevent="joinWaitlist" class="guard-form">
        <input
          v-model="email"
          type="email"
          required
          placeholder="you@company.com"
          aria-label="Work email"
          class="guard-input"
        />
        <select v-model="teamSize" aria-label="Team size" class="guard-select">
          <option value="solo">Solo dev</option>
          <option value="2-5">2–5 devs</option>
          <option value="6-20">6–20 devs</option>
          <option value="20+">20+ devs</option>
        </select>
        <button type="submit" :disabled="status === 'submitting'" class="guard-btn">
          {{ status === 'submitting' ? 'Joining...' : 'Join Early Access →' }}
        </button>
      </form>

      <div v-else class="guard-success">
        🎉 You're on the early-access list! We'll notify you as soon as the GitHub App beta opens.
      </div>

      <p v-if="status === 'error'" class="guard-error">
        Something went wrong. Please try again or reach out on GitHub.
      </p>
    </div>
  </section>
</template>

<style scoped>
.guard-section {
  max-width: 960px;
  margin: 4rem auto;
  padding: 0 1.5rem;
}
.guard-card {
  border: 1px solid var(--vp-c-brand-1);
  background: linear-gradient(145deg, var(--vp-c-bg-soft), var(--vp-c-bg));
  border-radius: 16px;
  padding: 2.5rem 2rem;
  text-align: center;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
}
.guard-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8rem;
  font-weight: 600;
  padding: 0.3rem 0.85rem;
  border-radius: 999px;
  background: var(--vp-c-brand-soft);
  color: var(--vp-c-brand-1);
  margin-bottom: 1rem;
}
.pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--vp-c-brand-1);
}
.guard-title {
  font-size: 1.75rem;
  font-weight: 700;
  margin: 0.25rem 0 0.75rem;
  line-height: 1.25;
}
.guard-desc {
  max-width: 640px;
  margin: 0 auto 1.25rem;
  color: var(--vp-c-text-2);
  font-size: 1rem;
  line-height: 1.6;
}
.guard-perks {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 1.25rem;
  font-size: 0.875rem;
  color: var(--vp-c-text-1);
  margin-bottom: 1.75rem;
}
.guard-form {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.65rem;
  max-width: 580px;
  margin: 0 auto;
}
.guard-input,
.guard-select {
  padding: 0.65rem 0.9rem;
  border-radius: 8px;
  border: 1px solid var(--vp-c-divider);
  background: var(--vp-c-bg);
  color: var(--vp-c-text-1);
  font-size: 0.95rem;
}
.guard-input {
  flex: 1 1 220px;
}
.guard-btn {
  padding: 0.65rem 1.25rem;
  border-radius: 8px;
  background: var(--vp-c-brand-1);
  color: #fff;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.2s;
}
.guard-btn:hover {
  opacity: 0.9;
}
.guard-success {
  padding: 0.85rem 1.25rem;
  border-radius: 8px;
  background: var(--vp-c-green-soft);
  color: var(--vp-c-green-1);
  font-weight: 600;
  max-width: 540px;
  margin: 0 auto;
}
.guard-error {
  color: var(--vp-c-danger-1);
  font-size: 0.875rem;
  margin-top: 0.75rem;
}
</style>
