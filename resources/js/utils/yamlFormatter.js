export function formatAsYaml(obj, indent = 0) {
  if (!obj || typeof obj !== 'object') return styledValue(obj)

  const pad = '  '.repeat(indent)

  return Object.entries(obj)
    .map(([key, value]) => {
      if (Array.isArray(value)) {
        const items = value.length ? value.map((v) => `${pad}  - ${styledValue(v)}`).join('\n') : styledValue('[]')
        return `${pad}${key}:\n${items}`
      }
      if (value && typeof value === 'object') {
        return `${pad}${key}:\n${formatAsYaml(value, indent + 1)}`
      }
      return `${pad}${key}: ${styledValue(value)}`
    })
    .join('\n')
}

function styledValue(value) {
  const escaped = String(value ?? '~')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
  return `<span class="text-blue-600 dark:text-blue-400">${escaped}</span>`
}
