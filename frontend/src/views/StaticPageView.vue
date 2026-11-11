<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import PageHeader from '../components/ui/PageHeader.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import { brand } from '../config/brand'
// Information pages linked from the footer. Plain content, no data; three layouts share one file:
// cards (things to do, each with its next step), checklist (rules to keep in mind), document (numbered text).
const props = defineProps({ page: { type: String, required: true } })
const menu = [
  ['Hỗ trợ', [['/help', 'Trợ giúp', 'search'], ['/safety', 'An toàn mua bán', 'shield'], ['/contact', 'Liên hệ', 'message']]],
  ['Về chúng tôi', [['/about', 'Giới thiệu', 'home'], ['/terms', 'Quy chế hoạt động', 'book'], ['/privacy', 'Chính sách bảo mật', 'lock']]],
]
const pages = {
  help: {
    title: 'Trợ giúp',
    lead: 'Những việc thường làm trên sàn, và nơi bắt đầu từng việc.',
    layout: 'cards',
    sections: [
      { icon: 'cart', heading: 'Mua hàng', text: 'Tìm món đồ bằng ô tìm kiếm hoặc danh mục, thêm vào giỏ hoặc bấm "Mua ngay", chọn địa chỉ và phương thức thanh toán. Đơn mua nằm ở mục "Đơn mua".', link: ['/products', 'Xem sản phẩm'] },
      { icon: 'upload', heading: 'Đăng tin', text: 'Bấm "Đăng tin", điền thông tin, tải ảnh rồi đăng. Tin hiện ngay, không chờ duyệt. Theo dõi ở "Quản lý tin".', link: ['/my-ads', 'Mở Quản lý tin'] },
      { icon: 'gavel', heading: 'Đấu giá', text: 'Vào "Sàn đấu giá" để ra giá. Giá và thời gian do máy chủ xác nhận; người thắng thanh toán trong thời hạn được thông báo.', link: ['/auctions', 'Vào sàn đấu giá'] },
      { icon: 'ticket', heading: 'Mã giảm giá', text: 'Nhập mã của sàn hoặc mã của gian hàng ở bước thanh toán rồi bấm "Áp dụng". Mỗi đơn dùng một mã của sàn và một mã cho mỗi gian hàng.', link: ['/cart', 'Mở giỏ hàng'] },
      { icon: 'user', heading: 'Xác minh tài khoản', text: 'Sau khi đăng ký, bấm nút trong thư chúng tôi gửi hoặc nhập mã 6 số trong thư, rồi đăng nhập. Không nhận được thư thì yêu cầu gửi lại.', link: ['/verify-email', 'Trang xác minh'] },
    ],
  },
  safety: {
    title: 'An toàn mua bán',
    lead: 'Bốn thói quen giúp bạn mua bán yên tâm.',
    layout: 'checklist',
    notice: 'Không chuyển khoản riêng cho người bán, kể cả khi họ yêu cầu. Chỉ thanh toán qua giỏ hàng và bước đặt hàng.',
    sections: [
      { icon: 'wallet', heading: 'Thanh toán trên sàn', text: 'Chỉ thanh toán qua giỏ hàng và bước đặt hàng. Không chuyển khoản riêng cho người bán theo yêu cầu ngoài hệ thống.' },
      { icon: 'eye', heading: 'Kiểm tra món đồ', text: 'Đọc kỹ mô tả tình trạng, xem ảnh và video đánh giá. Khi nhận hàng, kiểm tra trước khi xác nhận.' },
      { icon: 'flag', heading: 'Báo cáo', text: 'Thấy tin đăng, gian hàng hoặc bình luận có dấu hiệu bất thường, hãy dùng nút "Báo cáo". Quản trị viên sẽ xem xét.', link: ['/reports', 'Xem báo cáo của tôi'] },
      { icon: 'lock', heading: 'Bảo vệ tài khoản', text: 'Không chia sẻ mật khẩu và mã xác minh. Đăng xuất khi dùng máy chung.' },
    ],
  },
  contact: {
    title: 'Liên hệ',
    lead: 'Chọn đúng nơi để được trả lời nhanh nhất.',
    layout: 'cards',
    notice: `${brand.name} là đồ án học tập nên không có tổng đài hay hộp thư hỗ trợ.`,
    sections: [
      { icon: 'message', heading: 'Người mua và người bán', text: 'Trao đổi về một món đồ bằng nút "Nhắn người bán" ở trang sản phẩm; tin nhắn nằm trong mục Chat.', link: ['/products', 'Tìm món đồ'] },
      { icon: 'flag', heading: 'Quản trị viên', text: 'Các vấn đề về nội dung được gửi qua nút "Báo cáo" ở tin đăng hoặc gian hàng.', link: ['/reports', 'Xem báo cáo của tôi'] },
    ],
  },
  about: {
    title: 'Giới thiệu',
    lead: 'Mỗi món đồ cũ là một đời mới cho ai đó.',
    intro: `${brand.name} là sàn mua bán đồ đã qua sử dụng giữa người với người.`,
    layout: 'cards',
    notice: 'Đây là đồ án học tập chạy trên môi trường cục bộ với dữ liệu minh họa. Không có giao dịch tiền thật.',
    sections: [
      { icon: 'upload', heading: 'Đăng tin', text: 'Chụp ảnh, đặt giá và đăng. Tin hiện ngay cho người mua gần bạn.', link: ['/my-ads', 'Mở Quản lý tin'] },
      { icon: 'tag', heading: 'Mua giá cố định', text: 'Thêm vào giỏ hoặc mua ngay, thanh toán ngay trên sàn.', link: ['/products', 'Xem sản phẩm'] },
      { icon: 'gavel', heading: 'Đấu giá', text: 'Ra giá trực tiếp, thắng phiên là thanh toán.', link: ['/auctions', 'Vào sàn đấu giá'] },
      { icon: 'store', heading: 'Gian hàng', text: 'Xem người bán, đánh giá và video của từng gian hàng, rồi trò chuyện với họ.', link: ['/shops', 'Khám phá gian hàng'] },
    ],
  },
  terms: {
    title: 'Quy chế hoạt động',
    lead: 'Những quy định áp dụng cho người mua, người bán và quản trị viên.',
    layout: 'document',
    sections: [
      { heading: 'Tin đăng', text: 'Người bán mô tả trung thực tình trạng món đồ. Tin đăng và gian hàng hiển thị ngay, không chờ duyệt; quản trị viên xem xét các tin bị báo cáo.' },
      { heading: 'Đơn hàng', text: 'Giá và tồn kho được máy chủ kiểm tra lại khi đặt hàng. Đơn có thể bị hủy theo các trạng thái cho phép; tồn kho được hoàn lại khi hủy.' },
      { heading: 'Đấu giá', text: 'Người bán không tự ra giá cho phiên của mình. Quản trị viên có thể hủy phiên vi phạm kèm lý do.' },
      { heading: 'Xử lý vi phạm', text: 'Quản trị viên có thể ẩn tin đăng, ẩn bình luận hoặc tạm đình chỉ gian hàng; mọi thao tác đều được ghi nhật ký.' },
    ],
  },
  privacy: {
    title: 'Chính sách bảo mật',
    lead: 'Sàn lưu gì về bạn, và lưu ở đâu.',
    layout: 'document',
    sections: [
      { heading: 'Dữ liệu được lưu', text: 'Thông tin tài khoản, địa chỉ giao hàng, đơn hàng, tin nhắn và báo cáo bạn gửi.' },
      { heading: 'Thư điện tử và mật khẩu', text: 'Thư điện tử dùng để đăng nhập, xác minh tài khoản và lấy lại mật khẩu. Mật khẩu và mã xác minh chỉ được lưu ở dạng băm, không lưu bản gốc.' },
      { heading: 'Gợi ý sản phẩm', text: 'Khi bạn đã đăng nhập, sàn ghi lại sản phẩm bạn xem và từ khóa bạn tìm để gợi ý tin đăng. Không lưu địa chỉ IP. Khách chưa đăng nhập không bị ghi.' },
      { heading: 'Trên trình duyệt', text: 'Khu vực đã chọn, chế độ sử dụng và trạng thái đã xem hướng dẫn được lưu trong trình duyệt của bạn, không gửi lên máy chủ.' },
      { heading: 'Thanh toán', text: 'Sàn không lưu thông tin thẻ hay tài khoản ngân hàng.' },
    ],
  },
}
const content = computed(() => pages[props.page] || pages.help)
// On phones the menu is one scrolling row: the current page is brought into it, without moving the page.
const menuRow = ref(null)
async function revealCurrent() {
  await nextTick()
  const row = menuRow.value
  const current = row?.querySelector('[aria-current="page"]')
  if (!current || row.scrollWidth <= row.clientWidth) return
  const offset = current.getBoundingClientRect().left - row.getBoundingClientRect().left
  row.scrollLeft += offset - (row.clientWidth - current.offsetWidth) / 2
}
onMounted(revealCurrent)
watch(() => props.page, revealCurrent)
</script>
<template>
  <div class="static-layout">
    <nav ref="menuRow" class="static-menu" aria-label="Trang thông tin">
      <div v-for="[group, links] in menu" :key="group" class="static-menu-group">
        <p>{{ group }}</p>
        <RouterLink v-for="[path, label, icon] in links" :key="path" :to="path"
          ><AppIcon :name="icon" :size="18" />{{ label }}</RouterLink
        >
      </div>
    </nav>
    <section class="buyer-flow static-page">
      <header v-if="content.intro" class="static-field">
        <h1>{{ content.title }}</h1>
        <p class="static-field-line">{{ content.lead }}</p>
        <p class="static-field-intro">{{ content.intro }}</p>
      </header>
      <PageHeader v-else :title="content.title" :description="content.lead" />
      <p v-if="content.notice" class="static-notice" role="note">
        <AppIcon name="shield" :size="20" /><span>{{ content.notice }}</span>
      </p>
      <div class="static-sections" :class="`is-${content.layout}`">
        <article v-for="section in content.sections" :key="section.heading" class="static-block">
          <span v-if="section.icon" class="static-block-icon" aria-hidden="true"
            ><AppIcon :name="section.icon" :size="22"
          /></span>
          <div class="static-block-body">
            <h2>{{ section.heading }}</h2>
            <p>{{ section.text }}</p>
            <RouterLink v-if="section.link" :to="section.link[0]" class="static-block-link"
              >{{ section.link[1] }}<AppIcon name="arrowRight" :size="16"
            /></RouterLink>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>
