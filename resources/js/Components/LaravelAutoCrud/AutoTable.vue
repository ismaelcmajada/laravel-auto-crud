<script setup>
import AutoFormDialog from "./AutoFormDialog.vue"
import AutoForm from "./AutoForm.vue"
import AutoExternalRelation from "./AutoExternalRelation.vue"
import DestroyDialog from "./DestroyDialog.vue"
import RestoreDialog from "./RestoreDialog.vue"
import DestroyPermanentDialog from "./DestroyPermanentDialog.vue"
import LoadingOverlay from "./LoadingOverlay.vue"
import useTableServer from "../../Composables/LaravelAutoCrud/useTableServer"
import useDialogs from "../../Composables/LaravelAutoCrud/useDialogs"
import HistoryDialog from "./HistoryDialog.vue"
import { usePage } from "@inertiajs/vue3"
import { useDisplay } from "vuetify"
import { computed, watch, ref } from "vue"
import { generateItemTitle } from "../../Utils/LaravelAutoCrud/datatableUtils"

const page = usePage()

const { mobile } = useDisplay()

const props = defineProps([
  "title",
  "model",
  "customFilters",
  "filteredItems",
  "search",
  "orderBy",
  "customItemProps",
  "itemsPerPage",
  "itemsPerPageOptions",
  "customHeaders",
])

const emit = defineEmits([
  "closeDialog",
  "openDialog",
  "formChange",
  "update:item",
])

const model = computed(() => {
  return props.model
})

const finalHeaders = computed(() => {
  const originalHeaders = [...model.value.tableHeaders]

  // Buscar el índice de la columna con key 'actions'
  const actionsIndex = originalHeaders.findIndex((h) => h.key === "actions")

  // Asegurar que customHeaders es array
  const extraHeaders = Array.isArray(props.customHeaders)
    ? props.customHeaders
    : []

  if (actionsIndex === -1) {
    // Si no se encuentra 'actions', simplemente concatena al final
    return [...originalHeaders, ...extraHeaders]
  } else {
    // Insertar justo antes de la columna 'actions'
    return [
      ...originalHeaders.slice(0, actionsIndex),
      ...extraHeaders,
      ...originalHeaders.slice(actionsIndex),
    ]
  }
})

const forbiddenActions =
  model.value.forbiddenActions[page.props.auth.user.role] ?? []

const {
  endPoint,
  loading,
  itemsPerPageOptions,
  updateItems,
  tableData,
  loadItems,
  resetTable,
} = useTableServer()

tableData.search = props.search ?? {}

tableData.orderBy = props.orderBy ?? []

watch(
  props,
  () => {
    tableData.search = props.search ?? {}
    tableData.orderBy = props.orderBy ?? []
    loadItems()
  },
  { deep: true }
)

const {
  showFormDialog,
  formDialogType,
  showDestroyDialog,
  showRestoreDialog,
  showDestroyPermanentDialog,
  item,
  openDialog,
} = useDialogs()

const title = props.title
endPoint.value = model.value.endPoint

watch(showFormDialog, (value) => {
  if (!value) {
    loadItems()
    emit("closeDialog")
  } else {
    emit("openDialog")
  }
})

function getValueByNestedKey(obj, key) {
  return key.split(".").reduce((currentObject, keyPart) => {
    return currentObject ? currentObject[keyPart] : undefined
  }, obj)
}

const showHistoryDialog = ref(false)

const openHistoryDialog = (historyItem) => {
  item.value = historyItem
  showHistoryDialog.value = true
}

if (props.itemsPerPageOptions)
  itemsPerPageOptions.value = props.itemsPerPageOptions

if (props.itemsPerPage) tableData.itemsPerPage = props.itemsPerPage

watch(item, (value) => {
  emit("update:item", value)
})
</script>

