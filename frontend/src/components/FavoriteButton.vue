<script setup>
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useWishlistStore } from '../stores/wishlist'
import AppIcon from './ui/AppIcon.vue'
const props = defineProps({ product: { type: Object, required: true }, compact: Boolean })
const auth = useAuthStore(),
  wishlist = useWishlistStore(),
  route = useRoute(),
  router = useRouter()
let snapshotOwner
watch(
  () => props.product,
  (product) => {
    snapshotOwner = auth.user?.id
    wishlist.hydrate(product)
  },
  { immediate: true },
)
watch(
  () => auth.user?.id,
  (id, previous) => {
    if (id && !previous && !snapshotOwner) {
      snapshotOwner = id
      wishlist.hydrate(props.product)
    }
  },
)
const saved = computed(() => auth.isAuthenticated && Boolean(wishlist.states[props.product.id]))
async function toggle() {
  if (!auth.isAuthenticated) {
    await router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  await wishlist.toggle(props.product)
}
</script>
<template>
  <div class="favorite-control" :class="{ compact }">
    <button
      type="button"
      class="btn btn-outline-primary"
      :class="{ saved }"
      :aria-label="`${saved ? 'Bỏ yêu thích' : 'Thêm vào yêu thích'}: ${product.name}`"
      :aria-pressed="saved"
      :aria-busy="Boolean(wishlist.pending[product.id])"
      :disabled="Boolean(wishlist.pending[product.id])"
      @click.stop="toggle"
    >
      <AppIcon name="heart" :filled="saved" :size="compact ? 20 : 18" />
      <span v-if="!compact">{{
        wishlist.pending[product.id] ? 'Đang lưu…' : saved ? 'Đã yêu thích' : 'Thêm vào yêu thích'
      }}</span>
    </button>
    <p v-if="wishlist.errors[product.id]" role="alert" class="small text-danger mt-2 mb-0">
      {{ wishlist.errors[product.id] }}
    </p>
  </div>
</template>
<style scoped>
.favorite-control .btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}
.favorite-control .saved {
  background: var(--bs-primary-bg-subtle);
}
.compact .btn {
  width: 44px;
  height: 44px;
  padding: 0;
  border-radius: 50%;
  border-color: var(--border);
  color: var(--text);
  background: var(--surface);
}
.compact .btn:hover {
  color: var(--primary);
  border-color: var(--primary);
  background: var(--surface);
}
.compact .saved {
  color: var(--danger);
  border-color: var(--border);
  background: var(--surface);
}
.compact p {
  max-width: 200px;
  background: var(--bs-body-bg);
  padding: 0.5rem;
  border-radius: var(--radius-sm);
}
</style>
