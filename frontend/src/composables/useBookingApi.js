import { ref } from 'vue'

class ApiError extends Error {
  constructor(message, status = 0, payload = null) {
    super(message)
    this.status = status
    this.payload = payload
  }
}

async function request(path, options = {}) {
  let response

  try {
    response = await fetch(path, {
      ...options,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...options.headers,
      },
    })
  } catch {
    throw new ApiError('Не удалось связаться с сервером. Проверьте соединение и попробуйте ещё раз.')
  }

  const payload = response.status === 204 ? null : await response.json().catch(() => null)

  if (!response.ok) {
    throw new ApiError(
      payload?.message ?? 'Сервер не смог выполнить запрос. Попробуйте ещё раз.',
      response.status,
      payload,
    )
  }

  return payload
}

export function useBookingApi() {
  const slots = ref([])
  const timezone = ref('UTC')
  const loading = ref(false)
  const loadError = ref('')

  async function loadSlots() {
    loading.value = true
    loadError.value = ''

    try {
      const response = await request('/api/slots')
      slots.value = response.data
      timezone.value = response.timezone
    } catch (error) {
      loadError.value = error.message
    } finally {
      loading.value = false
    }
  }

  async function createBooking(data) {
    return request('/api/bookings', {
      method: 'POST',
      body: JSON.stringify(data),
    })
  }

  async function cancelBooking(id) {
    return request(`/api/bookings/${id}`, { method: 'DELETE' })
  }

  return { slots, timezone, loading, loadError, loadSlots, createBooking, cancelBooking }
}
