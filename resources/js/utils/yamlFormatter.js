/**
 * Formats an object as YAML-like syntax with styled values.
 *
 * @param {any} obj - The object to format
 * @param {number} indent - Current indentation level
 * @returns {string} HTML string with YAML formatting
 */
export function formatAsYaml(obj, indent = 0) {
  const data = obj?.value || obj
  if (!data || typeof data !== 'object') return styledValue(data)

  return Object.entries(data)
    .map(([key, value]) => {
      const pad = '  '.repeat(indent)
      if (value && typeof value === 'object' && !Array.isArray(value)) {
        return `${pad}${key}:\n${formatAsYaml(value, indent + 1)}`
      }
      if (Array.isArray(value)) {
        if (value.length === 0) return `${pad}${key}: ${styledValue('[]')}`
        return `${pad}${key}:\n${value.map((v) => `${'  '.repeat(indent + 1)}- ${styledValue(v)}`).join('\n')}`
      }
      return `${pad}${key}: ${styledValue(value)}`
    })
    .join('\n')
}

/**
 * Wraps a value in a styled span for syntax highlighting.
 *
 * @param {any} value - The value to style
 * @returns {string} HTML span element
 */
function styledValue(value) {
  const escaped = String(value ?? '~')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
  return `<span class="yaml-value">${escaped}</span>`
}
