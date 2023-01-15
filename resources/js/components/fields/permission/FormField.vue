<template>
  <default-field :field="field" class="w-full">
    <template v-slot:field class="w-full">
      <div class="grid grid-cols-2 my-11">
        <h2 class="text-xl ml-2">CRUD Permissions</h2>
        <div class="justify-self-end">
          <div
            class="shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 cursor-pointer hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm flex-shrink-0"
            @click="selectAllFieldOptions()"
          >
            {{
              areAllFieldOptionsSelected
                ? __("Deselect All Fields")
                : __("Select All Fields")
            }}
          </div>
        </div>
      </div>
      <table class="table-fixed border-collapse">
        <thead class="bg-gray-100 text-black sticky top-0 z-50">
          <tr class="text-base">
            <th
              v-for="resourceColumn in resourceColumns"
              :key="resourceColumn"
              scope="col"
              class="font-medium px-3 py-4"
            >
              {{ resourceColumn }}
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-for="group in availableGroups" :key="group.name">
            <tr class="border-t border-slate-300">
              <td class="flex items-center py-1 pl-5">
                <checkbox
                  class="mr-1"
                  @input="selectAllGroupedOptions(group.options)"
                  :name="group.name"
                  :checked="isAllGroupedOptionsSelected(group.options)"
                  >&nbsp;</checkbox
                >
                <h2 class="text-base cursor-pointer" @click="setFieldState(group.name)">
                  {{ group.name }}
                </h2>
              </td>

              <td class="" v-for="option in group.options" :key="option.value">
                <div class="flex items-center">
                  <checkbox
                    class="py-2 m-auto"
                    @click="handleChange(option.value)"
                    :name="field.name"
                    :checked="options[option.value]"
                  ></checkbox>
                </div>
              </td>
            </tr>
            <tr class="" v-if="getFieldState(group.name)">
              <td class="px-10 border-b border-slate-300" colspan="12">
                <table class="table-fixed border-collapse min-w-full w-40 my-5">
                  <thead class="bg-gray-100">
                    <tr>
                      <th
                        v-for="(fieldColumn, index) in fieldColumns"
                        :key="fieldColumn"
                        scope="col"
                        class="text-sm font-medium py-2"
                      >
                        <checkbox
                          v-if="index === 0"
                          class="mr-1"
                          @input="selectAllGroupedFields(group.fields)"
                          :name="group.name"
                          :checked="isAllGroupedFieldsSelected(group.fields)"
                        >
                          &nbsp;
                        </checkbox>
                        {{ fieldColumn }}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(option, fieldName) in group.fields"
                      :key="fieldName"
                      class="bg-white border-b"
                    >
                      <td class="text-base py-1.5 pl-5">
                        {{ fieldName }}
                      </td>
                      <td>
                        <div>
                          <checkbox
                            @click="handleChange(option.visible.value)"
                            class="py-2 flex items-center m-auto"
                            :name="field.name"
                            :checked="options[option.visible.value]"
                          ></checkbox>
                        </div>
                      </td>
                      <td>
                        <div>
                          <checkbox
                            @click="handleChange(option.writable.value)"
                            class="py-2 flex items-center m-auto"
                            :name="field.name"
                            :checked="options[option.writable.value]"
                          ></checkbox>
                        </div>
                      </td>
                      <td v-if="option.identifiable">
                        <div>
                          <checkbox
                            @click="handleChange(option.identifiable.value)"
                            class="py-2 flex items-center m-auto"
                            :name="field.name"
                            :checked="options[option.identifiable.value]"
                          ></checkbox>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>

                <div class="mb-7 my-2" v-if="group.actions.length > 0">
                  <div
                    class="bg-gray-100 inline-flex items-center leading-none my-3 px-2 py-2.5 w-full"
                  >
                    Actions
                  </div>

                  <!-- <h4 class="text-lg my-2">Actions</h4> -->
                  <div class="flex items-center ml-5">
                    <div
                      v-for="option in group.actions"
                      :key="option.value"
                      class="flex mb-1 mr-10"
                    >
                      <div>
                        <checkbox
                          @click="handleChange(option.value)"
                          class="py-2 mr-1"
                          :name="field.name"
                          :checked="options[option.value]"
                        ></checkbox>
                        <label :for="field.name" v-text="option.display"></label>
                      </div>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>

      <div class="flex">
        <div>
          <h2 class="text-xl mt-11 mb-7 ml-2">Tool Permissions</h2>
          <div class="ml-5 mb-7 w-72" v-if="tools.length > 0">
            <div class="items-center whitespace-nowrap">
              <div
                v-for="option in tools"
                :key="option.value"
                class="mb-1 mr-10 my-3 text-base"
              >
                <div class="grid grid-cols-2 justify-items-center items-center">
                  <label
                    class="ml-0 mr-auto"
                    :for="field.name"
                    v-text="option.display"
                  ></label>
                  <checkbox
                    @click="handleChange(option.value)"
                    class="mt-1"
                    :name="field.name"
                    :checked="options[option.value]"
                  ></checkbox>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div>
          <h2 class="text-xl mt-11 mb-7 ml-2">Dashboard Permissions</h2>
          <div class="ml-5 mb-7 w-72" v-if="dashboards.length > 0">
            <div class="items-center whitespace-nowrap">
              <div
                v-for="option in dashboards"
                :key="option.value"
                class="mb-1 mr-10 my-3 text-base"
              >
                <div class="grid grid-cols-2 justify-items-center items-center">
                  <label
                    class="ml-0 mr-auto"
                    :for="field.name"
                    v-text="option.display"
                  ></label>
                  <checkbox
                    @click="handleChange(option.value)"
                    class="mt-1"
                    :name="field.name"
                    :checked="options[option.value]"
                  ></checkbox>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </default-field>
