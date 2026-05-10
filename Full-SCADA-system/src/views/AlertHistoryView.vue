<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'

const FORCE_DEMO = false

const todayDate = ref(new Date().toISOString().slice(0, 10))
const loseData = ref([])
const selectedCategory = ref('')
const mobileTab = ref('categories')
const selectedTimeRange = ref('')
const sortBy = ref('time')

const predefinedCategories = ref([
  'Motor Speed A (RPM)',
  'Motor Speed B (RPM)',
  'Output Rate A (u/hr)',
  'Total Output A (units)',
  'Total Output B (units)',
  'Total Output C (units)',
  'Motor Load (%)',
  'Vibration (mm/s)',
  'Hopper Level (m)',
  'Buffer Level (m)',
  'PT1 (bar)', 'PT2 (bar)', 'PT3 (bar)', 'PT4 (bar)',
])

function generateDemoData(categories, dateStr) {
  const demoLoseData = []
  const now = new Date()
  const currentDate = now.toISOString().slice(0, 10)
  const currentHour = now.getHours()
  const seed = Math.floor(Date.now() / 5000)

  categories.forEach((catName, catIndex) => {
    for (let h = 0; h < 24; h++) {
      if (dateStr > currentDate || (dateStr === currentDate && h > currentHour)) continue
      const hash = ((catIndex * 31 + h * 17 + seed * 7) % 100)
      const threshold = 15 + (catIndex % 5) * 4
      if (hash < threshold) {
        demoLoseData.push({ name: catName, date: dateStr, missingHour: h.toString().padStart(2, '0') + ':00' })
      }
    }
  })
  return demoLoseData
}

