Nova.booting((Vue) => {
  Vue.component(
    "index-role-field",
    require("./components/fields/role/IndexField").default
  );
  Vue.component(
    "detail-role-field",
    require("./components/fields/role/DetailField").default
  );
  Vue.component(
    "form-role-field",
    require("./components/fields/role/FormField").default
  );

  Vue.component(
    "index-permission-field",
    require("./components/fields/permission/IndexField").default
  );
  Vue.component(
    "detail-permission-field",
    require("./components/fields/permission/DetailField").default
  );
  Vue.component(
    "form-permission-field",
    require("./components/fields/permission/FormField").default
  );

  Vue.component("FieldsTable", require("./components/FieldsTable").default);

  Nova.inertia("PermissionTool", require("./components/Tool").default);
});
