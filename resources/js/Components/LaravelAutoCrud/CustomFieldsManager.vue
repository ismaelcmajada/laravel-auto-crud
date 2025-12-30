<script setup>
import { ref, onMounted } from "vue"
import axios from "axios"

const props = defineProps({
  modelName: {
    type: String,
    required: true,
  },
})

const emit = defineEmits(["updated"])

const customFields = ref([])
const fieldTypes = ref([])
const loading = ref(false)
const dialog = ref(false)
const editingField = ref(null)

const defaultField = {
  label: "",
  type: "string",
  options: [],
  rules: { required: false },
  is_active: true,
  show_in_table: false,
}

const formData = ref({ ...defaultField })
const optionsInput = ref("")

const loadCustomFields = async () => {
  loading.value = true
  try {
    const response = await axios.get(
      `/laravel-auto-crud/custom-fields/${props.modelName}`
    )
    customFields.value = response.data
  } catch (error) {
    console.error("Error loading custom fields:", error)
  } finally {
    loading.value = false
  }
}

const loadFieldTypes = async () => {
  try {
    const response = await axios.get("/laravel-auto-crud/custom-fields-types")
    fieldTypes.value = response.data
  } catch (error) {
    console.error("Error loading field types:", error)
  }
}

const openDialog = (field = null) => {
  if (field) {
    editingField.value = field
    formData.value = { ...field }
    optionsInput.value = field.options?.join(", ") || ""
  } else {
    editingField.value = null
    formData.value = { ...defaultField }
    optionsInput.value = ""
  }
  dialog.value = true
}

const closeDialog = () => {
  dialog.value = false
  editingField.value = null
  formData.value = { ...defaultField }
  optionsInput.value = ""
}

const saveField = async () => {
  loading.value = true
  try {
    const data = { ...formData.value }

    if (data.type === "select" && optionsInput.value) {
      data.options = optionsInput.value.split(",").map((o) => o.trim())
    }

    if (editingField.value) {
      await axios.put(
        `/laravel-auto-crud/custom-fields/${props.modelName}/${editingField.value.id}`,
        data
      )
    } else {
      await axios.post(
        `/laravel-auto-crud/custom-fields/${props.modelName}`,
        data
      )
    }

    await loadCustomFields()
    closeDialog()
    emit("updated")
  } catch (error) {
    console.error("Error saving custom field:", error)
  } finally {
    loading.value = false
  }
}

const deleteField = async (field) => {
  if (!confirm(`¿Eliminar el campo "${field.label}"?`)) return

  loading.value = true
  try {
    await axios.delete(
      `/laravel-auto-crud/custom-fields/${props.modelName}/${field.id}`
    )
    await loadCustomFields()
    emit("updated")
  } catch (error) {
    console.error("Error deleting custom field:", error)
  } finally {
    loading.value = false
  }
}

const toggleActive = async (field) => {
  loading.value = true
  try {
    await axios.put(
      `/laravel-auto-crud/custom-fields/${props.modelName}/${field.id}`,
      { is_active: !field.is_active }
    )
    await loadCustomFields()
    emit("updated")
  } catch (error) {
    console.error("Error toggling field:", error)
  } finally {
    loading.value = false
  }
}

const getTypeName = (type) => {
  const found = fieldTypes.value.find((t) => t.value === type)
  return found?.label || type
}

onMounted(() => {
  loadCustomFields()
  loadFieldTypes()
})
</script>

<template>
  <v-card>
    <v-card-title class="d-flex align-center">
      <span>Campos Personalizados</span>
      <v-spacer></v-spacer>
      <v-btn color="primary" size="small" @click="openDialog()">
        <v-icon left>mdi-plus</v-icon>
        Añadir Campo
      </v-btn>
    </v-card-title>

    <v-card-text>
      <v-progress-linear v-if="loading" indeterminate></v-progress-linear>

      <v-list v-if="customFields.length > 0">
        <v-list-item
          v-for="field in customFields"
          :key="field.id"
          :class="{ 'opacity-50': !field.is_active }"
        >
          <template v-slot:prepend>
            <v-icon>mdi-form-textbox</v-icon>
          </template>

          <v-list-item-title>{{ field.label }}</v-list-item-title>
          <v-list-item-subtitle>
            {{ getTypeName(field.type) }}
            <v-chip v-if="field.rules?.required" size="x-small" class="ml-2">
              Requerido
            </v-chip>
            <v-chip
              v-if="field.show_in_table"
              size="x-small"
              color="info"
              class="ml-1"
            >
              En tabla
            </v-chip>
          </v-list-item-subtitle>

          <template v-slot:append>
            <v-btn
              icon
              size="small"
              variant="text"
              @click="toggleActive(field)"
            >
              <v-icon>{{ field.is_active ? "mdi-eye" : "mdi-eye-off" }}</v-icon>
              <v-tooltip activator="parent">{{
                field.is_active ? "Desactivar" : "Activar"
              }}</v-tooltip>
            </v-btn>
            <v-btn icon size="small" variant="text" @click="openDialog(field)">
              <v-icon>mdi-pencil</v-icon>
              <v-tooltip activator="parent">Editar</v-tooltip>
            </v-btn>
            <v-btn
              icon
              size="small"
              variant="text"
              color="error"
              @click="deleteField(field)"
            >
              <v-icon>mdi-delete</v-icon>
              <v-tooltip activator="parent">Eliminar</v-tooltip>
            </v-btn>
          </template>
        </v-list-item>
      </v-list>

      <v-alert v-else type="info" variant="tonal">
        No hay campos personalizados definidos para este modelo.
      </v-alert>
    </v-card-text>
  </v-card>

  <!-- Dialog para crear/editar campo -->
  <v-dialog v-model="dialog" max-width="500">
    <v-card>
      <v-card-title>
        {{ editingField ? "Editar Campo" : "Nuevo Campo Personalizado" }}
      </v-card-title>

      <v-card-text>
        <v-text-field
          v-model="formData.label"
          label="Etiqueta *"
          required
        ></v-text-field>

        <v-select
          v-model="formData.type"
          :items="fieldTypes"
          item-title="label"
          item-value="value"
          label="Tipo de Campo *"
        ></v-select>

        <v-text-field
          v-if="formData.type === 'select'"
          v-model="optionsInput"
          label="Opciones (separadas por coma)"
          hint="Ej: Opción 1, Opción 2, Opción 3"
          persistent-hint
        ></v-text-field>

        <v-checkbox
          v-model="formData.rules.required"
          label="Campo requerido"
        ></v-checkbox>

        <v-checkbox
          v-model="formData.show_in_table"
          label="Mostrar en tabla"
        ></v-checkbox>

        <v-checkbox
          v-model="formData.is_active"
          label="Campo activo"
        ></v-checkbox>
      </v-card-text>

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn variant="text" @click="closeDialog">Cancelar</v-btn>
        <v-btn
          color="primary"
          variant="flat"
          @click="saveField"
          :loading="loading"
        >
          Guardar
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
