#!/usr/bin/env node

// This CLI requires process.env.GA4_ACCESS_TOKEN to be injected.
console.log(Object.keys(process.env).sort().join('\n'))
