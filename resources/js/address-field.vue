<template>
  <div>
    <v-select
      ref="select"
      :value="value"
      :filterable="false"
      :options="options"
      :placeholder="config.placeholder"
      append-to-body
      :no-drop="!options.length"
      @search="onSearch"
      @input="setSelected"
    >
      <template slot="option" slot-scope="option">
        <div class="d-center">
          {{ option.label }}
        </div>
      </template>
      <template slot="selected-option" slot-scope="option">
        <div class="selected d-center">
          {{ option.label }}
        </div>
      </template>
    </v-select>
  </div>
</template>

<script>
import vSelect from 'vue-select'
import debounce from 'lodash.debounce'

// TODO: Figure out how to remove this stuff...
vSelect.props.components.default = () => ({
  Deselect: {
    render: (createElement) => createElement('span', __('×')),
  },
  OpenIndicator: {
    render: (createElement) =>
      createElement('span', {
        class: { toggle: true },
        domProps: {
          innerHTML:
            '<svg xmlns="http://www.w3.org/2000/svg" height="16" width="16" viewBox="0 0 20 20"><path fill="currentColor" d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>',
        },
      }),
  },
})

export default {
  components: {
    vSelect,
  },
  mixins: [Fieldtype],
  data() {
    return {
      options: [],
    }
  },
  methods: {
    setSelected(value) {
      this.update(value)
    },
    onSearch(search, loading) {
      if (search.length) {
        loading(true)
        this.search(loading, search, this)
      }
    },
    search: debounce((loading, search, vm) => {
      const { countries, language, exclude_fields } = vm.config

      const options = {
        addressdetails: 1,
        namedetails: 1,
        format: 'json',
        'accept-language': language || 'en',
      }

      if (countries) {
        options.countrycodes = countries.join(',')
      }

      const params = new URLSearchParams(options)

      const url = `https://nominatim.openstreetmap.org/search?${params}&q=${search}`

      fetch(url, {
        headers: {
          'Access-Control-Allow-Origin': '*',
          'Content-Type': 'application/json',
        },
      })
        .then((response) => response.json())
        .then((data) => {
          const options = data.map((_item) => {
            const item = { label: _item.display_name, ..._item }

            exclude_fields.forEach((field) => {
              //check if field exists and remove it
              if (item[field]) delete item[field]
            })

            for (const key in item.namedetails) {
              //if key contains a colon remove it
              if (key.indexOf(':') > -1) {
                delete item.namedetails[key]
              }
            }

            return item
          })

          vm.options = options
        })
        .catch((error) => console.error(error))
        .finally(() => {
          loading(false)
        })
    }, 300),
  },
}
</script>
