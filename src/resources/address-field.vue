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

import Fieldtype from "../../../../../vendor/statamic/cms/resources/js/components/fieldtypes/Fieldtype.vue";

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
      window.axios
        .get(
          `https://nominatim.openstreetmap.org/search?format=json&q=${search}`,
          {
            headers: {
              "Access-Control-Allow-Origin": "*",
              "Content-Type": "application/json",
            },
          }
        )
        .then((response) => {
          const { data } = response;

          const options = data.map((item) => {
            return {
              label: item.display_name,
              ...item,
            };
          });

          vm.options = options;
        })
        .finally(() => {
          loading(false);
        });
    }, 350),
  },
};
</script>
