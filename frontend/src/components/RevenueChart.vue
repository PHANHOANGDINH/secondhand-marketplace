<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js'
import { formatPrice } from '../composables/productPresentation'
Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip)
const props = defineProps({ series: { type: Array, required: true } })
const canvas = ref(null)
let chart = null
function draw() {
  if (!canvas.value) return
  const labels = props.series.map((row) => row.period)
  // Chart coordinates are numeric; labels/table always use the original decimal strings.
  const values = props.series.map((row) => Number(row.revenue))
  if (chart) {
    chart.data.labels = labels
    chart.data.datasets[0].data = values
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
          backgroundColor: '#0d6efd',
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
        y: {
          beginAtZero: true,
          border: { display: false },
          grid: { color: 'rgba(108, 117, 125, 0.1)' },
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
}
onMounted(draw)
watch(() => props.series, draw, { deep: true })
onBeforeUnmount(() => {
  chart?.destroy()
  chart = null
})
</script>
<template>
  <div class="position-relative" style="height: 300px">
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
