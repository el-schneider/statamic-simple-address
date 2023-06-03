import Vue from "vue";
import AddressField from "./address-field.vue";
import 'vue-select/dist/vue-select.css';
// Statamic.booting(() => {
//     Statamic.$components.register("address-fieldtype", AddressField);
// });


new Vue({
    render: h => h(AddressField),
    el: "#app",
});
