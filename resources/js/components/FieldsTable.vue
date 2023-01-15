<template>
  <div>
    <!-- <div class="ml-5 mb-7 my-2" v-if="group.actions.length > 0">
      <div class="flex items-center">
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
    </div> -->

    <!-- <table class="table-fixed min-w-full w-40">
      <thead class="bg-gray-100">
        <tr>
          <th
            v-for="fieldColumn in fieldColumns"
            :key="fieldColumn"
            scope="col"
            class="text-sm font-medium py-4"
          >
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
                @click="handleChange(option.hidden.value)"
                class="py-2 flex items-center m-auto"
                :name="field.name"
                :checked="options[option.hidden.value]"
              ></checkbox>
            </div>
          </td>
          <td>
            <div>
              <checkbox
                @click="handleChange(option.readonly.value)"
                class="py-2 flex items-center m-auto"
                :name="field.name"
                :checked="options[option.readonly.value]"
              ></checkbox>
            </div>
          </td>
        </tr>
      </tbody>
    </table> -->
  </div>
</template>


<script>
export default {
  props: ["group", 'field'],
  data() {
    return {
      show: false,
    };
  },

  methods: {
    toggleView() {
      this.show = !this.show;
    },
  },

  // mounted() {
  //   this.$el.parentElement.parentElement.classList.add('w-full')
  //   this.$parent.$parent.$parent.$el.parentElement.classList.add('w-max')
  // }
};
</script>