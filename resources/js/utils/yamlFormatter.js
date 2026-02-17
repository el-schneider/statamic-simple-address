export function formatAsYaml(obj, indent = 0) {
  if (!obj || typeof obj !== 'object') return formatValue(obj)

  const pad = '  '.repeat(indent)

  return Object.entries(obj)
    .map(([key, value]) => {
      if (Array.isArray(value)) {
        const items = value.length ? value.map((v) => `${pad}  - ${formatValue(v)}`).join('\n') : formatValue('[]')
        return `${pad}${formatKey(key)}\n${items}`
      }
      if (value && typeof value === 'object') {
        return `${pad}${formatKey(key)}\n${formatAsYaml(value, indent + 1)}`
      }
      return `${pad}${formatKey(key)} ${formatValue(value)}`
    })
    .join('\n')
}

function formatKey(key) {
  return `<span>${key}:</span>`
}

function formatValue(value) {
  return String(value ?? '~')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
}
