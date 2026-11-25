import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Kinlore',
  description: 'A self-hosted family photo journal. One family per server.',
  lang: 'en-US',
  base: process.env.DOCS_BASE ?? '/',
  cleanUrls: true,
  lastUpdated: true,
  themeConfig: {
    nav: [
      { text: 'Guide', link: '/guide/what-is-kinlore' },
      { text: 'Self-hosting', link: '/self-hosting/requirements' },
      { text: 'App', link: '/app/install' },
      { text: 'Tutorials', link: '/tutorials/move-or-restore-a-server' },
      { text: 'Reference', link: '/reference/api' },
      { text: 'FAQ', link: '/faq' },
    ],
    sidebar: [
      {
        text: 'Guide',
        items: [
          { text: 'What is Kinlore?', link: '/guide/what-is-kinlore' },
          { text: 'Quick start', link: '/guide/quick-start' },
        ],
      },
      {
        text: 'Self-hosting',
        items: [
          { text: 'Requirements', link: '/self-hosting/requirements' },
          { text: 'Install with Docker', link: '/self-hosting/docker' },
          { text: 'Install without Docker', link: '/self-hosting/bare-metal' },
          { text: 'Create the first admin', link: '/self-hosting/first-admin' },
          { text: 'Configuration', link: '/self-hosting/configuration' },
          { text: 'HTTPS and reverse proxy', link: '/self-hosting/https-and-reverse-proxy' },
          { text: 'Backup and restore', link: '/self-hosting/backup-and-restore' },
          { text: 'Updating', link: '/self-hosting/updating' },
          { text: 'Maintenance', link: '/self-hosting/maintenance' },
          { text: 'Security', link: '/self-hosting/security' },
          { text: 'Troubleshooting', link: '/self-hosting/troubleshooting' },
        ],
      },
      {
        text: 'Mobile app',
        items: [
          { text: 'Install the app', link: '/app/install' },
          { text: 'Connect to a server', link: '/app/connect' },
          { text: 'Children', link: '/app/children' },
          { text: 'Moments', link: '/app/moments' },
          { text: 'Timeline and look-back', link: '/app/timeline-and-look-back' },
          { text: 'Family and permissions', link: '/app/family-and-permissions' },
          { text: 'Offline use and uploads', link: '/app/offline-and-uploads' },
          { text: 'Several servers', link: '/app/multiple-servers' },
          { text: 'Settings', link: '/app/settings' },
          { text: 'Export', link: '/app/export' },
          { text: 'Troubleshooting', link: '/app/troubleshooting' },
        ],
      },
      {
        text: 'Tutorials',
        items: [
          { text: 'Invite your family', link: '/tutorials/invite-your-family' },
          { text: 'Upload your phone photos', link: '/tutorials/upload-your-phone-photos' },
          { text: 'Move or restore a server', link: '/tutorials/move-or-restore-a-server' },
        ],
      },
      {
        text: 'Reference',
        items: [
          { text: 'API rules', link: '/reference/api' },
          { text: 'Architecture', link: '/reference/architecture' },
          { text: 'Commands', link: '/reference/commands' },
        ],
      },
      {
        text: 'Project',
        items: [
          { text: 'FAQ', link: '/faq' },
          { text: 'Roadmap', link: '/project/roadmap' },
          { text: 'Contributing', link: '/project/contributing' },
          { text: 'License', link: '/project/license' },
        ],
      },
    ],
    socialLinks: [{ icon: 'github', link: 'https://github.com/Kinlore/kinlore-server' }],
    search: { provider: 'local' },
    editLink: {
      pattern: 'https://github.com/Kinlore/kinlore-server/edit/develop/docs/:path',
      text: 'Edit this page on GitHub',
    },
    footer: {
      message: 'Released under the GNU Affero General Public License, version 3.',
      copyright: 'Copyright (C) 2025-2026 the Kinlore contributors',
    },
  },
})
