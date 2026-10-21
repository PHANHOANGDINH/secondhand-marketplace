<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js'
import { formatPrice } from '../composables/productPresentation'
import { cssVar, useTheme } from '../composables/useTheme'
Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip)
const props = defineProps({ series: { type: Array, required: true } })
const canvas = ref(null)
let chart = null
const { theme } = useTheme()
// A canvas cannot use CSS variables, so the colours are read from the tokens on every draw.
function paint(target) {
  const bar = cssVar('--primary', '#c2410c')
  const grid = cssVar('--border', '#eadfcb')
  const text = cssVar('--muted', '#6b6660')
  target.data.datasets[0].backgroundColor = bar
  for (const axis of ['x', 'y']) {
    target.config.options.scales[axis].ticks.color = text
    target.config.options.scales[axis].grid.color = grid
  }
}
function draw() {
  if (!canvas.value) return
  const labels = props.series.map((row) => row.period)
  // Chart coordinates are numeric; labels/table always use the original decimal strings.
  const values = props.series.map((row) => Number(row.revenue))
  if (chart) {
    chart.data.labels = labels
    chart.data.datasets[0].data = values
    paint(chart)
    chart.update('none')
    return
  }
  chart = new Chart(canvas.value, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Doanh thu',
          data: values,
          backgroundColor: cssVar('--primary', '#c2410c'),
          borderRadius: 5,
          maxBarThickness: 36,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: false,
      layout: { padding: { top: 12, right: 8 } },
      scales: {
        x: { grid: { display: false }, ticks: {} },
        y: {
          beginAtZero: true,
          border: { display: false },
          grid: {},
          ticks: {
            callback: (value) =>
              new Intl.NumberFormat('vi-VN', { notation: 'compact' }).format(value),
          },
        },
      },
      plugins: {
        tooltip: {
          callbacks: { label: (context) => formatPrice(props.series[context.dataIndex]?.revenue) },
        },
      },
    },
  })
  paint(chart)
  chart.update('none')
}
onMounted(draw)
watch(() => props.series, draw, { deep: true })
watch(theme, draw, { flush: 'post' })
onBeforeUnmount(() => {
  chart?.destroy()
  chart = null
})
</script>
<template>
  <!-- Clipped: the canvas keeps its old width for a frame after the window narrows. -->
  <div class="position-relative revenue-chart" style="height: 300px">
    <canvas
      ref="canvas"
      role="img"
      aria-label="Biểu đồ doanh thu hàng hóa theo kỳ. Số tiền chi tiết nằm trong bảng bên dưới."
    />
  </div>
  <details class="mt-3">
    <summary>Xem số liệu doanh thu</summary>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Kỳ</th>
            <th>Đơn hoàn tất</th>
            <th>Doanh thu</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in series" :key="row.period">
            <td>{{ row.period }}</td>
            <td>{{ row.completed_orders }}</td>
            <td>{{ formatPrice(row.revenue) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </details>
</template>
<style scoped>
.revenue-chart {
  min-width: 0;
  max-width: 100%;
  overflow: hidden;
}
.revenue-chart canvas {
  max-width: 100%;
}
</style>
