import { defineConfig } from 'vitepress';

export default defineConfig({
  title: 'Laravel TypeGen',
  description: 'Generate TypeScript types from Eloquent models, Enums & FormRequests.',
  themeConfig: {
    nav: [
      { text: 'Guide', link: '/guide/getting-started' },
      { text: 'Recipes', link: '/recipes/inertia' },
    ],
    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Introduction', link: '/guide/getting-started' },
          { text: 'Migration from Spatie', link: '/guide/migration' },
        ]
      },
      {
        text: 'Core Features',
        items: [
          { text: 'Eloquent Models', link: '/guide/models' },
          { text: 'Enums & Form Requests', link: '/guide/enums-and-requests' },
          { text: 'Route Parameters & DX', link: '/guide/routes-and-dx' },
        ]
      },
      {
        text: 'Ecosystem & Integration',
        items: [
          { text: 'Inertia Setup (React/Vue)', link: '/recipes/inertia' },
        ]
      }
    ],
    socialLinks: [
      { icon: 'github', link: 'https://github.com/hemilrajput/laravel-typegen' }
    ]
  }
});