</template>

<script>
import { FormField, HandlesValidationErrors, Minimum } from "laravel-nova";

export default {
  mixins: [FormField, HandlesValidationErrors],

  props: ["resourceName", "resourceId", "field"],

  data: () => ({
    fieldsViewStates: {},
    areAllFieldOptionsSelected: false,
    show: false,
    options: [],
    fieldColumns: ["Fields", "Show", "Edit", "Identify User"],
    resourceColumns: [
      "Resource",
      "Create",
      "View",
      "View Any",
      "Update",
      "Update Any",
      "Delete",
      "Delete Any",
      "Force Delete",
      "Restore",
      "Attach",
      "Detach",
    ],
    tools: [],
    dashboards: [],
  }),

  computed: {
    availableGroups() {
      var groups = {};
      this.field.options.forEach((option) => {
        if (!groups[option.resource]) {
          groups[option.resource] = {
            name: option.resource,
            options: [],
            actions: [],
            fields: {},
          };
        }
        if (option.action) {
          groups[option.resource].actions.push(option);
        } else if (option.field) {
          let fieldNameExists = groups[option.resource].fields.hasOwnProperty(
            option.display
          );
          if (!fieldNameExists) {
            groups[option.resource].fields[option.display] = {
              visible: "",
              writable: "",
              identifiable: null,
            };
          }
          groups[option.resource].fields[option.display][option.fieldType] = option;
        } else if (option.resource === "Tools") {
          this.tools.push(option);
          delete groups[option.resource];
        } else if (option.resource === "Nova Dashboard") {
          this.dashboards.push(option);
          delete groups[option.resource];
        } else {
          groups[option.resource].options.push(option);
        }
      });

      let groupsArray = _.toArray(groups);
      let fieldsViewStates = {};
      _.map(groupsArray, function (group) {
        fieldsViewStates[group.name] = false;
      });
      this.fieldsViewStates = fieldsViewStates;

      return groupsArray;
    },
  },
  methods: {
    getFieldState(groupName) {
      return this.fieldsViewStates[groupName];
    },
    setFieldState(groupName) {
      this.fieldsViewStates[groupName] = !this.fieldsViewStates[groupName];
    },
    /*
     * Set the initial, internal value for the field.
     */
    setInitialValue() {
      this.value = this.field.value || "";
      this.$nextTick(() => {
        this.options = this.value ? JSON.parse(this.value) : [];
      });
    },

    /**
     * Fill the given FormData object with the field's internal value.
     */
    fill(formData) {
      formData.append(this.field.attribute, this.value || "");
    },

    /**
     * Update the field's internal value.
     */
    handleChange(key) {
      this.options[key] = !this.options[key];
    },

    isAllGroupedFieldsSelected(fields) {
      let _this = this;
      let result = true;
      _.each(fields, function (field) {
        if (
          _this.options[field.visible.value] == false &&
          _this.options[field.writable.value] == false
        ) {
          if (field.identifiable && _this.options[field.identifiable.value] == true) {
            return;
          }
          result = false;
          return false;
        }
      });
      return result;
    },
    selectAllGroupedFields(fields) {
      let _this = this;
      if (this.isAllGroupedFieldsSelected(fields)) {
        _.each(fields, function (field) {
          _this.options[field.visible.value] = false;
          _this.options[field.writable.value] = false;

          if (field.identifiable) {
            _this.options[field.identifiable.value] = false;
          }
        });
        return;
      }
      _.each(fields, function (field) {
        _this.options[field.visible.value] = true;
        _this.options[field.writable.value] = true;

        if (field.identifiable) {
          _this.options[field.identifiable.value] = true;
        }
      });
    },
    isAllGroupedOptionsSelected(options) {
      let _this = this;
      let result = true;
      _.each(options, function (option) {
        if (_this.options[option.value] == false) {
          result = false;
          return false;
        }
      });
      return result;
    },
    selectAllGroupedOptions(options) {
      let _this = this;
      if (this.isAllGroupedOptionsSelected(options)) {
        _.each(options, function (option) {
          _this.options[option.value] = false;
        });
        return;
      }
      _.each(options, function (option) {
        _this.options[option.value] = true;
      });
    },

    selectAllFieldOptions() {
      let _this = this;
      if (!this.areAllFieldOptionsSelected) {
        this.availableGroups.forEach(function (group) {
          return _.forOwn(group.fields, function (option, fieldName) {
            _this.options[option.visible.value] = true;
            _this.options[option.writable.value] = true;
            if (option.identifiable) {
              _this.options[option.identifiable.value] = true;
            }
          });
        });
        return (this.areAllFieldOptionsSelected = true);
      }
      this.availableGroups.forEach(function (group) {
        return _.forOwn(group.fields, function (option, fieldName) {
          _this.options[option.visible.value] = false;
          _this.options[option.writable.value] = false;
          if (option.identifiable) {
            _this.options[option.identifiable.value] = false;
          }
        });
      });
      return (this.areAllFieldOptionsSelected = false);
    },
  },

  watch: {
    options: {
      handler: function (newOptions) {
        this.value = JSON.stringify(newOptions);
      },
      deep: true,
    },
  },
  beforeUnmount() {
    localStorage.setItem(
      "permission.resourceAccordionState",
      JSON.stringify(this.fieldsViewStates)
    );
  },

  mounted() {
    if (localStorage.getItem("permission.resourceAccordionState")) {
      this.fieldsViewStates = JSON.parse(
        localStorage.getItem("permission.resourceAccordionState")
      );
    }
  },
};
</script>

<style>
fieldset {
  border: 1px groove;
}
</style>
