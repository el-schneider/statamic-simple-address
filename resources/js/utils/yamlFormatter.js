export function formatAsYaml(obj, indent = 0) {
  if (!obj || typeof obj !== 'object') return styledValue(obj)

  const pad = '  '.repeat(indent)

  return Object.entries(obj)
    .map(([key, value]) => {
      if (Array.isArray(value)) {
        const items = value.length ? value.map((v) => `${pad}  - ${styledValue(v)}`).join('\n') : styledValue('[]')
        return `${pad}${styledKey(key)}\n${items}`
      }
      if (value && typeof value === 'object') {
        return `${pad}${styledKey(key)}\n${formatAsYaml(value, indent + 1)}`
      }
      return `${pad}${styledKey(key)} ${styledValue(value)}`
    })
    .join('\n')
}

function styledKey(key) {
  return `<span>${key}:</span>`
}

function styledValue(value) {
  return String(value ?? '~')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
}
