<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import Card from 'primevue/card'
import ConfirmDialog from 'primevue/confirmdialog'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useBookingApi } from './composables/useBookingApi'

const api = useBookingApi()
const confirm = useConfirm()
const selectedDate = ref('')
const bookingDialogOpen = ref(false)
const selectedSlot = ref(null)
const actionPending = ref(false)
const actionError = ref('')
const fieldErrors = ref({})
const notice = ref('')
const form = reactive({ customer_name: '', customer_email: '' })

const dateKeyFormatter = computed(() => new Intl.DateTimeFormat('en-CA', {
  timeZone: api.timezone.value,
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
}))

const dateOptions = computed(() => {
  const values = [...new Set(api.slots.value.map((slot) => dateKey(slot.start_at)))]

  return values.map((value) => ({
    value,
    label: new Intl.DateTimeFormat('ru-RU', {
      timeZone: api.timezone.value,
      weekday: 'short',
      day: 'numeric',
      month: 'long',
    }).format(new Date(`${value}T12:00:00Z`)),
  }))
})

const filteredSlots = computed(() => api.slots.value.filter(
  (slot) => dateKey(slot.start_at) === selectedDate.value,
))

watch(dateOptions, (options) => {
  if (!options.some((option) => option.value === selectedDate.value)) {
    selectedDate.value = options[0]?.value ?? ''
  }
}, { immediate: true })

function dateKey(value) {
  return dateKeyFormatter.value.format(new Date(value))
}

function formatTime(value) {
  return new Intl.DateTimeFormat('ru-RU', {
    timeZone: api.timezone.value,
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}

function statusMeta(status) {
  return {
    available: { value: 'Свободно', severity: 'success' },
    booked: { value: 'Занято', severity: 'danger' },
    past: { value: 'Прошло', severity: 'secondary' },
  }[status]
}

function openBooking(slot) {
  selectedSlot.value = slot
  form.customer_name = ''
  form.customer_email = ''
  fieldErrors.value = {}
  actionError.value = ''
  bookingDialogOpen.value = true
}

async function submitBooking() {
  actionPending.value = true
  actionError.value = ''
  fieldErrors.value = {}

  try {
    await api.createBooking({
      slot_id: selectedSlot.value.id,
      customer_name: form.customer_name,
      customer_email: form.customer_email,
    })
    bookingDialogOpen.value = false
    notice.value = 'Переговорная забронирована.'
    await api.loadSlots()
  } catch (error) {
    if (error.status === 422) {
      fieldErrors.value = error.payload?.errors ?? {}
    } else {
      actionError.value = error.message
      if (error.status === 409) await api.loadSlots()
    }
  } finally {
    actionPending.value = false
  }
}

function askToCancel(slot) {
  confirm.require({
    header: 'Отмена бронирования',
    message: `Освободить слот ${formatTime(slot.start_at)}–${formatTime(slot.end_at)}?`,
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Не отменять',
    acceptLabel: 'Отменить',
    acceptClass: 'p-button-danger',
    accept: () => cancel(slot),
  })
}

async function cancel(slot) {
  actionPending.value = true
  actionError.value = ''

  try {
    await api.cancelBooking(slot.booking_id)
    notice.value = 'Бронирование отменено.'
    await api.loadSlots()
  } catch (error) {
    actionError.value = error.message
  } finally {
    actionPending.value = false
  }
}

onMounted(api.loadSlots)
</script>

<template>
  <main class="page-shell">
    <section class="page-heading">
      <div>
        <p class="eyebrow">Одна переговорная · 09:00–18:00</p>
        <h1>Mini Booking</h1>
        <p class="subtitle">Выберите свободный часовой слот и укажите контактные данные.</p>
      </div>

      <div class="date-filter" v-if="dateOptions.length">
        <label for="date">Дата</label>
        <Select
          input-id="date"
          v-model="selectedDate"
          :options="dateOptions"
          option-label="label"
          option-value="value"
        />
      </div>
    </section>

    <Message v-if="notice" severity="success" closable @close="notice = ''">{{ notice }}</Message>
    <Message v-if="actionError" severity="error" closable @close="actionError = ''">{{ actionError }}</Message>

    <div v-if="api.loading.value && !api.slots.value.length" class="state-block">
      <ProgressSpinner aria-label="Загрузка расписания" />
      <span>Загружаем расписание…</span>
    </div>

    <Message v-else-if="api.loadError.value" severity="error" :closable="false">
      <div class="error-row">
        <span>{{ api.loadError.value }}</span>
        <Button label="Повторить" icon="pi pi-refresh" size="small" @click="api.loadSlots" />
      </div>
    </Message>

    <section v-else class="slots-grid" aria-live="polite">
      <Card v-for="slot in filteredSlots" :key="slot.id" class="slot-card" :class="`slot-${slot.status}`">
        <template #content>
          <div class="slot-content">
            <div>
              <p class="slot-time">{{ formatTime(slot.start_at) }}–{{ formatTime(slot.end_at) }}</p>
              <Tag v-bind="statusMeta(slot.status)" />
            </div>

            <Button
              v-if="slot.status === 'available'"
              label="Забронировать"
              icon="pi pi-calendar-plus"
              :loading="actionPending"
              :disabled="actionPending"
              @click="openBooking(slot)"
            />
            <Button
              v-else-if="slot.status === 'booked'"
              label="Отменить"
              icon="pi pi-times"
              severity="danger"
              variant="outlined"
              :loading="actionPending"
              :disabled="actionPending"
              @click="askToCancel(slot)"
            />
          </div>
        </template>
      </Card>
    </section>

    <Dialog
      v-model:visible="bookingDialogOpen"
      modal
      header="Новое бронирование"
      :style="{ width: 'min(30rem, calc(100vw - 2rem))' }"
      :closable="!actionPending"
    >
      <p class="dialog-slot" v-if="selectedSlot">
        {{ formatTime(selectedSlot.start_at) }}–{{ formatTime(selectedSlot.end_at) }}
      </p>

      <Message v-if="actionError" severity="error" :closable="false">{{ actionError }}</Message>

      <form id="booking-form" class="booking-form" @submit.prevent="submitBooking">
        <div class="field">
          <label for="customer-name">Имя</label>
          <InputText id="customer-name" v-model="form.customer_name" required maxlength="120" autofocus />
          <small v-if="fieldErrors.customer_name" class="field-error">{{ fieldErrors.customer_name[0] }}</small>
        </div>

        <div class="field">
          <label for="customer-email">Email</label>
          <InputText id="customer-email" v-model="form.customer_email" type="email" required maxlength="255" />
          <small v-if="fieldErrors.customer_email" class="field-error">{{ fieldErrors.customer_email[0] }}</small>
        </div>
      </form>

      <template #footer>
        <Button label="Закрыть" severity="secondary" variant="text" :disabled="actionPending" @click="bookingDialogOpen = false" />
        <Button label="Забронировать" type="submit" form="booking-form" icon="pi pi-check" :loading="actionPending" />
      </template>
    </Dialog>

    <ConfirmDialog />
  </main>
</template>
