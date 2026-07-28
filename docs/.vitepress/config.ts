import { defineConfig } from 'vitepress';

export default defineConfig({
  base: '/laravel-typegen/',
  title: 'Laravel TypeGen',
  description: 'Generate TypeScript types directly from your Eloquent models, Enums, FormRequests & API Resources. Zero config. Instant sync.',
  head: [
    ['link', { rel: 'preconnect', href: 'https://fonts.googleapis.com' }],
    ['link', { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' }],
    ['meta', { name: 'theme-color', content: '#FF2D20' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'Laravel TypeGen — TypeScript types, direct from Laravel' }],
    ['meta', { property: 'og:description', content: 'One Artisan command turns your Eloquent models, Enums, FormRequests and API Resources into fully typed TypeScript files.' }],
    ['meta', { name: 'twitter:card', content: 'summary_large_image' }],
  ],
  themeConfig: {
    logo: '/logo.svg',
    siteTitle: 'Laravel TypeGen',

    nav: [
      { text: 'Guide', link: '/guide/getting-started', activeMatch: '/guide/' },
      { text: 'Recipes', link: '/recipes/inertia', activeMatch: '/recipes/' },
      {
        text: 'v2.2.3',
        items: [
          { text: 'Changelog', link: 'https://github.com/hemilrajput/laravel-typegen/blob/main/CHANGELOG.md' },
          { text: 'NPM Helpers', link: 'https://www.npmjs.com/package/@hemilrajput/laravel-typegen-helpers' },
        ]
      },
    ],

    sidebar: [
      {
        text: '🚀 Getting Started',
        items: [
          { text: 'Introduction', link: '/guide/getting-started' },
          { text: 'Migration from Spatie', link: '/guide/migration' },
        ]
      },
      {
        text: '⚡ Core Features',
        items: [
          { text: 'Eloquent Models', link: '/guide/models' },
          { text: 'Enums, Requests & Resources', link: '/guide/enums-and-requests' },
          { text: 'Route Parameters & DX', link: '/guide/routes-and-dx' },
        ]
      },
      {
        text: '🔌 Ecosystem & Integration',
        items: [
          { text: 'Inertia Setup (React/Vue)', link: '/recipes/inertia' },
        ]
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/hemilrajput/laravel-typegen' },
      { icon: 'npm', link: 'https://www.npmjs.com/package/@hemilrajput/laravel-typegen-helpers' },
    ],

    search: {
      provider: 'local',
      options: {
        detailedView: true,
        placeholder: 'Search documentation…',
      }
    },

    editLink: {
      pattern: 'https://github.com/hemilrajput/laravel-typegen/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },

    lastUpdated: {
      text: 'Last updated',
      formatOptions: {
        dateStyle: 'medium',
      }
    },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2024 Hemil Rajput',
    },

    docFooter: {
      prev: '← Previous',
      next: 'Next →',
    },
  },

  markdown: {
    lineNumbers: true,
    theme: {
      light: 'github-light',
      dark: 'github-dark-dimmed',
    },
  },
});
