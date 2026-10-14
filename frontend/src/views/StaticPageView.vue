<script setup>
import { computed } from 'vue'
import PageHeader from '../components/ui/PageHeader.vue'
import { brand } from '../config/brand'
// Simple information pages linked from the footer. Plain text, no data.
const props = defineProps({ page: { type: String, required: true } })
const pages = {
  help: {
    title: 'Trợ giúp',
    sections: [
      ['Mua hàng', 'Tìm món đồ bằng ô tìm kiếm hoặc danh mục, thêm vào giỏ hoặc bấm "Mua ngay", chọn địa chỉ và phương thức thanh toán. Đơn mua nằm ở mục "Đơn mua".'],
      ['Đăng tin', 'Bấm "Đăng tin", điền thông tin, tải ảnh rồi gửi duyệt. Tin chỉ hiện công khai sau khi quản trị viên duyệt. Theo dõi ở "Quản lý tin".'],
      ['Đấu giá', 'Vào "Sàn đấu giá" để ra giá. Giá và thời gian do máy chủ xác nhận; người thắng thanh toán trong thời hạn được thông báo.'],
      ['Mã giảm giá', 'Nhập mã của sàn hoặc mã của gian hàng ở bước thanh toán rồi bấm "Áp dụng". Mỗi đơn dùng một mã của sàn và một mã cho mỗi gian hàng.'],
    ],
  },
  safety: {
    title: 'An toàn mua bán',
    sections: [
      ['Thanh toán trên sàn', 'Chỉ thanh toán qua giỏ hàng và bước đặt hàng. Không chuyển khoản riêng cho người bán theo yêu cầu ngoài hệ thống.'],
      ['Kiểm tra món đồ', 'Đọc kỹ mô tả tình trạng, xem ảnh và video đánh giá. Khi nhận hàng, kiểm tra trước khi xác nhận.'],
      ['Báo cáo', 'Thấy tin đăng, gian hàng hoặc bình luận có dấu hiệu bất thường, hãy dùng nút "Báo cáo". Quản trị viên sẽ xem xét.'],
      ['Bảo vệ tài khoản', 'Không chia sẻ mật khẩu. Đăng xuất khi dùng máy chung.'],
    ],
  },
  contact: {
    title: 'Liên hệ',
    sections: [
      ['Người mua và người bán', 'Trao đổi về một món đồ bằng nút "Nhắn người bán" ở trang sản phẩm; tin nhắn nằm trong mục Chat.'],
      ['Quản trị viên', `${brand.name} là đồ án học tập nên không có tổng đài hay hộp thư hỗ trợ. Các vấn đề về nội dung được gửi qua nút "Báo cáo".`],
    ],
  },
  about: {
    title: 'Giới thiệu',
    sections: [
      [brand.name, `${brand.name} là sàn mua bán đồ đã qua sử dụng giữa người với người: đăng tin, mua giá cố định, đấu giá, video đánh giá và trò chuyện với người bán.`],
      ['Phạm vi', 'Đây là đồ án học tập chạy trên môi trường cục bộ với dữ liệu minh họa. Không có giao dịch tiền thật.'],
    ],
  },
  terms: {
    title: 'Quy chế hoạt động',
    sections: [
      ['Tin đăng', 'Người bán mô tả trung thực tình trạng món đồ. Tin đăng và gian hàng được quản trị viên kiểm duyệt trước khi hiển thị.'],
      ['Đơn hàng', 'Giá và tồn kho được máy chủ kiểm tra lại khi đặt hàng. Đơn có thể bị hủy theo các trạng thái cho phép; tồn kho được hoàn lại khi hủy.'],
      ['Đấu giá', 'Người bán không tự ra giá cho phiên của mình. Quản trị viên có thể hủy phiên vi phạm kèm lý do.'],
      ['Xử lý vi phạm', 'Quản trị viên có thể ẩn tin đăng, ẩn bình luận hoặc tạm đình chỉ gian hàng; mọi thao tác đều được ghi nhật ký.'],
    ],
  },
  privacy: {
    title: 'Chính sách bảo mật',
    sections: [
      ['Dữ liệu được lưu', 'Thông tin tài khoản, địa chỉ giao hàng, đơn hàng, tin nhắn và báo cáo bạn gửi.'],
      ['Gợi ý sản phẩm', 'Khi bạn đã đăng nhập, sàn ghi lại sản phẩm bạn xem và từ khóa bạn tìm để gợi ý tin đăng. Không lưu địa chỉ IP. Khách chưa đăng nhập không bị ghi.'],
      ['Trên trình duyệt', 'Khu vực đã chọn, chế độ sử dụng và trạng thái đã xem hướng dẫn được lưu trong trình duyệt của bạn, không gửi lên máy chủ.'],
      ['Thanh toán', 'Sàn không lưu thông tin thẻ hay tài khoản ngân hàng.'],
    ],
  },
}
const content = computed(() => pages[props.page] || pages.help)
</script>
<template>
  <section class="buyer-flow static-page">
    <PageHeader :title="content.title" />
    <article v-for="[heading, text] in content.sections" :key="heading" class="static-page-block">
      <h2>{{ heading }}</h2>
      <p>{{ text }}</p>
    </article>
  </section>
</template>
<style scoped>
.static-page {
  max-width: 48rem;
}
.static-page-block {
  margin-bottom: var(--space-4);
  padding: var(--space-5) var(--space-6);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
}
.static-page-block h2 {
  margin-bottom: var(--space-2);
  font-size: 1.05rem;
  font-weight: 800;
}
.static-page-block p {
  margin: 0;
  line-height: 1.75;
}
</style>
