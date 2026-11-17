<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
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

  return values.map((value) => {
    const date = new Date(`${value}T12:00:00Z`)

    return {
      value,
      weekday: new Intl.DateTimeFormat('ru-RU', {
        timeZone: api.timezone.value,
        weekday: 'short',
      }).format(date).replace('.', ''),
      day: new Intl.DateTimeFormat('ru-RU', {
        timeZone: api.timezone.value,
        day: '2-digit',
      }).format(date),
      label: new Intl.DateTimeFormat('ru-RU', {
        timeZone: api.timezone.value,
        weekday: 'long',
        day: 'numeric',
        month: 'long',
      }).format(date),
    }
  })
})

const filteredSlots = computed(() => api.slots.value.filter(
  (slot) => dateKey(slot.start_at) === selectedDate.value,
))

const selectedDateLabel = computed(() => dateOptions.value.find(
  (date) => date.value === selectedDate.value,
)?.label ?? '')

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
    available: { label: 'Свободно', className: 'status-available' },
    booked: { label: 'Занято', className: 'status-booked' },
    past: { label: 'Прошло', className: 'status-past' },
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
    header: 'Отменить бронирование?',
    message: `Слот ${formatTime(slot.start_at)}–${formatTime(slot.end_at)} снова станет доступен.`,
    icon: 'pi pi-exclamation-circle',
    rejectLabel: 'Оставить',
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
  <div class="app-shell">
    <header class="topbar">
      <div class="topbar-inner">
        <span class="brand">Mini Booking</span>
        <span class="topbar-room">Переговорная</span>
      </div>
    </header>

    <main class="workspace">
      <header class="page-heading">
        <div>
          <p class="page-context">Офис</p>
          <h1>Переговорная</h1>
          <p class="room-description">Часовые слоты, ежедневно с 09:00 до 18:00</p>
        </div>

        <dl class="room-details">
          <div>
            <dt>Часовой пояс</dt>
            <dd>{{ api.timezone.value }}</dd>
          </div>
        </dl>
      </header>

      <section class="schedule-panel">
        <div class="schedule-heading">
          <h2>{{ selectedDateLabel || 'Выберите дату' }}</h2>
        </div>

        <nav v-if="dateOptions.length" class="date-strip" aria-label="Фильтр по дате">
          <button
            v-for="date in dateOptions"
            :key="date.value"
            type="button"
            class="date-tab"
            :class="{ active: selectedDate === date.value }"
            :aria-pressed="selectedDate === date.value"
            :aria-label="date.label"
            @click="selectedDate = date.value"
          >
            <span>{{ date.weekday }}</span>
            <strong>{{ date.day }}</strong>
          </button>
        </nav>

        <div class="feedback-stack">
          <Message v-if="notice" severity="success" closable @close="notice = ''">{{ notice }}</Message>
          <Message v-if="actionError" severity="error" closable @close="actionError = ''">{{ actionError }}</Message>
        </div>

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

        <div v-else class="schedule-list" aria-live="polite">
          <div class="list-header" aria-hidden="true">
            <span>Время</span>
            <span>Статус</span>
            <span></span>
          </div>

          <article
            v-for="slot in filteredSlots"
            :key="slot.id"
            class="slot-row"
            :class="`slot-${slot.status}`"
          >
            <div class="slot-time">
              <strong>{{ formatTime(slot.start_at) }}</strong>
              <span>—</span>
              <span>{{ formatTime(slot.end_at) }}</span>
            </div>

            <div class="slot-status" :class="statusMeta(slot.status).className">
              <i></i>
              <span>{{ statusMeta(slot.status).label }}</span>
            </div>

            <div class="slot-action">
              <Button
                v-if="slot.status === 'available'"
                label="Забронировать"
                variant="outlined"
                size="small"
                :loading="actionPending"
                :disabled="actionPending"
                @click="openBooking(slot)"
              />
              <Button
                v-else-if="slot.status === 'booked'"
                label="Отменить"
                severity="danger"
                variant="text"
                size="small"
                :loading="actionPending"
                :disabled="actionPending"
                @click="askToCancel(slot)"
              />
              <span v-else class="no-action">—</span>
            </div>
          </article>
        </div>

        <p class="schedule-note">Слот недоступен для бронирования с момента его начала.</p>
      </section>
    </main>

    <Dialog
      v-model:visible="bookingDialogOpen"
      modal
      header="Забронировать переговорную"
      :style="{ width: 'min(29rem, calc(100vw - 2rem))' }"
      :closable="!actionPending"
    >
      <div v-if="selectedSlot" class="dialog-summary">
        <i class="pi pi-calendar"></i>
        <div>
          <strong>{{ selectedDateLabel }}</strong>
          <span>{{ formatTime(selectedSlot.start_at) }}–{{ formatTime(selectedSlot.end_at) }}</span>
        </div>
      </div>

      <Message v-if="actionError" severity="error" :closable="false">{{ actionError }}</Message>

      <form id="booking-form" class="booking-form" @submit.prevent="submitBooking">
        <div class="field">
          <label for="customer-name">Имя</label>
          <InputText id="customer-name" v-model="form.customer_name" required maxlength="120" autofocus />
          <small v-if="fieldErrors.customer_name" class="field-error">{{ fieldErrors.customer_name[0] }}</small>
        </div>

        <div class="field">
          <label for="customer-email">Рабочий email</label>
          <InputText id="customer-email" v-model="form.customer_email" type="email" required maxlength="255" />
          <small v-if="fieldErrors.customer_email" class="field-error">{{ fieldErrors.customer_email[0] }}</small>
        </div>
      </form>

      <template #footer>
        <Button label="Закрыть" severity="secondary" variant="text" :disabled="actionPending" @click="bookingDialogOpen = false" />
        <Button label="Подтвердить" type="submit" form="booking-form" :loading="actionPending" />
      </template>
    </Dialog>

    <ConfirmDialog />
  </div>
</template>
