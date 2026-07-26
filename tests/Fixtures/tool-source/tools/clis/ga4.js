#!/usr/bin/env node

const ACCESS_TOKEN = process.env.GA4_ACCESS_TOKEN

if (!ACCESS_TOKEN) {
  console.error(JSON.stringify({ error: 'GA4_ACCESS_TOKEN environment variable required' }))
  process.exit(1)
}

console.log('ga4 ok: ' + (process.argv[2] || 'no-args') + ' token:' + ACCESS_TOKEN.slice(0, 4))