<style scoped>
.static-layout {
  display: grid;
  grid-template-columns: 15rem minmax(0, 1fr);
  align-items: start;
  gap: var(--space-8);
}
/* The six pages, always one tap apart; the current one is marked by the router. */
.static-menu {
  position: sticky;
  top: 6rem;
  display: grid;
  gap: var(--space-5);
}
.static-menu-group {
  display: grid;
  gap: var(--space-1);
}
.static-menu-group p {
  margin: 0 0 var(--space-1);
  padding-inline: var(--space-3);
  color: var(--muted);
  font-size: var(--font-size-small);
  font-weight: 600;
}
.static-menu a {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-height: var(--control-height);
  padding-inline: var(--space-3);
  border-radius: var(--radius-sm);
  color: var(--text);
  font-size: var(--font-size-body);
  font-weight: 500;
  text-decoration: none;
  transition: background-color var(--transition-fast);
}
.static-menu a:hover {
  background: var(--surface-2);
}
.static-menu a.router-link-exact-active {
  background: var(--primary-soft);
  color: var(--primary-ink);
  font-weight: 700;
}
:is(.static-menu a, .static-block-link):focus-visible {
  outline: var(--focus-ring);
  outline-offset: 2px;
}
.static-page {
  max-width: 56rem;
}
.static-field {
  margin-bottom: var(--space-6);
  padding: clamp(1.75rem, 4vw, 3rem);
  border-radius: var(--radius-xl);
  background: var(--field);
  color: var(--on-field);
}
.static-field h1 {
  margin: 0 0 var(--space-3);
  color: var(--on-field-muted);
  font-family: var(--font-ui);
  font-size: var(--font-size-body);
  font-weight: 600;
  letter-spacing: 0;
}
.static-field-line {
  max-width: 20ch;
  margin: 0;
  font-family: var(--font-display);
  font-size: clamp(2rem, 4vw, 3rem);
  font-weight: 500;
  line-height: 1.08;
  letter-spacing: -0.015em;
  text-wrap: balance;
}
.static-field-intro {
  max-width: 46ch;
  margin: var(--space-4) 0 0;
  color: var(--on-field-muted);
  font-size: 1.05rem;
  line-height: 1.6;
}
.static-notice {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  margin: 0 0 var(--space-5);
  padding: var(--space-4) var(--space-5);
  border-radius: var(--radius-lg);
  background: var(--primary-soft);
  color: var(--primary-ink);
  font-weight: 500;
  line-height: 1.55;
}
.static-notice :deep(svg) {
  flex: none;
  margin-top: 0.1rem;
}
.static-block h2 {
  margin: 0 0 var(--space-2);
  font-size: 1.05rem;
  font-weight: 700;
  line-height: 1.35;
  letter-spacing: 0;
}
.static-block p {
  margin: 0;
  color: var(--muted);
  line-height: 1.7;
}
.static-block-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: var(--radius-md);
  background: var(--primary-soft);
  color: var(--primary-ink);
}
.static-block-body {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  flex: 1;
  min-width: 0;
}
.static-block-link {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  min-height: var(--control-height);
  margin-top: auto;
  padding-top: var(--space-2);
  color: var(--primary);
  font-weight: 600;
  text-decoration: none;
}
.static-block-link:hover {
  color: var(--primary-hover);
  text-decoration: underline;
}
/* Cards: things to do, each ending on its next step. */
.is-cards {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-4);
}
.is-cards .static-block {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-5);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
}
/* Checklist and document: one panel of rows, so the text reads top to bottom. */
.is-checklist,
.is-document {
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
}
.is-checklist .static-block,
.is-document .static-block {
  display: flex;
  gap: var(--space-4);
  padding: var(--space-5) var(--space-6);
}
.is-checklist .static-block + .static-block,
.is-document .static-block + .static-block {
  border-top: 1px solid var(--border);
}
/* Document: numbered, so a rule can be referred to. */
.is-document {
  counter-reset: static-rule;
}
.is-document .static-block {
  counter-increment: static-rule;
}
.is-document .static-block::before {
  content: counter(static-rule);
  flex: none;
  width: 2rem;
  color: var(--muted);
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 500;
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
}
.is-document .static-block p {
  color: var(--text);
}
@media (max-width: 991px) {
  .static-layout {
    grid-template-columns: minmax(0, 1fr);
    gap: var(--space-5);
  }
  /* One scrolling row above the page instead of a side column. */
  .static-menu {
    position: static;
    display: flex;
    gap: var(--space-1);
    overflow-x: auto;
    padding-bottom: var(--space-1);
    scrollbar-width: none;
  }
  .static-menu::-webkit-scrollbar {
    display: none;
  }
  .static-menu-group {
    display: contents;
  }
  .static-menu-group p {
    display: none;
  }
  .static-menu a {
    flex: none;
    border: 1px solid var(--border);
    background: var(--surface);
    white-space: nowrap;
  }
}
@media (max-width: 575px) {
  .is-cards {
    grid-template-columns: minmax(0, 1fr);
  }
  .is-checklist .static-block,
  .is-document .static-block {
    gap: var(--space-3);
    padding: var(--space-4);
  }
}
</style>
