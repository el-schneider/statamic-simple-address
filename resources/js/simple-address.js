import AddressField from "./address-field.vue";

Statamic.booting(() => {
    Statamic.$components.register("simple_address-fieldtype", AddressField);
});