function fetchData() {
  if (FORCE_DEMO) {
    loseData.value = generateDemoData(predefinedCategories.value, todayDate.value)
    return
  }
  fetch('./backend/alert_3.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ today_date: todayDate.value }) })
    .then(res => res.json()).then(checkDataIntegrity).catch(() => {})
}

function checkDataIntegrity(chartDate) {
  const map = {}
  predefinedCategories.value.forEach(c => { map[c] = [] })
  chartDate.forEach(item => { if (map[item.name]) map[item.name].push(item) })
  loseData.value = []
  const now = new Date()
  const currentDate = now.toISOString().slice(0, 10)
  const currentHour = now.getHours()
  for (let i = 0; i < 24; i++) {
    const startHour = i.toString().padStart(2, '0') + ':00'
    if (todayDate.value > currentDate || (todayDate.value === currentDate && i > currentHour)) continue
    Object.keys(map).forEach(name => {
      const hasData = map[name].map(item => item.time).some(time => time >= startHour && time < startHour.slice(0, 2) + ':59')
      if (!hasData) loseData.value.push({ name, date: todayDate.value, missingHour: startHour })
    })
  }
}

const filteredAlerts = computed(() => {
  let filtered = [...loseData.value]
  if (selectedCategory.value) filtered = filtered.filter(a => a.name === selectedCategory.value)
  if (selectedTimeRange.value === 'morning') filtered = filtered.filter(a => parseInt(a.missingHour) < 12)
  if (selectedTimeRange.value === 'afternoon') filtered = filtered.filter(a => parseInt(a.missingHour) >= 12)
  if (sortBy.value === 'time') filtered.sort((a, b) => b.missingHour.localeCompare(a.missingHour))
  if (sortBy.value === 'category') filtered.sort((a, b) => a.name.localeCompare(b.name))
  return filtered
})

const totalMissingData = computed(() => loseData.value.length)
const categoriesWithMissingData = computed(() => new Set(loseData.value.map(i => i.name)).size)
const mostRecentMissingHour = computed(() => {
  if (!loseData.value.length) return '--:--'
  return [...loseData.value].sort((a, b) => b.missingHour.localeCompare(a.missingHour))[0].missingHour
})
const dataIntegrityPercentage = computed(() => {
  const now = new Date()
  const currentDate = now.toISOString().slice(0, 10)
  const currentHour = now.getHours()
  let totalHours = todayDate.value < currentDate ? 24 : todayDate.value === currentDate ? currentHour + 1 : 0
  const total = predefinedCategories.value.length * totalHours
  if (!total) return 100
  return Math.round(((total - loseData.value.length) / total) * 100)
})
const categoryStatus = computed(() => {
  const status = {}
  predefinedCategories.value.forEach(c => { status[c] = { missingCount: 0, lastMissingTime: null } })
  loseData.value.forEach(alert => {
    if (status[alert.name]) {
      status[alert.name].missingCount++
      if (!status[alert.name].lastMissingTime || alert.missingHour > status[alert.name].lastMissingTime)
        status[alert.name].lastMissingTime = alert.missingHour
    }
  })
  return status
})

const integrityRadius = 18
const integrityCircumference = 2 * Math.PI * integrityRadius
const integrityStrokeDash = computed(() => {
  const pct = dataIntegrityPercentage.value / 100
  return `${pct * integrityCircumference} ${integrityCircumference}`
})

function selectCategory(category) {
  selectedCategory.value = category === selectedCategory.value ? '' : category
  if (selectedCategory.value && window.innerWidth < 1024) mobileTab.value = 'records'
}

function progressWidth(missingCount) {
  return missingCount > 0 ? Math.min((missingCount / 24) * 100, 100) + '%' : '1%'
}

watch(todayDate, () => fetchData())

let refreshInterval = null
onMounted(() => { fetchData(); refreshInterval = setInterval(fetchData, 5000) })
onUnmounted(() => { if (refreshInterval) clearInterval(refreshInterval) })
</script>

<template>
  <div class="relative min-h-[calc(100vh-3.5rem)]">
    <!-- Factory Background -->
    <div class="fixed inset-0 bg-gradient-to-b from-[#0d0d12] to-[#111318] pointer-events-none z-0"></div>

    <div class="relative z-10 max-w-[1440px] mx-auto px-4 md:px-8 py-6 md:py-8">

      <!-- Page Title -->
      <div class="hidden md:flex flex-col mb-6 md:mb-8">
        <h1 class="text-2xl md:text-3xl font-headline font-bold text-on-surface tracking-tight mb-1">
          Data Loss Control
          <span class="text-primary/60 font-medium ml-2">Data Loss Monitoring</span>
        </h1>
        <p class="text-on-surface-variant text-sm">Real-time integrity analysis for factory machine sensor network.</p>
      </div>

      <!-- KPI Summary Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-6 mb-4 md:mb-8">
        <div class="glass-panel bg-white/[0.03] backdrop-blur-xl rounded-xl md:rounded-2xl p-3 md:p-5 border border-white/5 hover:border-error/30 transition-all">
          <div class="flex items-center gap-2 md:flex-col md:items-start">
            <div class="w-8 h-8 md:w-10 md:h-10 rounded-lg md:rounded-xl bg-error/15 flex items-center justify-center shrink-0 relative">
              <span class="material-symbols-outlined text-error text-lg md:text-xl">error</span>
              <span v-if="totalMissingData > 0" class="absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full bg-error animate-ping"></span>
              <span v-if="totalMissingData > 0" class="absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full bg-error"></span>
            </div>
            <div class="min-w-0 md:mt-3">
              <p class="text-on-surface-variant text-[10px] md:text-xs font-medium uppercase tracking-wider mb-0.5 md:mb-2 truncate">Missing Data</p>
              <span class="text-2xl md:text-4xl font-headline font-bold text-error tabular-nums glow-error">{{ totalMissingData }}</span>
            </div>
          </div>
        </div>

        <div class="glass-panel bg-white/[0.03] backdrop-blur-xl rounded-xl md:rounded-2xl p-3 md:p-5 border border-white/5 hover:border-secondary/30 transition-all">
          <div class="flex items-center gap-2 md:flex-col md:items-start">
            <div class="w-8 h-8 md:w-10 md:h-10 rounded-lg md:rounded-xl bg-secondary/15 flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-secondary text-lg md:text-xl">category</span>
            </div>
            <div class="min-w-0 md:mt-3">
              <p class="text-on-surface-variant text-[10px] md:text-xs font-medium uppercase tracking-wider mb-0.5 md:mb-2 truncate">Sensors Affected</p>
              <div class="flex items-end gap-1">
                <span class="text-2xl md:text-4xl font-headline font-bold text-secondary tabular-nums glow-secondary">{{ categoriesWithMissingData }}</span>
                <span class="text-secondary/50 text-[10px] mb-0.5 font-label">/{{ predefinedCategories.length }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="glass-panel bg-white/[0.03] backdrop-blur-xl rounded-xl md:rounded-2xl p-3 md:p-5 border border-white/5 hover:border-primary/30 transition-all">
          <div class="flex items-center gap-2 md:flex-col md:items-start">
            <div class="w-8 h-8 md:w-10 md:h-10 rounded-lg md:rounded-xl bg-primary/15 flex items-center justify-center shrink-0 relative">
              <svg class="absolute inset-0 w-full h-full -rotate-90" viewBox="0 0 40 40">
                <circle cx="20" cy="20" :r="integrityRadius" fill="none" stroke="currentColor" class="text-white/10" stroke-width="2.5"/>
                <circle cx="20" cy="20" :r="integrityRadius" fill="none" :stroke="dataIntegrityPercentage > 70 ? '#f4a25b' : '#ff716c'" stroke-width="2.5" stroke-linecap="round" :stroke-dasharray="integrityStrokeDash" class="transition-all duration-700"/>
              </svg>
              <span class="material-symbols-outlined text-primary text-lg md:text-xl relative z-10">verified</span>
            </div>
            <div class="min-w-0 md:mt-3">
              <p class="text-on-surface-variant text-[10px] md:text-xs font-medium uppercase tracking-wider mb-0.5 md:mb-2 truncate">Data Integrity</p>
              <span class="text-2xl md:text-4xl font-headline font-bold tabular-nums" :class="dataIntegrityPercentage > 70 ? 'text-primary glow-primary' : 'text-error glow-error'">{{ dataIntegrityPercentage }}%</span>
            </div>
          </div>
        </div>

        <div class="glass-panel bg-white/[0.03] backdrop-blur-xl rounded-xl md:rounded-2xl p-3 md:p-5 border border-white/5 hover:border-tertiary/30 transition-all">
          <div class="flex items-center gap-2 md:flex-col md:items-start">
            <div class="w-8 h-8 md:w-10 md:h-10 rounded-lg md:rounded-xl bg-tertiary/15 flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-tertiary text-lg md:text-xl">schedule</span>
            </div>
            <div class="min-w-0 md:mt-3">
              <p class="text-on-surface-variant text-[10px] md:text-xs font-medium uppercase tracking-wider mb-0.5 md:mb-2 truncate">Recent Missing</p>
              <span class="text-2xl md:text-4xl font-headline font-bold text-tertiary tabular-nums">{{ mostRecentMissingHour }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Filter Bar -->
      <div class="glass-panel bg-white/[0.03] rounded-xl mb-4 md:mb-8 p-3 md:p-4 border border-white/5">
        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4">
          <div class="flex items-center gap-1.5 bg-white/5 px-2.5 py-1.5 md:px-3 md:py-2 rounded-lg border border-white/10 hover:border-primary/40 transition-colors">
            <span class="material-symbols-outlined text-sm text-primary">calendar_today</span>
            <input type="date" v-model="todayDate" class="bg-transparent text-xs md:text-sm font-medium text-on-surface border-none outline-none flex-1 cursor-pointer" />
          </div>
          <div class="flex items-center gap-2">
            <div class="flex items-center gap-1 flex-1">
              <button @click="selectedTimeRange = ''" class="flex-1 md:flex-none px-2 md:px-3 py-1.5 rounded-lg text-[10px] md:text-xs font-bold transition-all" :class="selectedTimeRange === '' ? 'bg-primary/20 text-primary border border-primary/40' : 'bg-white/5 text-on-surface-variant hover:bg-white/10 border border-white/10'">All Day</button>
              <button @click="selectedTimeRange = 'morning'" class="flex-1 md:flex-none px-2 md:px-3 py-1.5 rounded-lg text-[10px] md:text-xs font-bold transition-all" :class="selectedTimeRange === 'morning' ? 'bg-primary/20 text-primary border border-primary/40' : 'bg-white/5 text-on-surface-variant hover:bg-white/10 border border-white/10'">AM</button>
              <button @click="selectedTimeRange = 'afternoon'" class="flex-1 md:flex-none px-2 md:px-3 py-1.5 rounded-lg text-[10px] md:text-xs font-bold transition-all" :class="selectedTimeRange === 'afternoon' ? 'bg-primary/20 text-primary border border-primary/40' : 'bg-white/5 text-on-surface-variant hover:bg-white/10 border border-white/10'">PM</button>
            </div>
            <div class="flex items-center gap-1.5 md:ml-auto bg-white/5 px-2.5 py-1.5 md:px-3 md:py-2 rounded-lg border border-white/10 hover:border-primary/40 transition-colors">
              <span class="material-symbols-outlined text-sm text-primary">sort</span>
              <select v-model="sortBy" class="bg-transparent text-xs md:text-sm font-medium text-on-surface border-none outline-none cursor-pointer appearance-none pr-2">
                <option value="time" class="bg-surface-container text-on-surface">Sort by Time</option>
                <option value="category" class="bg-surface-container text-on-surface">Sort by Category</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Mobile Tab Switcher -->
      <div class="flex lg:hidden mb-4 glass-panel bg-white/[0.03] rounded-xl border border-white/5 p-1">
        <button @click="mobileTab = 'categories'" class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-lg text-xs font-bold transition-all" :class="mobileTab === 'categories' ? 'bg-primary/20 text-primary' : 'text-on-surface-variant'">
          <span class="material-symbols-outlined text-sm">category</span>Sensor Status
        </button>
        <button @click="mobileTab = 'records'" class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-lg text-xs font-bold transition-all" :class="mobileTab === 'records' ? 'bg-primary/20 text-primary' : 'text-on-surface-variant'">
          <span class="material-symbols-outlined text-sm">table_rows</span>Loss Records
          <span v-if="filteredAlerts.length" class="bg-error/20 text-error text-[10px] px-1.5 py-0.5 rounded-full">{{ filteredAlerts.length }}</span>
        </button>
      </div>

      <!-- Two-Column Layout -->
      <div class="grid grid-cols-1 lg:grid-cols-10 gap-4 md:gap-8">

        <!-- Left Sidebar: Sensor Status -->
        <div class="lg:col-span-3 space-y-4" :class="{ 'hidden lg:block': mobileTab === 'records' }">
          <div class="flex justify-between items-center px-1">
            <h2 class="text-base font-bold font-headline">Sensor Status</h2>
            <button @click="fetchData" class="hover:rotate-180 transition-transform duration-500">
              <span class="material-symbols-outlined text-primary text-lg">refresh</span>
            </button>
          </div>
          <div class="space-y-2.5 max-h-[calc(100vh-420px)] overflow-y-auto custom-scrollbar pr-1">
            <div
              v-for="(status, category) in categoryStatus" :key="category"
              class="glass-panel bg-white/[0.03] p-4 rounded-xl border transition-all cursor-pointer"
              :class="[
                selectedCategory === category ? 'border-primary/40 bg-primary/5 translate-x-1' : 'border-white/5 hover:translate-x-1',
                status.missingCount > 0 ? 'hover:border-error/30' : 'hover:border-primary/30 opacity-70'
              ]"
              @click="selectCategory(category)"
            >
              <div class="flex justify-between items-center mb-2">
                <span class="text-xs font-bold truncate mr-2">{{ category }}</span>
                <span v-if="status.missingCount > 0" class="px-2 py-0.5 rounded text-[10px] font-bold bg-error/20 text-error border border-error/30 whitespace-nowrap">{{ status.missingCount }} Missing</span>
                <span v-else class="px-2 py-0.5 rounded text-[10px] font-bold bg-primary/20 text-primary border border-primary/30">Stable</span>
              </div>
              <div class="w-full bg-white/5 h-1.5 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500" :class="status.missingCount > 0 ? 'bg-error' : 'bg-primary'" :style="{ width: progressWidth(status.missingCount) }"></div>
              </div>
              <div class="flex justify-between mt-2 text-[10px] text-on-surface-variant">
                <span>Loss: {{ status.missingCount }}/24h</span>
                <span v-if="status.lastMissingTime">Last: {{ status.lastMissingTime }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Content: Loss Records Table -->
        <div class="lg:col-span-7" :class="{ 'hidden lg:block': mobileTab === 'categories' }">
          <div class="glass-panel bg-white/[0.03] rounded-2xl border border-white/5 overflow-hidden flex flex-col" style="min-height: 400px; max-height: calc(100vh - 200px);">
            <div class="p-4 md:p-6 border-b border-white/5 flex flex-wrap justify-between items-center gap-3">
              <div>
                <h2 class="text-base font-bold font-headline">Loss Records</h2>
                <p v-if="selectedCategory" class="text-primary text-xs mt-1 flex items-center gap-1">
                  <span class="material-symbols-outlined text-xs">filter_list</span>
                  {{ selectedCategory }}
                  <button @click="selectedCategory = ''" class="text-on-surface-variant hover:text-error ml-1 transition-colors"><span class="material-symbols-outlined text-xs">close</span></button>
                </p>
              </div>
              <div class="text-xs text-on-surface-variant font-label">{{ filteredAlerts.length }} records</div>
            </div>

            <div class="overflow-x-auto overflow-y-auto flex-1 custom-scrollbar">
              <table class="w-full text-left border-collapse">
                <thead class="bg-surface-container-high/60 text-[11px] uppercase tracking-widest text-on-surface-variant sticky top-0 z-10">
                  <tr>
                    <th class="hidden md:table-cell px-4 md:px-6 py-3 font-bold">Date</th>
                    <th class="px-3 md:px-6 py-3 font-bold">Time</th>
                    <th class="px-3 md:px-6 py-3 font-bold">Sensor</th>
                    <th class="px-3 md:px-6 py-3 font-bold">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                  <tr v-for="(alert, index) in filteredAlerts" :key="index" class="hover:bg-white/[0.03] transition-colors cursor-pointer">
                    <td class="hidden md:table-cell px-4 md:px-6 py-3 text-sm font-medium tabular-nums">{{ alert.date }}</td>
                    <td class="px-3 md:px-6 py-3 text-sm font-medium text-secondary tabular-nums">{{ alert.missingHour }}</td>
                    <td class="px-3 md:px-6 py-3 text-xs md:text-sm">{{ alert.name }}</td>
                    <td class="px-3 md:px-6 py-3">
                      <span class="px-2 md:px-3 py-1 rounded-full text-[10px] font-bold bg-error/20 text-error border border-error/30 uppercase">Loss</span>
                    </td>
                  </tr>
                  <tr v-if="filteredAlerts.length === 0">
                    <td colspan="4" class="text-center py-12 text-on-surface-variant">
                      <span class="material-symbols-outlined text-primary text-3xl mb-2 block">check_circle</span>
                      <span class="text-sm">No data loss detected for current filters</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="p-4 border-t border-white/5 flex justify-between items-center text-xs text-on-surface-variant">
              <span>Showing {{ filteredAlerts.length }} of {{ totalMissingData }} total records</span>
              <span v-if="FORCE_DEMO" class="px-2 py-0.5 rounded bg-tertiary/20 text-tertiary border border-tertiary/30 text-[10px] font-bold uppercase">DEMO Mode</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0.8); cursor: pointer; }
select option { background-color: #111318; color: #e8e8f0; }
</style>
