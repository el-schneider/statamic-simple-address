<template>
  <div>
    <v-select
      :value="value"
      :filterable="false"
      :options="options"
      :placeholder="config.placeholder"
      @search="onSearch"
      @input="setSelected"
    >
      <template slot="no-options">
        <div class="py-2 text-grey-60">
          <p>Start typing address...</p>
        </div>
      </template>
      <template slot="option" slot-scope="option">
        <div class="d-center">
          {{ option.display_name }}
        </div>
      </template>
      <template slot="selected-option" slot-scope="option">
        <div class="selected d-center">
          {{ option.display_name }}
        </div>
      </template>
    </v-select>
  </div>
</template>

<script>
import vSelect from "vue-select";
import debounce from "lodash.debounce";

// TODO: Figure out how to remove this stuff...
vSelect.props.components.default = () => ({
  Deselect: {
    render: (createElement) => createElement("span", __("×")),
  },
  OpenIndicator: {
    render: (createElement) =>
      createElement("span", {
        class: { toggle: true },
        domProps: {
          innerHTML:
            '<svg xmlns="http://www.w3.org/2000/svg" height="16" width="16" viewBox="0 0 20 20"><path fill="currentColor" d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>',
        },
      }),
  },
});

export default {
  mixins: [Fieldtype],

  components: {
    vSelect,
  },
  data() {
    return {
      options: [],
    };
  },
  methods: {
    setSelected(value) {
      this.update(value);
    },
    onSearch(search, loading) {
      if (search.length) {
        loading(true);
        this.search(loading, search, this);
      }
    },
    search: debounce((loading, search, vm) => {
      const { countries, language } = vm.config;

      const options = {
        addressdetails: 1,
        namedetails: 1,
        format: "json",
        "accept-language": language || "en",
      };

      if (countries) {
        options.countrycodes = countries.join(",");
      }

      const params = new URLSearchParams(options);

      const url = `https://nominatim.openstreetmap.org/search?${params}&q=${search}`;

      fetch(url, {
        headers: {
          "Access-Control-Allow-Origin": "*",
          "Content-Type": "application/json",
        },
      })
        .then((response) => response.json())
        .then((data) => {
          console.log("data[0]:", data[0]);

          const options = data.map((item) => {
            const fieldsToRemove = ["importance", "icon"];

            fieldsToRemove.forEach((field) => {
              delete item[field];
            });

            return {
              label: item.display_name,
              ...item,
            };
          });

          vm.options = options;
        })
        .catch((error) => console.error(error))
        .finally(() => {
          loading(false);
        });
    }, 300),
  },
};
</script>