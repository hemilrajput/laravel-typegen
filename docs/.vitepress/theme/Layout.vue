<script setup lang="ts">
import DefaultTheme from 'vitepress/theme'
import HomeHero from './HomeHero.vue'
import HomeFeatures from './HomeFeatures.vue'

const { Layout } = DefaultTheme
</script>

<template>
  <Layout>
    <template #home-hero-before>
      <HomeHero />
    </template>
    <template #home-features-before>
      <HomeFeatures />
    </template>
  </Layout>
</template>