<template>
  <slot
    name="auto-form-dialog"
    :show="showFormDialog"
    :type="formDialogType"
    :item="item"
    :filteredItems="props.filteredItems"
    :customFilters="props.customFilters"
    :customItemProps="props.customItemProps"
    :model="model"
  >
    <auto-form-dialog
      v-model:show="showFormDialog"
      v-model:type="formDialogType"
      v-model:item="item"
      :filteredItems="props.filteredItems"
      :customFilters="props.customFilters"
      :customItemProps="props.customItemProps"
      :model="model"
      @formChange="emit('formChange', $event)"
    >
      <template #prepend>
        <slot
          name="auto-form-dialog.prepend"
          :model="model"
          :type="formDialogType"
          :item="item"
          :show="showFormDialog"
        ></slot>
      </template>
      <template #auto-form="{ handleIsFormDirty }">
        <slot
          name="auto-form-dialog.auto-form"
          :model="model"
          :type="formDialogType"
          :item="item"
        >
          <auto-form
            :model="model"
            v-model:type="formDialogType"
            v-model:item="item"
            :customFilters="props.customFilters"
            :filteredItems="props.filteredItems"
            :customItemProps="props.customItemProps"
            @formChange="emit('formChange', $event)"
            @isDirty="handleIsFormDirty($event)"
          >
            <template #prepend="slotProps">
              <slot
                name="auto-form-dialog.auto-form.prepend"
                v-bind="slotProps"
              >
              </slot>
            </template>

            <template
              v-for="field in model.formFields"
              :key="field.field"
              #[`field.${field.field}`]="fieldSlotProps"
            >
              <!-- 
                Reexponemos un slot llamado:
                "auto-form-dialog.auto-form.field.nombreCampo"

                Si el padre lo define, se inyectará aquí.
                De lo contrario, AutoForm.vue mostrará su fallback.
              -->
              <slot
                :name="`auto-form-dialog.auto-form.field.${field.field}`"
                v-bind="fieldSlotProps"
              >
                <!-- No ponemos fallback aquí, porque el fallback
                     está en <AutoForm> mismo, en su <slot :name="field.xxx"> -->
              </slot>
            </template>

            <template #append="slotProps">
              <slot name="auto-form-dialog.auto-form.append" v-bind="slotProps">
              </slot>
            </template>

            <template
              v-for="relation in model.externalRelations"
              :key="relation.relation"
              v-slot:[`auto-external-relation.${relation.relation}.actions`]="{
                item,
              }"
            >
              <slot
                :name="`auto-external-relation.${relation.relation}.actions`"
                :item="item"
              ></slot>
            </template>
          </auto-form>
        </slot>
      </template>
      <template #append>
        <slot
          name="auto-form-dialog.append"
          :model="model"
          :type="formDialogType"
          :item="item"
          :show="showFormDialog"
        ></slot>
      </template>
    </auto-form-dialog>
  </slot>

  <history-dialog
    :show="showHistoryDialog"
    @closeDialog="showHistoryDialog = false"
    :records="item?.records"
  />

  <destroy-dialog
    :show="showDestroyDialog"
    @closeDialog="showDestroyDialog = false"
    @reloadItems="loadItems()"
    :item="item"
    :endPoint="endPoint"
  />

  <restore-dialog
    :show="showRestoreDialog"
    @closeDialog="showRestoreDialog = false"
    @reloadItems="loadItems()"
    :item="item"
    :endPoint="endPoint"
  />

  <destroy-permanent-dialog
    :show="showDestroyPermanentDialog"
    @closeDialog="showDestroyPermanentDialog = false"
    @reloadItems="loadItems()"
    :item="item"
    :endPoint="endPoint"
  />

  <v-card elevation="6" class="ma-5" variant="outlined">
    <v-data-table-server
      multi-sort
      :loading="loading"
      :headers="finalHeaders"
      :items="tableData.items"
      :items-length="tableData.itemsLength"
      :items-per-page-options="itemsPerPageOptions"
      v-model:page="tableData.page"
      v-model:sort-by="tableData.sortBy"
      v-model:items-per-page="tableData.itemsPerPage"
      @update:options="loadItems()"
    >
      <template v-slot:top>
        <v-toolbar :class="{ 'bg-red-lighten-2': tableData.deleted }" flat>
          <v-toolbar-title>
            <span>{{ title }}</span>
          </v-toolbar-title>
          <v-divider class="mx-4" inset vertical></v-divider>
          <slot
            name="table.actions.prepend"
            :openDialog="openDialog"
            :resetTable="resetTable"
            :tableData="tableData"
            :loadItems="loadItems"
          ></slot>
          <slot
            name="table.actions"
            :openDialog="openDialog"
            :resetTable="resetTable"
            :tableData="tableData"
            :loadItems="loadItems"
          >
            <template v-if="!tableData.deleted">
              <v-btn icon @click="resetTable(props.search, props.orderBy)">
                <v-icon>mdi-refresh</v-icon>
                <v-tooltip activator="parent">Recargar tabla</v-tooltip>
              </v-btn>

              <v-btn
                icon
                v-if="forbiddenActions.indexOf('store') === -1"
                @click="openDialog('create')"
              >
                <v-icon>mdi-file-plus-outline</v-icon>
                <v-tooltip activator="parent">Crear elemento</v-tooltip>
              </v-btn>
            </template>

            <v-btn
              v-if="
                forbiddenActions.indexOf('restore') === -1 ||
                forbiddenActions.indexOf('destroyPermanent') === -1
              "
              :active="tableData.deleted"
              icon
              @click="tableData.deleted = !tableData.deleted"
            >
              <v-icon>mdi-delete-variant</v-icon>
              <v-tooltip activator="parent">{{
                tableData.deleted ? "Ver activos" : "Ver eliminados"
              }}</v-tooltip>
            </v-btn>
          </slot>
          <slot
            name="table.actions.append"
            :openDialog="openDialog"
            :resetTable="resetTable"
            :tableData="tableData"
            :loadItems="loadItems"
          ></slot>
        </v-toolbar>
      </template>

      <template v-if="!mobile" v-slot:thead>
        <tr>
          <td
            v-for="header in finalHeaders.filter(
              (header) => header.key != 'actions' && header.searchable !== false
            )"
            :key="header.key"
          >
            <v-text-field
              v-if="header.key"
              v-model="tableData.search[header.key]"
              @input="updateItems"
              type="text"
              class="px-1"
              variant="underlined"
            ></v-text-field>
          </td>
        </tr>
      </template>

      <template
        v-slot:[`item.${header.key}`]="{ item }"
        v-for="header in finalHeaders.filter(
          (header) => header.key !== 'actions'
        )"
      >
        <slot :name="`item.${header.key}`" :item="item">
          <span>
            <!-- Si header tiene relation y tableKey -->
            <template v-if="header.relation && header.relation.tableKey">
              {{
                generateItemTitle(
                  header.relation.tableKey,
                  item[header.relation.relation]
                )
              }}
            </template>
            <!-- Si no, usamos el valor normal -->
            <template v-else>
              {{ getValueByNestedKey(item, header.key) }}
            </template>
          </span>
        </slot>
      </template>

      <template v-slot:item.actions="{ item }">
        <slot
          name="item.actions.prepend"
          :item="item"
          :openDialog="openDialog"
          :resetTable="resetTable"
          :tableData="tableData"
          :loadItems="loadItems"
          :forbiddenActions="forbiddenActions"
        ></slot>
        <slot
          name="item.actions"
          :item="item"
          :openDialog="openDialog"
          :resetTable="resetTable"
          :tableData="tableData"
          :loadItems="loadItems"
          :forbiddenActions="forbiddenActions"
        >
          <v-btn
            v-if="
              item.records?.length > 0 &&
              forbiddenActions.indexOf('showRecords') === -1
            "
            density="compact"
            variant="text"
            icon
            @click="openHistoryDialog(item)"
          >
            <v-icon>mdi-history</v-icon>
            <v-tooltip activator="parent">Historial</v-tooltip>
          </v-btn>
          <v-btn
            v-if="
              !tableData.deleted && forbiddenActions.indexOf('update') === -1
            "
            density="compact"
            variant="text"
            icon
            @click="openDialog('edit', item)"
          >
            <v-icon>mdi-pencil</v-icon>
            <v-tooltip activator="parent">Editar</v-tooltip>
          </v-btn>
          <v-btn
            v-if="
              !tableData.deleted && forbiddenActions.indexOf('destroy') === -1
            "
            density="compact"
            variant="text"
            icon
            @click="openDialog('destroy', item)"
          >
            <v-icon>mdi-delete</v-icon>
            <v-tooltip activator="parent">Eliminar</v-tooltip>
          </v-btn>
          <v-btn
            v-if="
              tableData.deleted && forbiddenActions.indexOf('restore') === -1
            "
            density="compact"
            variant="text"
            icon
            @click="openDialog('restore', item)"
          >
            <v-icon>mdi-restore</v-icon>
            <v-tooltip activator="parent">Restaurar</v-tooltip>
          </v-btn>
          <v-btn
            v-if="
              tableData.deleted &&
              forbiddenActions.indexOf('destroyPermanent') === -1
            "
            density="compact"
            variant="text"
            icon
            @click="openDialog('destroyPermanent', item)"
          >
            <v-icon>mdi-delete-alert</v-icon>
            <v-tooltip activator="parent">Eliminar permanente</v-tooltip>
          </v-btn>
        </slot>
        <slot
          name="item.actions.append"
          :item="item"
          :openDialog="openDialog"
          :resetTable="resetTable"
          :tableData="tableData"
          :loadItems="loadItems"
          :forbiddenActions="forbiddenActions"
        ></slot>
      </template>
    </v-data-table-server>
    <loading-overlay v-if="loading" />
  </v-card>
</template>
